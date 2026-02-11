<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Model;

use App\Pricing\Domain\ValueObject\DiscountType;
use App\Shared\Domain\ValueObject\Money;

/**
 * Value Object representing a discount (percentage or fixed amount).
 *
 * Business Rules:
 * - type: 'percentage' or 'fixed'
 * - percentage: 0-100
 * - fixed_amount: > 0
 * - cannot_be_negative: true
 */
final readonly class Discount
{
    public const TYPE_PERCENTAGE = 'percentage';
    public const TYPE_FIXED = 'fixed';

    private function __construct(
        private string $type,
        private ?float $percentage,
        private ?Money $fixedAmount
    ) {
        if (!in_array($this->type, [self::TYPE_PERCENTAGE, self::TYPE_FIXED], true)) {
            throw new \InvalidArgumentException(sprintf('Invalid discount type "%s". Must be "%s" or "%s"', $this->type, self::TYPE_PERCENTAGE, self::TYPE_FIXED));
        }

        if (self::TYPE_PERCENTAGE === $this->type) {
            if (null === $this->percentage) {
                throw new \InvalidArgumentException('Percentage discount requires a percentage value');
            }

            if ($this->percentage < 0 || $this->percentage > 100) {
                throw new \InvalidArgumentException(sprintf('Discount percentage must be between 0 and 100, got %.2f', $this->percentage));
            }

            if (null !== $this->fixedAmount) {
                throw new \InvalidArgumentException('Percentage discount cannot have a fixed amount');
            }
        }

        if (self::TYPE_FIXED === $this->type) {
            if (null === $this->fixedAmount) {
                throw new \InvalidArgumentException('Fixed discount requires a fixed amount');
            }

            if ($this->fixedAmount->isNegative()) {
                throw new \InvalidArgumentException('Fixed discount amount cannot be negative');
            }

            if (null !== $this->percentage) {
                throw new \InvalidArgumentException('Fixed discount cannot have a percentage');
            }
        }
    }

    public static function percentage(float $percentage): self
    {
        return new self(self::TYPE_PERCENTAGE, $percentage, null);
    }

    public static function fixed(Money $amount): self
    {
        return new self(self::TYPE_FIXED, null, $amount);
    }

    public static function fromTypeAndValue(string $type, float $value): self
    {
        if (self::TYPE_PERCENTAGE === $type) {
            return self::percentage($value);
        }

        if (self::TYPE_FIXED === $type) {
            // For fixed discounts, value is stored in minor units (cents)
            return self::fixed(Money::fromScalars((int) $value, 'USD')); // TODO: Make currency configurable
        }

        throw new \InvalidArgumentException(sprintf('Invalid discount type "%s"', $type));
    }

    public function value(): float
    {
        if ($this->isPercentage()) {
            return $this->percentage;
        }

        return (float) $this->fixedAmount->getAmount();
    }

    public function type(): DiscountType
    {
        if ($this->isPercentage()) {
            return DiscountType::percentage();
        }

        return DiscountType::fixedAmount();
    }

    public function isPercentage(): bool
    {
        return self::TYPE_PERCENTAGE === $this->type;
    }

    public function isFixed(): bool
    {
        return self::TYPE_FIXED === $this->type;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getPercentage(): ?float
    {
        return $this->percentage;
    }

    public function getFixedAmount(): ?Money
    {
        return $this->fixedAmount;
    }

    /**
     * Calculate discount amount for a given price.
     */
    public function calculateDiscountAmount(Money $price): Money
    {
        if ($this->isPercentage()) {
            return $price->multiplyBy($this->percentage / 100);
        }

        // Fixed discount: return the fixed amount (but not more than the price)
        if ($this->fixedAmount->isGreaterThan($price)) {
            return $price; // Discount cannot exceed price
        }

        return $this->fixedAmount;
    }

    /**
     * Apply discount to a price and return final price.
     */
    public function apply(Money $price): Money
    {
        $discountAmount = $this->calculateDiscountAmount($price);

        return $price->subtract($discountAmount);
    }

    public function equals(self $other): bool
    {
        if ($this->type !== $other->type) {
            return false;
        }

        if ($this->isPercentage()) {
            return abs($this->percentage - $other->percentage) < 0.001; // Float comparison
        }

        return $this->fixedAmount->equals($other->fixedAmount);
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'percentage' => $this->percentage,
            'fixed_amount' => $this->fixedAmount?->toArray(),
        ];
    }
}
