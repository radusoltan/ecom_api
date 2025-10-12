<?php

declare(strict_types=1);

namespace App\Payment\Domain\ValueObject;

/**
 * Payment Method Value Object
 *
 * Supported payment methods:
 * - card: Credit/debit card payments
 * - paypal: PayPal account payments
 * - bank_transfer: Direct bank transfers
 */
final readonly class PaymentMethod
{
    private const CARD = 'card';
    private const PAYPAL = 'paypal';
    private const BANK_TRANSFER = 'bank_transfer';

    private const VALID_METHODS = [
        self::CARD,
        self::PAYPAL,
        self::BANK_TRANSFER,
    ];

    private function __construct(
        private string $value
    ) {
        if (!in_array($value, self::VALID_METHODS, true)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Invalid payment method: "%s". Allowed: %s',
                    $value,
                    implode(', ', self::VALID_METHODS)
                )
            );
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
        return $this->value === self::CARD;
    }

    public function isPaypal(): bool
    {
        return $this->value === self::PAYPAL;
    }

    public function isBankTransfer(): bool
    {
        return $this->value === self::BANK_TRANSFER;
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
