<?php

declare(strict_types=1);

namespace App\Pricing\Application\DTO\Analytics;

/**
 * Overall pricing analytics summary DTO.
 *
 * Aggregates all pricing analytics into a single dashboard view.
 */
final readonly class PricingSummaryDTO
{
    /**
     * @param array<int, PromotionPerformanceDTO> $topPromotions
     */
    public function __construct(
        public int $totalOrdersCount,
        public int $totalRevenueAmount,
        public string $totalRevenueCurrency,
        public int $totalDiscountAmount,
        public string $totalDiscountCurrency,
        public float $averageOrderValue,
        public float $discountRate,
        public int $promotionsActiveCount,
        public int $couponsUsedCount,
        public array $topPromotions,
        public DateRangeFilter $period,
    ) {
    }

    /**
     * Create from query results.
     *
     * @param array<string, mixed> $summary
     * @param array<int, PromotionPerformanceDTO> $topPromotions
     */
    public static function create(
        array $summary,
        array $topPromotions,
        DateRangeFilter $period
    ): self {
        return new self(
            totalOrdersCount: (int) $summary['total_orders_count'],
            totalRevenueAmount: (int) $summary['total_revenue_amount'],
            totalRevenueCurrency: $summary['total_revenue_currency'] ?? 'USD',
            totalDiscountAmount: (int) $summary['total_discount_amount'],
            totalDiscountCurrency: $summary['total_discount_currency'] ?? 'USD',
            averageOrderValue: (float) $summary['average_order_value'],
            discountRate: (float) $summary['discount_rate'],
            promotionsActiveCount: (int) $summary['promotions_active_count'],
            couponsUsedCount: (int) $summary['coupons_used_count'],
            topPromotions: $topPromotions,
            period: $period,
        );
    }

    /**
     * Convert to array for API response.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'period' => [
                'startDate' => $this->period->startDate?->format(\DateTimeInterface::ATOM),
                'endDate' => $this->period->endDate?->format(\DateTimeInterface::ATOM),
                'preset' => $this->period->preset,
                'days' => $this->period->getDays(),
            ],
            'summary' => [
                'totalOrders' => $this->totalOrdersCount,
                'totalRevenue' => [
                    'amount' => $this->totalRevenueAmount,
                    'currency' => $this->totalRevenueCurrency,
                    'formatted' => number_format($this->totalRevenueAmount / 100, 2) . ' ' . $this->totalRevenueCurrency,
                ],
                'totalDiscount' => [
                    'amount' => $this->totalDiscountAmount,
                    'currency' => $this->totalDiscountCurrency,
                    'formatted' => number_format($this->totalDiscountAmount / 100, 2) . ' ' . $this->totalDiscountCurrency,
                ],
                'averageOrderValue' => round($this->averageOrderValue, 2),
                'discountRate' => round($this->discountRate, 2) . '%',
                'promotionsActive' => $this->promotionsActiveCount,
                'couponsUsed' => $this->couponsUsedCount,
            ],
            'topPromotions' => array_map(
                fn (PromotionPerformanceDTO $dto) => $dto->toArray(),
                $this->topPromotions
            ),
        ];
    }
}
