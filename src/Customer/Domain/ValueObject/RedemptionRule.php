<?php

declare(strict_types=1);

namespace App\Customer\Domain\ValueObject;

use App\Shared\Domain\ValueObject\Money;

/**
 * Redemption Rule Value Object.
 *
 * Defines rules for redeeming loyalty points as discount.
 *
 * Business Rules:
 * - Conversion rate must be greater than 0 (e.g., 100 points = $1 means conversionRate = 100)
 * - Minimum points to redeem must be >= 0
 * - Maximum points per order can be null (unlimited) or a positive integer
 */
final readonly class RedemptionRule
{
    private function __construct(
        private float $conversionRate,
        private int $minPointsToRedeem,
        private ?int $maxPointsPerOrder,
    ) {
        if ($conversionRate <= 0) {
            throw new \InvalidArgumentException('Conversion rate must be greater than 0');
        }

        if ($minPointsToRedeem < 0) {
            throw new \InvalidArgumentException('Minimum points to redeem must be greater than or equal to 0');
        }

        if (null !== $maxPointsPerOrder && $maxPointsPerOrder <= 0) {
            throw new \InvalidArgumentException('Maximum points per order must be greater than 0 or null');
        }

        if (null !== $maxPointsPerOrder && $maxPointsPerOrder < $minPointsToRedeem) {
            throw new \InvalidArgumentException('Maximum points per order cannot be less than minimum points to redeem');
        }
    }

    public static function create(
        float $conversionRate,
        int $minPointsToRedeem,
        ?int $maxPointsPerOrder = null,
    ): self {
        return new self($conversionRate, $minPointsToRedeem, $maxPointsPerOrder);
    }

    /**
     * Calculate discount amount for given points.
     *
     * @param int $points Number of points to redeem
     *
     * @return Money Discount amount in USD
     */
    public function calculateDiscount(int $points): Money
    {
        if ($points < $this->minPointsToRedeem) {
            throw new \InvalidArgumentException(sprintf('Points to redeem (%d) must be at least %d', $points, $this->minPointsToRedeem));
        }

        if (null !== $this->maxPointsPerOrder && $points > $this->maxPointsPerOrder) {
            throw new \InvalidArgumentException(sprintf('Points to redeem (%d) cannot exceed maximum of %d per order', $points, $this->maxPointsPerOrder));
        }

        // Calculate discount: points / conversionRate = dollar amount
        // Example: 100 points / 100 conversion rate = $1.00
        $discountAmount = $points / $this->conversionRate;

        // Convert to Money object (USD)
        return Money::of((string) $discountAmount, 'USD');
    }

    public function conversionRate(): float
    {
        return $this->conversionRate;
    }

    public function minPointsToRedeem(): int
    {
        return $this->minPointsToRedeem;
    }

    public function maxPointsPerOrder(): ?int
    {
        return $this->maxPointsPerOrder;
    }

    /**
     * @return array<string, float|int|null>
     */
    public function toArray(): array
    {
        return [
            'conversionRate' => $this->conversionRate,
            'minPointsToRedeem' => $this->minPointsToRedeem,
            'maxPointsPerOrder' => $this->maxPointsPerOrder,
        ];
    }

    public function equals(self $other): bool
    {
        return abs($this->conversionRate - $other->conversionRate) < 0.0001
            && $this->minPointsToRedeem === $other->minPointsToRedeem
            && $this->maxPointsPerOrder === $other->maxPointsPerOrder;
    }
}
