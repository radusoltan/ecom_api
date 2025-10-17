<?php

declare(strict_types=1);

namespace App\Payment\Domain\ValueObject;

/**
 * Payment Gateway Value Object
 *
 * Supported payment gateways:
 * - stripe: Stripe payment processing
 * - paypal: PayPal payment processing
 * - twocheckout: 2Checkout payment processing
 */
final readonly class PaymentGateway
{
    private const STRIPE = 'stripe';
    private const PAYPAL = 'paypal';
    private const TWOCHECKOUT = 'twocheckout';

    private const VALID_GATEWAYS = [
        self::STRIPE,
        self::PAYPAL,
        self::TWOCHECKOUT,
    ];

    private function __construct(
        private string $value
    ) {
        if (!in_array($value, self::VALID_GATEWAYS, true)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Invalid payment gateway: "%s". Allowed: %s',
                    $value,
                    implode(', ', self::VALID_GATEWAYS)
                )
            );
        }
    }

    public static function stripe(): self
    {
        return new self(self::STRIPE);
    }

    public static function paypal(): self
    {
        return new self(self::PAYPAL);
    }

    public static function twoCheckout(): self
    {
        return new self(self::TWOCHECKOUT);
    }

    public static function fromString(string $value): self
    {
        return new self(strtolower($value));
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isStripe(): bool
    {
        return $this->value === self::STRIPE;
    }

    public function isPaypal(): bool
    {
        return $this->value === self::PAYPAL;
    }

    public function isTwoCheckout(): bool
    {
        return $this->value === self::TWOCHECKOUT;
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
