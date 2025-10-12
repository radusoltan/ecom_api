<?php

declare(strict_types=1);

namespace App\Pricing\Domain\ValueObject;

/**
 * Discount types:
 * - percentage: Percentage discount (e.g., 10%)
 * - fixed_amount: Fixed amount discount (e.g., $5 off)
 */
final readonly class DiscountType
{
    public const PERCENTAGE = 'percentage';
    public const FIXED_AMOUNT = 'fixed_amount';

    private const VALID_TYPES = [
        self::PERCENTAGE,
        self::FIXED_AMOUNT,
    ];

    private function __construct(private string $value)
    {
        if (!in_array($this->value, self::VALID_TYPES, true)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Invalid DiscountType: "%s". Must be one of: %s',
                    $this->value,
                    implode(', ', self::VALID_TYPES)
                )
            );
        }
    }

    public static function percentage(): self
    {
        return new self(self::PERCENTAGE);
    }

    public static function fixedAmount(): self
    {
        return new self(self::FIXED_AMOUNT);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function isPercentage(): bool
    {
        return $this->value === self::PERCENTAGE;
    }

    public function isFixedAmount(): bool
    {
        return $this->value === self::FIXED_AMOUNT;
    }
}
