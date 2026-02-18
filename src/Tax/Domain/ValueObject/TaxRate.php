<?php

declare(strict_types=1);

namespace App\Tax\Domain\ValueObject;

/**
 * Tax Rate Value Object.
 *
 * Represents a tax rate as a percentage (0-100).
 * Examples: 19% VAT, 20% VAT, 5% reduced rate.
 */
final readonly class TaxRate
{
    private function __construct(
        private float $value,
    ) {
        if ($value < 0 || $value > 100) {
            throw new \InvalidArgumentException(sprintf('Tax rate must be between 0 and 100, got %.2f', $value));
        }
    }

    public static function fromPercentage(float $percentage): self
    {
        return new self($percentage);
    }

    public static function zero(): self
    {
        return new self(0.0);
    }

    /**
     * Get tax rate as percentage (e.g., 19.0 for 19%).
     */
    public function getPercentage(): float
    {
        return $this->value;
    }

    /**
     * Get tax rate as decimal (e.g., 0.19 for 19%).
     */
    public function getDecimal(): float
    {
        return $this->value / 100;
    }

    /**
     * Calculate tax amount for given price in cents.
     */
    public function calculateTaxAmount(int $priceInCents): int
    {
        return (int) round($priceInCents * $this->getDecimal());
    }

    /**
     * Calculate price including tax.
     */
    public function calculatePriceWithTax(int $priceInCents): int
    {
        return $priceInCents + $this->calculateTaxAmount($priceInCents);
    }

    public function equals(self $other): bool
    {
        return abs($this->value - $other->value) < 0.0001;
    }

    public function __toString(): string
    {
        return sprintf('%.2f%%', $this->value);
    }
}
