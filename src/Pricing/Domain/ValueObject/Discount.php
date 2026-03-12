<?php

declare(strict_types=1);

namespace App\Pricing\Domain\ValueObject;

use App\Shared\Domain\ValueObject\Money;

/**
 * Discount value object.
 * Can represent either percentage or fixed amount discount.
 *
 * Fixed amounts use Money (brick/money) to avoid float precision issues.
 */
final readonly class Discount
{
    private function __construct(
        private DiscountType $type,
        private ?float $percentageValue,
        private ?Money $moneyValue,
    ) {
        if ($this->type->isPercentage()) {
            if (null === $this->percentageValue || $this->percentageValue < 0.01 || $this->percentageValue > 100) {
                throw new \InvalidArgumentException(sprintf('Percentage discount must be between 0.01 and 100, got: %.2f', $this->percentageValue ?? 0));
            }
        } else {
            if (null === $this->moneyValue || $this->moneyValue->getAmount() < 1) {
                throw new \InvalidArgumentException('Fixed amount discount must be greater than 0');
            }
        }
    }

    public static function percentage(float $percentage): self
    {
        return new self(DiscountType::percentage(), $percentage, null);
    }

    /**
     * Create a fixed amount discount from minor units (cents).
     */
    public static function fixedAmount(int $amountInMinorUnits, string $currencyCode = 'EUR'): self
    {
        return new self(DiscountType::fixedAmount(), null, Money::fromScalars($amountInMinorUnits, $currencyCode));
    }

    /**
     * Create a fixed amount discount from a Money value object.
     */
    public static function fixedAmountFromMoney(Money $amount): self
    {
        return new self(DiscountType::fixedAmount(), null, $amount);
    }

    /**
     * Create from type string and value (DB/API layer compatibility).
     *
     * For percentage: value is the percentage (e.g. 10.0 = 10%).
     * For fixed_amount: value is in major units (e.g. 9.99 = €9.99).
     * Internally converts to Money using string precision.
     */
    public static function fromTypeAndValue(string $type, float $value, string $currencyCode = 'EUR'): self
    {
        $discountType = DiscountType::fromString($type);

        if ($discountType->isPercentage()) {
            return self::percentage($value);
        }

        // Convert major units to Money using string to preserve precision
        return self::fixedAmountFromMoney(
            Money::of(number_format($value, 2, '.', ''), $currencyCode)
        );
    }

    public function type(): DiscountType
    {
        return $this->type;
    }

    /**
     * Get the numeric value for storage/serialization.
     * For percentage: returns the percentage (e.g. 10.0).
     * For fixed_amount: returns the amount in major units (e.g. 9.99) for DB compatibility.
     */
    public function value(): float
    {
        if ($this->type->isPercentage()) {
            /** @var float $percentageValue guaranteed non-null for percentage type */
            $percentageValue = $this->percentageValue;

            return $percentageValue;
        }

        /** @var Money $money guaranteed non-null for fixed_amount type */
        $money = $this->moneyValue;
        $scale = $money->getCurrency()->getDefaultFractionDigits();

        return $money->getAmount() / (10 ** $scale);
    }

    /**
     * Get the Money value for fixed amount discounts.
     */
    public function getMoneyValue(): ?Money
    {
        return $this->moneyValue;
    }

    /**
     * Apply discount to a price and return the discount amount.
     */
    public function applyTo(Money $price): Money
    {
        if ($this->type->isPercentage()) {
            /** @var float $pct */
            $pct = $this->percentageValue;
            $discountAmountInMinorUnits = (int) ($price->getAmount() * ($pct / 100));

            return Money::fromScalars($discountAmountInMinorUnits, $price->getCurrency()->getCurrencyCode());
        }

        /** @var Money $fixedMoney guaranteed non-null for fixed_amount type */
        $fixedMoney = $this->moneyValue;

        // Fixed amount discount — ensure discount doesn't exceed price
        if ($fixedMoney->isGreaterThan($price)) {
            return $price;
        }

        return $fixedMoney;
    }

    public function equals(self $other): bool
    {
        if (!$this->type->equals($other->type)) {
            return false;
        }

        if ($this->type->isPercentage()) {
            return abs(($this->percentageValue ?? 0) - ($other->percentageValue ?? 0)) < 0.001;
        }

        /** @var Money $thisMoney */
        $thisMoney = $this->moneyValue;
        /** @var Money $otherMoney */
        $otherMoney = $other->moneyValue;

        return $thisMoney->equals($otherMoney);
    }
}
