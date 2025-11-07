<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use Brick\Money\Currency;
use Brick\Money\Money as BrickMoney;

final readonly class Money
{
    private function __construct(
        private BrickMoney $money
    ) {
    }

    public static function fromScalars(int $amount, string $currencyCode): self
    {
        return new self(
            BrickMoney::ofMinor($amount, $currencyCode)
        );
    }

    public static function of(string $amount, string $currencyCode): self
    {
        return new self(
            BrickMoney::of($amount, $currencyCode)
        );
    }

    public function getAmount(): int
    {
        return $this->money->getMinorAmount()->toInt();
    }

    public function getCurrency(): Currency
    {
        return $this->money->getCurrency();
    }

    public function add(Money $other): self
    {
        return new self($this->money->plus($other->money));
    }

    public function subtract(Money $other): self
    {
        return new self($this->money->minus($other->money));
    }

    public function multiply(int|float|string $multiplier): self
    {
        return new self($this->money->multipliedBy($multiplier));
    }

    public function isGreaterThan(Money $other): bool
    {
        return $this->money->isGreaterThan($other->money);
    }

    public function isLessThan(Money $other): bool
    {
        return $this->money->isLessThan($other->money);
    }

    public function equals(Money $other): bool
    {
        return $this->money->isEqualTo($other->money);
    }

    public function isNegative(): bool
    {
        return $this->money->isNegative();
    }

    public function multiplyBy(int|float|string $multiplier): self
    {
        return $this->multiply($multiplier);
    }

    public function toArray(): array
    {
        return [
            'amount' => (string) $this->money->getMinorAmount(),
            'currency' => $this->money->getCurrency()->getCurrencyCode(),
        ];
    }

    public function __toString(): string
    {
        return $this->money->formatTo('en_US');
    }
}
