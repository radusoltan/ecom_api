<?php

declare(strict_types=1);

namespace App\Pricing\Application\DTO\Analytics;

/**
 * Promotion performance analytics DTO.
 *
 * Provides insights into promotion effectiveness:
 * - Revenue generated
 * - Orders using the promotion
 * - Conversion metrics
 * - Average discount given
 */
final readonly class PromotionPerformanceDTO
{
    public function __construct(
        public string $promotionId,
        public string $promotionName,
        public string $promotionType,
        public int $ordersCount,
        public int $totalRevenueAmount,
        public string $totalRevenueCurrency,
        public int $totalDiscountAmount,
        public string $totalDiscountCurrency,
        public float $averageDiscountAmount,
        public float $conversionRate,
        public ?\DateTimeImmutable $validFrom = null,
        public ?\DateTimeImmutable $validTo = null,
    ) {
    }

    /**
     * Create from database result.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            promotionId: $data['promotion_id'],
            promotionName: $data['promotion_name'],
            promotionType: $data['promotion_type'],
            ordersCount: (int) $data['orders_count'],
            totalRevenueAmount: (int) $data['total_revenue_amount'],
            totalRevenueCurrency: $data['total_revenue_currency'] ?? 'USD',
            totalDiscountAmount: (int) $data['total_discount_amount'],
            totalDiscountCurrency: $data['total_discount_currency'] ?? 'USD',
            averageDiscountAmount: (float) $data['average_discount_amount'],
            conversionRate: (float) $data['conversion_rate'],
            validFrom: isset($data['valid_from']) ? new \DateTimeImmutable($data['valid_from']) : null,
            validTo: isset($data['valid_to']) ? new \DateTimeImmutable($data['valid_to']) : null,
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
            'promotionId' => $this->promotionId,
            'promotionName' => $this->promotionName,
            'promotionType' => $this->promotionType,
            'ordersCount' => $this->ordersCount,
            'totalRevenue' => [
                'amount' => $this->totalRevenueAmount,
                'currency' => $this->totalRevenueCurrency,
                'formatted' => number_format($this->totalRevenueAmount / 100, 2).' '.$this->totalRevenueCurrency,
            ],
            'totalDiscount' => [
                'amount' => $this->totalDiscountAmount,
                'currency' => $this->totalDiscountCurrency,
                'formatted' => number_format($this->totalDiscountAmount / 100, 2).' '.$this->totalDiscountCurrency,
            ],
            'averageDiscountAmount' => round($this->averageDiscountAmount, 2),
            'conversionRate' => round($this->conversionRate, 2),
            'validFrom' => $this->validFrom?->format(\DateTimeInterface::ATOM),
            'validTo' => $this->validTo?->format(\DateTimeInterface::ATOM),
        ];
    }
}
