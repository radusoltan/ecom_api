<?php

declare(strict_types=1);

namespace App\Pricing\Application\Query\GetPromotionPerformance;

use App\Pricing\Application\DTO\Analytics\PromotionPerformanceDTO;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Handler for getting promotion performance analytics.
 *
 * Uses raw SQL for performance with complex aggregations.
 * Results are cached for 1 hour.
 */
final readonly class GetPromotionPerformanceQueryHandler
{
    public function __construct(
        private Connection $connection,
        private CacheInterface $cache,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return array<int, PromotionPerformanceDTO>
     */
    public function __invoke(GetPromotionPerformanceQuery $query): array
    {
        $cacheKey = sprintf(
            'pricing_analytics.promotion_performance.%s.%s_%s.%s.%d',
            $query->tenantId->toString(),
            $query->dateRange->startDate?->format('Y-m-d') ?? 'all',
            $query->dateRange->endDate?->format('Y-m-d') ?? 'all',
            $query->promotionId ?? 'all',
            $query->limit
        );

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($query) {
            $item->expiresAfter(3600); // 1 hour cache

            return $this->fetchPromotionPerformance($query);
        });
    }

    /**
     * @return array<int, PromotionPerformanceDTO>
     */
    private function fetchPromotionPerformance(GetPromotionPerformanceQuery $query): array
    {
        // Build base query
        $sql = <<<SQL
            SELECT
                p.id AS promotion_id,
                p.name AS promotion_name,
                p.type AS promotion_type,
                p.valid_from,
                p.valid_to,
                COUNT(DISTINCT o.id) AS orders_count,
                COALESCE(SUM(
                    (SELECT SUM(
                        CAST(line->>'quantity' AS INTEGER) *
                        CAST(line->>'unitPriceAmount' AS INTEGER)
                    ) FROM jsonb_array_elements(o.lines) AS line)
                ), 0) AS total_revenue_amount,
                COALESCE(o.discount_currency, 'USD') AS total_revenue_currency,
                COALESCE(SUM(o.discount_amount), 0) AS total_discount_amount,
                COALESCE(o.discount_currency, 'USD') AS total_discount_currency,
                COALESCE(AVG(o.discount_amount), 0) AS average_discount_amount,
                ROUND(
                    CAST(COUNT(DISTINCT o.id) AS NUMERIC) /
                    NULLIF(CAST(
                        (SELECT COUNT(*) FROM orders WHERE tenant_id = :tenantId AND created_at BETWEEN :startDate AND :endDate)
                    AS NUMERIC), 0) * 100,
                    2
                ) AS conversion_rate
            FROM promotions p
            LEFT JOIN orders o ON
                o.tenant_id = p.tenant_id AND
                o.applied_promotions::text LIKE '%' || p.id || '%'
        SQL;

        // Add WHERE clauses
        $conditions = ['p.tenant_id = :tenantId'];
        $params = [
            'tenantId' => $query->tenantId->toString(),
        ];
        $types = [
            'tenantId' => \PDO::PARAM_STR,
        ];

        // Date range filter on orders
        if ($query->dateRange->hasStartDate() && $query->dateRange->hasEndDate()) {
            $conditions[] = '(o.created_at IS NULL OR o.created_at BETWEEN :startDate AND :endDate)';
            $params['startDate'] = $query->dateRange->startDate?->format('Y-m-d H:i:s') ?? '';
            $params['endDate'] = $query->dateRange->endDate?->format('Y-m-d H:i:s') ?? '';
            $types['startDate'] = \PDO::PARAM_STR;
            $types['endDate'] = \PDO::PARAM_STR;
        }

        // Specific promotion filter
        if ($query->promotionId !== null) {
            $conditions[] = 'p.id = :promotionId';
            $params['promotionId'] = $query->promotionId;
            $types['promotionId'] = \PDO::PARAM_STR;
        }

        $sql .= ' WHERE ' . implode(' AND ', $conditions);
        $sql .= ' GROUP BY p.id, p.name, p.type, p.valid_from, p.valid_to, o.discount_currency';
        $sql .= ' ORDER BY orders_count DESC, total_revenue_amount DESC';
        $sql .= ' LIMIT :limit';

        $params['limit'] = $query->limit;
        $types['limit'] = \PDO::PARAM_INT;

        try {
            $results = $this->connection->fetchAllAssociative($sql, $params, $types);

            return array_map(
                fn (array $row) => PromotionPerformanceDTO::fromArray($row),
                $results
            );
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch promotion performance analytics', [
                'error' => $e->getMessage(),
                'tenantId' => $query->tenantId->toString(),
            ]);

            return [];
        }
    }
}
