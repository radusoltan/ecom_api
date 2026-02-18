<?php

declare(strict_types=1);

namespace App\Pricing\Application\Query\GetDiscountUsage;

use App\Pricing\Application\DTO\Analytics\DiscountUsageDTO;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Handler for getting discount usage analytics.
 *
 * Analyzes coupon/discount usage patterns across orders.
 * Results are cached for 1 hour.
 */
final readonly class GetDiscountUsageQueryHandler
{
    public function __construct(
        private Connection $connection,
        private CacheInterface $cache,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(GetDiscountUsageQuery $query): DiscountUsageDTO
    {
        $cacheKey = sprintf(
            'pricing_analytics.discount_usage.%s.%s_%s.%d',
            $query->tenantId->toString(),
            $query->dateRange->startDate?->format('Y-m-d') ?? 'all',
            $query->dateRange->endDate?->format('Y-m-d') ?? 'all',
            $query->topCouponsLimit
        );

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($query) {
            $item->expiresAfter(3600); // 1 hour cache

            $summary = $this->fetchDiscountSummary($query);
            $topCoupons = $this->fetchTopCoupons($query);
            $unusedPromotions = $this->fetchUnusedPromotions($query);

            return DiscountUsageDTO::create($summary, $topCoupons, $unusedPromotions);
        });
    }

    /**
     * Fetch summary statistics.
     *
     * @return array<string, mixed>
     */
    private function fetchDiscountSummary(GetDiscountUsageQuery $query): array
    {
        $sql = <<<SQL
            SELECT
                COUNT(DISTINCT coupon_code) FILTER (WHERE coupon_code IS NOT NULL) AS total_coupons_used,
                COALESCE(SUM(discount_amount), 0) AS total_discount_amount,
                COALESCE(MAX(discount_currency), 'USD') AS total_discount_currency,
                COALESCE(AVG(discount_amount) FILTER (WHERE discount_amount > 0), 0) AS average_discount_per_order,
                (SELECT COUNT(*) FROM promotions WHERE tenant_id = :tenantId AND is_active = true AND coupon_code IS NOT NULL) AS active_coupons_count,
                (SELECT COUNT(*) FROM promotions WHERE tenant_id = :tenantId AND is_active = false AND coupon_code IS NOT NULL) AS expired_coupons_count
            FROM orders
            WHERE tenant_id = :tenantId
        SQL;

        $params = ['tenantId' => $query->tenantId->toString()];
        $types = ['tenantId' => ParameterType::STRING];

        if ($query->dateRange->hasStartDate() && $query->dateRange->hasEndDate()) {
            $sql .= ' AND created_at BETWEEN :startDate AND :endDate';
            $params['startDate'] = $query->dateRange->startDate?->format('Y-m-d H:i:s') ?? '';
            $params['endDate'] = $query->dateRange->endDate?->format('Y-m-d H:i:s') ?? '';
            $types['startDate'] = ParameterType::STRING;
            $types['endDate'] = ParameterType::STRING;
        }

        try {
            $result = $this->connection->fetchAssociative($sql, $params, $types);

            return $result ?: [
                'total_coupons_used' => 0,
                'total_discount_amount' => 0,
                'total_discount_currency' => 'USD',
                'average_discount_per_order' => 0,
                'active_coupons_count' => 0,
                'expired_coupons_count' => 0,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch discount summary', [
                'error' => $e->getMessage(),
                'tenantId' => $query->tenantId->toString(),
            ]);

            return [
                'total_coupons_used' => 0,
                'total_discount_amount' => 0,
                'total_discount_currency' => 'USD',
                'average_discount_per_order' => 0,
                'active_coupons_count' => 0,
                'expired_coupons_count' => 0,
            ];
        }
    }

    /**
     * Fetch top used coupons.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchTopCoupons(GetDiscountUsageQuery $query): array
    {
        $sql = <<<SQL
            SELECT
                o.coupon_code,
                p.name AS promotion_name,
                COUNT(o.id) AS usage_count,
                COALESCE(SUM(o.discount_amount), 0) AS total_discount,
                COALESCE(o.discount_currency, 'USD') AS currency
            FROM orders o
            LEFT JOIN promotions p ON p.coupon_code = o.coupon_code AND p.tenant_id = o.tenant_id
            WHERE o.tenant_id = :tenantId
                AND o.coupon_code IS NOT NULL
        SQL;

        $params = ['tenantId' => $query->tenantId->toString()];
        $types = ['tenantId' => ParameterType::STRING];

        if ($query->dateRange->hasStartDate() && $query->dateRange->hasEndDate()) {
            $sql .= ' AND o.created_at BETWEEN :startDate AND :endDate';
            $params['startDate'] = $query->dateRange->startDate?->format('Y-m-d H:i:s') ?? '';
            $params['endDate'] = $query->dateRange->endDate?->format('Y-m-d H:i:s') ?? '';
            $types['startDate'] = ParameterType::STRING;
            $types['endDate'] = ParameterType::STRING;
        }

        $sql .= ' GROUP BY o.coupon_code, p.name, o.discount_currency';
        $sql .= ' ORDER BY usage_count DESC';
        $sql .= ' LIMIT :limit';

        $params['limit'] = $query->topCouponsLimit;
        $types['limit'] = ParameterType::INTEGER;

        try {
            return $this->connection->fetchAllAssociative($sql, $params, $types);
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch top coupons', [
                'error' => $e->getMessage(),
                'tenantId' => $query->tenantId->toString(),
            ]);

            return [];
        }
    }

    /**
     * Fetch unused promotions (active but never used).
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchUnusedPromotions(GetDiscountUsageQuery $query): array
    {
        $sql = <<<SQL
            SELECT
                p.id,
                p.name,
                p.type,
                p.coupon_code,
                p.valid_from,
                p.valid_to
            FROM promotions p
            WHERE p.tenant_id = :tenantId
                AND p.is_active = true
                AND p.coupon_code IS NOT NULL
                AND NOT EXISTS (
                    SELECT 1 FROM orders o
                    WHERE o.tenant_id = p.tenant_id
                        AND o.coupon_code = p.coupon_code
                )
            ORDER BY p.created_at DESC
            LIMIT 10
        SQL;

        try {
            return $this->connection->fetchAllAssociative($sql, [
                'tenantId' => $query->tenantId->toString(),
            ], [
                'tenantId' => ParameterType::STRING,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch unused promotions', [
                'error' => $e->getMessage(),
                'tenantId' => $query->tenantId->toString(),
            ]);

            return [];
        }
    }
}
