<?php

declare(strict_types=1);

namespace App\Payment\Domain\ValueObject;

/**
 * Payment Method Value Object.
 *
 * Supported payment methods:
 * - card: Credit/debit card payments
 * - paypal: PayPal account payments
 * - bank_transfer: Direct bank transfers
 * - apple_pay: Apple Pay payments
 * - google_pay: Google Pay payments
 */
final readonly class PaymentMethod
{
    private const CARD = 'card';
    private const PAYPAL = 'paypal';
    private const BANK_TRANSFER = 'bank_transfer';
    private const APPLE_PAY = 'apple_pay';
    private const GOOGLE_PAY = 'google_pay';

    private const VALID_METHODS = [
        self::CARD,
        self::PAYPAL,
        self::BANK_TRANSFER,
        self::APPLE_PAY,
        self::GOOGLE_PAY,
    ];

    private function __construct(
        private string $value
    ) {
        if (!in_array($value, self::VALID_METHODS, true)) {
            throw new \InvalidArgumentException(sprintf('Invalid payment method: "%s". Allowed: %s', $value, implode(', ', self::VALID_METHODS)));
        }
    }

    public static function card(): self
    {
        return new self(self::CARD);
    }

    public static function paypal(): self
    {
        return new self(self::PAYPAL);
    }

    public static function bankTransfer(): self
    {
        return new self(self::BANK_TRANSFER);
    }

    public static function applePay(): self
    {
        return new self(self::APPLE_PAY);
    }

    public static function googlePay(): self
    {
        return new self(self::GOOGLE_PAY);
    }

    public static function fromString(string $value): self
    {
        return new self(strtolower($value));
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isCard(): bool
    {
        return self::CARD === $this->value;
    }

    public function isPaypal(): bool
    {
        return self::PAYPAL === $this->value;
    }

    public function isBankTransfer(): bool
    {
        return self::BANK_TRANSFER === $this->value;
    }

    public function isApplePay(): bool
    {
        return self::APPLE_PAY === $this->value;
    }

    public function isGooglePay(): bool
    {
        return self::GOOGLE_PAY === $this->value;
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
