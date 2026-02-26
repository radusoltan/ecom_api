<?php

declare(strict_types=1);

namespace App\Shipping\Domain\ValueObject;

final readonly class CarrierCode implements \Stringable
{
    public const UPS = 'ups';
    public const FEDEX = 'fedex';
    public const DHL = 'dhl';
    public const USPS = 'usps';
    public const DPD = 'dpd';
    public const GLS = 'gls';
    public const MOCK = 'mock';

    private const VALID_CARRIERS = [
        self::UPS,
        self::FEDEX,
        self::DHL,
        self::USPS,
        self::DPD,
        self::GLS,
        self::MOCK,
    ];

    private function __construct(private string $value)
    {
        if (!in_array($value, self::VALID_CARRIERS, true)) {
            throw new \InvalidArgumentException(sprintf('Invalid carrier code: "%s". Valid carriers are: %s', $value, implode(', ', self::VALID_CARRIERS)));
        }
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public static function ups(): self
    {
        return new self(self::UPS);
    }

    public static function fedex(): self
    {
        return new self(self::FEDEX);
    }

    public static function dhl(): self
    {
        return new self(self::DHL);
    }

    public static function usps(): self
    {
        return new self(self::USPS);
    }

    public static function dpd(): self
    {
        return new self(self::DPD);
    }

    public static function gls(): self
    {
        return new self(self::GLS);
    }

    public static function mock(): self
    {
        return new self(self::MOCK);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
