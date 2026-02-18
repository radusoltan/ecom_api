<?php

declare(strict_types=1);

namespace App\Pricing\Application\DTO\Analytics;

/**
 * Discount usage analytics DTO.
 *
 * Tracks discount/coupon usage patterns:
 * - Most used coupons
 * - Total discount amounts
 * - Unused promotions
 */
final readonly class DiscountUsageDTO
{
    /**
     * @param array<int, array<string, mixed>> $topCoupons
     * @param array<int, array<string, mixed>> $unusedPromotions
     */
    public function __construct(
        public int $totalCouponsUsed,
        public int $totalDiscountAmount,
        public string $totalDiscountCurrency,
        public float $averageDiscountPerOrder,
        public array $topCoupons,
        public array $unusedPromotions,
        public int $activeCouponsCount,
        public int $expiredCouponsCount,
    ) {
    }

    /**
     * Create from query results.
     *
     * @param array<string, mixed>             $summary
     * @param array<int, array<string, mixed>> $topCoupons
     * @param array<int, array<string, mixed>> $unusedPromotions
     */
    public static function create(
        array $summary,
        array $topCoupons,
        array $unusedPromotions,
    ): self {
        return new self(
            totalCouponsUsed: (int) $summary['total_coupons_used'],
            totalDiscountAmount: (int) $summary['total_discount_amount'],
            totalDiscountCurrency: $summary['total_discount_currency'] ?? 'USD',
            averageDiscountPerOrder: (float) $summary['average_discount_per_order'],
            topCoupons: $topCoupons,
            unusedPromotions: $unusedPromotions,
            activeCouponsCount: (int) $summary['active_coupons_count'],
            expiredCouponsCount: (int) $summary['expired_coupons_count'],
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
            'summary' => [
                'totalCouponsUsed' => $this->totalCouponsUsed,
                'totalDiscount' => [
                    'amount' => $this->totalDiscountAmount,
                    'currency' => $this->totalDiscountCurrency,
                    'formatted' => number_format($this->totalDiscountAmount / 100, 2).' '.$this->totalDiscountCurrency,
                ],
                'averageDiscountPerOrder' => round($this->averageDiscountPerOrder, 2),
                'activeCouponsCount' => $this->activeCouponsCount,
                'expiredCouponsCount' => $this->expiredCouponsCount,
            ],
            'topCoupons' => array_map(fn (array $coupon) => [
                'code' => $coupon['coupon_code'],
                'name' => $coupon['promotion_name'],
                'usageCount' => (int) $coupon['usage_count'],
                'totalDiscount' => [
                    'amount' => (int) $coupon['total_discount'],
                    'currency' => $coupon['currency'] ?? 'USD',
                ],
            ], $this->topCoupons),
            'unusedPromotions' => array_map(fn (array $promo) => [
                'id' => $promo['id'],
                'name' => $promo['name'],
                'type' => $promo['type'],
                'couponCode' => $promo['coupon_code'] ?? null,
                'validFrom' => isset($promo['valid_from']) ? (new \DateTimeImmutable($promo['valid_from']))->format(\DateTimeInterface::ATOM) : null,
                'validTo' => isset($promo['valid_to']) ? (new \DateTimeImmutable($promo['valid_to']))->format(\DateTimeInterface::ATOM) : null,
            ], $this->unusedPromotions),
        ];
    }
}
