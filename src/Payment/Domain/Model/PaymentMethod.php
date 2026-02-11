<?php

declare(strict_types=1);

namespace App\Payment\Domain\Model;

/**
 * Payment Method Value Object.
 *
 * Business Rules:
 * - Supported methods: STRIPE, PAYPAL, BANK_TRANSFER, APPLE_PAY, GOOGLE_PAY
 * - Card methods (STRIPE, APPLE_PAY, GOOGLE_PAY) process immediately via Stripe
 * - Redirect methods (PAYPAL) require external flow
 * - Bank transfers require manual verification
 * - Apple Pay and Google Pay use Stripe SDK (no separate gateway needed)
 */
enum PaymentMethod: string
{
    case STRIPE = 'stripe';
    case PAYPAL = 'paypal';
    case BANK_TRANSFER = 'bank_transfer';
    case APPLE_PAY = 'apple_pay';
    case GOOGLE_PAY = 'google_pay';

    /**
     * Check if this payment method is a card-based payment.
     *
     * @return bool True if the method processes card payments directly
     */
    public function isCard(): bool
    {
        return match ($this) {
            self::STRIPE, self::APPLE_PAY, self::GOOGLE_PAY => true,
            default => false,
        };
    }

    /**
     * Check if this payment method requires external redirect.
     *
     * @return bool True if the method requires redirecting to external gateway
     */
    public function requiresRedirect(): bool
    {
        return self::PAYPAL === $this;
    }

    /**
     * Check if this payment method requires manual verification.
     *
     * @return bool True if the method requires manual processing
     */
    public function requiresManualVerification(): bool
    {
        return self::BANK_TRANSFER === $this;
    }

    /**
     * Get human-readable description of the payment method.
     */
    public function description(): string
    {
        return match ($this) {
            self::STRIPE => 'Credit/Debit Card (Stripe)',
            self::PAYPAL => 'PayPal',
            self::BANK_TRANSFER => 'Bank Transfer',
            self::APPLE_PAY => 'Apple Pay',
            self::GOOGLE_PAY => 'Google Pay',
        };
    }

    /**
     * Get the value as string.
     */
    public function value(): string
    {
        return $this->value;
    }
}
