<?php

declare(strict_types=1);

namespace App\Pricing\Application\Query\GetPricingSummary;

use App\Pricing\Application\DTO\Analytics\PricingSummaryDTO;
use App\Pricing\Application\Query\GetPromotionPerformance\GetPromotionPerformanceQuery;
use App\Pricing\Application\Query\GetPromotionPerformance\GetPromotionPerformanceQueryHandler;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Handler for getting overall pricing analytics summary.
 *
 * Aggregates multiple analytics queries into a single dashboard view.
 * Results are cached for 1 hour.
 */
final readonly class GetPricingSummaryQueryHandler
{
    public function __construct(
        private Connection $connection,
        private GetPromotionPerformanceQueryHandler $promotionPerformanceHandler,
        private CacheInterface $cache,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(GetPricingSummaryQuery $query): PricingSummaryDTO
    {
        $cacheKey = sprintf(
            'pricing_analytics.summary.%s.%s_%s.%d',
            $query->tenantId->toString(),
            $query->dateRange->startDate?->format('Y-m-d') ?? 'all',
            $query->dateRange->endDate?->format('Y-m-d') ?? 'all',
            $query->topPromotionsLimit
        );

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($query) {
            $item->expiresAfter(3600); // 1 hour cache

            $summary = $this->fetchSummaryData($query);
            $topPromotions = ($this->promotionPerformanceHandler)(
                new GetPromotionPerformanceQuery(
                    tenantId: $query->tenantId,
                    dateRange: $query->dateRange,
                    promotionId: null,
                    limit: $query->topPromotionsLimit
                )
            );

            return PricingSummaryDTO::create($summary, $topPromotions, $query->dateRange);
        });
    }

    /**
     * Fetch overall summary data.
     *
     * @return array<string, mixed>
     */
    private function fetchSummaryData(GetPricingSummaryQuery $query): array
    {
        $sql = <<<SQL
            SELECT
                COUNT(*) AS total_orders_count,
                COALESCE(SUM(
                    (SELECT SUM(
                        CAST(line->>'quantity' AS INTEGER) *
                        CAST(line->>'unitPriceAmount' AS INTEGER)
                    ) FROM jsonb_array_elements(lines) AS line)
                ), 0) AS total_revenue_amount,
                COALESCE(MAX(discount_currency), 'USD') AS total_revenue_currency,
                COALESCE(SUM(discount_amount), 0) AS total_discount_amount,
                COALESCE(MAX(discount_currency), 'USD') AS total_discount_currency,
                COALESCE(AVG(
                    (SELECT SUM(
                        CAST(line->>'quantity' AS INTEGER) *
                        CAST(line->>'unitPriceAmount' AS INTEGER)
                    ) FROM jsonb_array_elements(lines) AS line)
                ), 0) AS average_order_value,
                ROUND(
                    CASE
                        WHEN SUM(
                            (SELECT SUM(
                                CAST(line->>'quantity' AS INTEGER) *
                                CAST(line->>'unitPriceAmount' AS INTEGER)
                            ) FROM jsonb_array_elements(lines) AS line)
                        ) > 0
                        THEN (CAST(COALESCE(SUM(discount_amount), 0) AS NUMERIC) /
                              CAST(SUM(
                                  (SELECT SUM(
                                      CAST(line->>'quantity' AS INTEGER) *
                                      CAST(line->>'unitPriceAmount' AS INTEGER)
                                  ) FROM jsonb_array_elements(lines) AS line)
                              ) AS NUMERIC)) * 100
                        ELSE 0
                    END,
                    2
                ) AS discount_rate,
                (SELECT COUNT(*) FROM promotions WHERE tenant_id = :tenantId AND is_active = true) AS promotions_active_count,
                COUNT(*) FILTER (WHERE coupon_code IS NOT NULL) AS coupons_used_count
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
                'total_orders_count' => 0,
                'total_revenue_amount' => 0,
                'total_revenue_currency' => 'USD',
                'total_discount_amount' => 0,
                'total_discount_currency' => 'USD',
                'average_order_value' => 0,
                'discount_rate' => 0,
                'promotions_active_count' => 0,
                'coupons_used_count' => 0,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch pricing summary', [
                'error' => $e->getMessage(),
                'tenantId' => $query->tenantId->toString(),
            ]);

            return [
                'total_orders_count' => 0,
                'total_revenue_amount' => 0,
                'total_revenue_currency' => 'USD',
                'total_discount_amount' => 0,
                'total_discount_currency' => 'USD',
                'average_order_value' => 0,
                'discount_rate' => 0,
                'promotions_active_count' => 0,
                'coupons_used_count' => 0,
            ];
        }
    }
}
