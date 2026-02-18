<?php

declare(strict_types=1);

namespace App\Tax\Domain\Model;

/**
 * Tax Rate value object representing a percentage (0-100).
 *
 * Encapsulates tax rate calculations with precise decimal handling.
 *
 * Business Rules:
 * - Rate must be between 0.00 and 100.00
 * - Precision: 2 decimal places
 * - Stored as decimal (e.g., 19.00 for 19%)
 * - Immutable once created
 *
 * Examples:
 * - Standard VAT DE: 19.00%
 * - Reduced VAT DE: 7.00%
 * - Standard VAT FR: 20.00%
 * - Zero rate: 0.00%
 */
final readonly class TaxRate
{
    private function __construct(
        private float $rate,
    ) {
        if ($rate < 0 || $rate > 100) {
            throw new \InvalidArgumentException(sprintf('Tax rate must be between 0 and 100, got %s', $rate));
        }
    }

    public static function fromPercentage(float $percentage): self
    {
        return new self(round($percentage, 2));
    }

    public static function zero(): self
    {
        return new self(0.0);
    }

    public static function standard(float $rate): self
    {
        return new self($rate);
    }

    public function percentage(): float
    {
        return $this->rate;
    }

    public function getPercentage(): float
    {
        return $this->rate;
    }

    public function multiplier(): float
    {
        return $this->rate / 100;
    }

    public function calculateTax(int $amountInCents): int
    {
        return (int) round($amountInCents * $this->multiplier());
    }

    public function isZero(): bool
    {
        return 0.0 === $this->rate;
    }

    public function equals(self $other): bool
    {
        return abs($this->rate - $other->rate) < 0.001;
    }

    public function toString(): string
    {
        return number_format($this->rate, 2).'%';
    }
}
