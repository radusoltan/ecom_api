<?php

declare(strict_types=1);

namespace App\Pricing\Domain\ValueObject;

use App\Shared\Domain\ValueObject\Money;

/**
 * Discount value object.
 * Can represent either percentage or fixed amount discount.
 */
final readonly class Discount
{
    private function __construct(
        private DiscountType $type,
        private float $value,
    ) {
        if ($this->type->isPercentage()) {
            if ($this->value < 0.01 || $this->value > 100) {
                throw new \InvalidArgumentException(sprintf('Percentage discount must be between 0.01 and 100, got: %.2f', $this->value));
            }
        } else {
            if ($this->value < 0.01) {
                throw new \InvalidArgumentException(sprintf('Fixed amount discount must be greater than 0.01, got: %.2f', $this->value));
            }
        }
    }

    public static function percentage(float $percentage): self
    {
        return new self(DiscountType::percentage(), $percentage);
    }

    public static function fixedAmount(float $amount): self
    {
        return new self(DiscountType::fixedAmount(), $amount);
    }

    public static function fromTypeAndValue(string $type, float $value): self
    {
        return new self(DiscountType::fromString($type), $value);
    }

    public function type(): DiscountType
    {
        return $this->type;
    }

    public function value(): float
    {
        return $this->value;
    }

    /**
     * Apply discount to a price and return the discount amount.
     */
    public function applyTo(Money $price): Money
    {
        if ($this->type->isPercentage()) {
            $discountAmountInMinorUnits = (int) ($price->getAmount() * ($this->value / 100));

            return Money::fromScalars($discountAmountInMinorUnits, $price->getCurrency()->getCurrencyCode());
        }

        // Fixed amount discount
        $discountMoney = Money::of((string) $this->value, $price->getCurrency()->getCurrencyCode());

        // Ensure discount doesn't exceed price
        if ($discountMoney->isGreaterThan($price)) {
            return $price;
        }

        return $discountMoney;
    }

    public function equals(self $other): bool
    {
        return $this->type->equals($other->type) && abs($this->value - $other->value) < 0.001;
    }
}
