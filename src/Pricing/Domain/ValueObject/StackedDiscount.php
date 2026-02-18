<?php

declare(strict_types=1);

namespace App\Pricing\Domain\ValueObject;

use App\Shared\Domain\ValueObject\Money;

/**
 * Represents the result of stacking multiple promotions.
 *
 * This value object encapsulates the complete discount calculation result,
 * providing a type-safe way to pass stacking results throughout the application.
 */
final readonly class StackedDiscount
{
    /**
     * @param Money                 $originalPrice Price before any discounts
     * @param Money                 $finalPrice    Price after all discounts applied
     * @param Money                 $totalDiscount Total amount discounted
     * @param DiscountApplication[] $applications  Individual discount applications in order
     */
    public function __construct(
        private Money $originalPrice,
        private Money $finalPrice,
        private Money $totalDiscount,
        private array $applications,
    ) {
        if ($this->totalDiscount->isNegative()) {
            throw new \InvalidArgumentException('Total discount cannot be negative');
        }

        if ($this->finalPrice->isNegative()) {
            throw new \InvalidArgumentException('Final price cannot be negative');
        }

        if (!$this->originalPrice->subtract($this->totalDiscount)->equals($this->finalPrice)) {
            throw new \InvalidArgumentException('Discount calculation is inconsistent: originalPrice - totalDiscount must equal finalPrice');
        }
    }

    public function originalPrice(): Money
    {
        return $this->originalPrice;
    }

    public function finalPrice(): Money
    {
        return $this->finalPrice;
    }

    public function totalDiscount(): Money
    {
        return $this->totalDiscount;
    }

    /**
     * @return DiscountApplication[]
     */
    public function applications(): array
    {
        return $this->applications;
    }

    public function hasDiscounts(): bool
    {
        return !empty($this->applications);
    }

    public function discountCount(): int
    {
        return count($this->applications);
    }

    /**
     * Calculate the effective discount percentage.
     *
     * @return float Percentage (0-100)
     */
    public function effectiveDiscountPercentage(): float
    {
        if (0 === $this->originalPrice->getAmount()) {
            return 0.0;
        }

        return ($this->totalDiscount->getAmount() / $this->originalPrice->getAmount()) * 100;
    }

    /**
     * Convert to array for API responses or event payloads.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'originalPrice' => $this->originalPrice->toArray(),
            'finalPrice' => $this->finalPrice->toArray(),
            'totalDiscount' => $this->totalDiscount->toArray(),
            'effectiveDiscountPercentage' => round($this->effectiveDiscountPercentage(), 2),
            'applications' => array_map(
                static fn (DiscountApplication $app) => $app->toArray(),
                $this->applications
            ),
        ];
    }
}
