<?php

declare(strict_types=1);

namespace App\Payment\Domain\Model;

/**
 * Transaction Type Value Object.
 *
 * Business Rules:
 * - AUTHORIZATION: Reserve funds without capturing
 * - CAPTURE: Capture previously authorized funds
 * - REFUND: Return funds to customer
 * - VOID: Cancel authorization before capture
 * - CHARGEBACK: Customer-initiated dispute
 * - Affects balance calculation for payment reconciliation
 */
enum TransactionType: string
{
    case AUTHORIZATION = 'authorization';
    case CAPTURE = 'capture';
    case REFUND = 'refund';
    case VOID = 'void';
    case CHARGEBACK = 'chargeback';

    /**
     * Check if this transaction type affects the merchant's balance.
     *
     * @return bool True if transaction affects balance (CAPTURE increases, REFUND/CHARGEBACK decrease)
     */
    public function affectsBalance(): bool
    {
        return match ($this) {
            self::AUTHORIZATION => false, // Only reserves funds
            self::CAPTURE => true,        // Adds to balance
            self::REFUND => true,         // Subtracts from balance
            self::VOID => false,          // Cancels reservation
            self::CHARGEBACK => true,     // Subtracts from balance
        };
    }

    /**
     * Get the balance impact multiplier.
     *
     * @return int 1 for positive impact (CAPTURE), -1 for negative impact (REFUND, CHARGEBACK), 0 for no impact
     */
    public function balanceMultiplier(): int
    {
        return match ($this) {
            self::AUTHORIZATION => 0,
            self::CAPTURE => 1,
            self::REFUND => -1,
            self::VOID => 0,
            self::CHARGEBACK => -1,
        };
    }

    /**
     * Get human-readable description of the transaction type.
     */
    public function description(): string
    {
        return match ($this) {
            self::AUTHORIZATION => 'Authorization (funds reserved)',
            self::CAPTURE => 'Capture (funds collected)',
            self::REFUND => 'Refund (funds returned)',
            self::VOID => 'Void (authorization cancelled)',
            self::CHARGEBACK => 'Chargeback (customer dispute)',
        };
    }

    /**
     * Check if this transaction type is an authorization.
     */
    public function isAuthorization(): bool
    {
        return self::AUTHORIZATION === $this;
    }

    /**
     * Check if this transaction type is a capture.
     */
    public function isCapture(): bool
    {
        return self::CAPTURE === $this;
    }

    /**
     * Check if this transaction type is a refund.
     */
    public function isRefund(): bool
    {
        return self::REFUND === $this;
    }

    /**
     * Check if this transaction type is a void.
     */
    public function isVoid(): bool
    {
        return self::VOID === $this;
    }

    /**
     * Check if this transaction type is a chargeback.
     */
    public function isChargeback(): bool
    {
        return self::CHARGEBACK === $this;
    }

    /**
     * Check if this transaction type reverses a previous transaction.
     * Refunds and chargebacks reverse captured funds.
     */
    public function isReversing(): bool
    {
        return match ($this) {
            self::REFUND, self::CHARGEBACK => true,
            default => false,
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
