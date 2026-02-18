<?php

declare(strict_types=1);

namespace App\Customer\Domain\ValueObject;

use App\Shared\Domain\ValueObject\Money;

/**
 * Earning Rate Value Object.
 *
 * Represents the rate at which loyalty points are earned per currency unit.
 * Example: 1.0 = 1 point per $1 spent, 2.5 = 2.5 points per $1 spent
 *
 * Business Rules:
 * - Must be greater than 0 (minimum earning rate)
 * - Cannot exceed 100 (prevents abuse)
 */
final readonly class EarningRate
{
    private function __construct(
        private float $value,
    ) {
        if ($value <= 0) {
            throw new \InvalidArgumentException('Earning rate must be greater than 0');
        }

        if ($value > 100) {
            throw new \InvalidArgumentException('Earning rate cannot exceed 100 points per currency unit');
        }
    }

    public static function fromFloat(float $value): self
    {
        return new self($value);
    }

    public static function fromString(string $value): self
    {
        $floatValue = (float) $value;

        if ((string) $floatValue !== $value && number_format($floatValue, 2) !== $value) {
            throw new \InvalidArgumentException(sprintf('Invalid earning rate format: "%s"', $value));
        }

        return new self($floatValue);
    }

    /**
     * Calculate loyalty points earned for a given monetary amount.
     *
     * @return int Whole points earned (rounded down)
     */
    public function calculatePoints(Money $amount): int
    {
        $points = $amount->amount() * $this->value;

        return (int) floor($points);
    }

    public function toFloat(): float
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        // Use epsilon comparison for float equality
        return abs($this->value - $other->value) < 0.0001;
    }

    public function __toString(): string
    {
        return number_format($this->value, 2);
    }
}
