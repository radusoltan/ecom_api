<?php

declare(strict_types=1);

namespace App\Payment\Domain\Model;

/**
 * Transaction Status Enum.
 *
 * Represents the outcome of a payment transaction operation.
 *
 * Business Rules:
 * - SUCCESS: Transaction completed successfully
 * - FAILED: Transaction failed (permanent failure)
 * - PENDING: Transaction is awaiting confirmation from payment gateway
 * - Immutable once set (transactions are never modified)
 *
 * @see Transaction
 */
enum TransactionStatus: string
{
    case SUCCESS = 'success';
    case FAILED = 'failed';
    case PENDING = 'pending';

    /**
     * Check if the transaction was successful.
     */
    public function isSuccessful(): bool
    {
        return self::SUCCESS === $this;
    }

    /**
     * Check if the transaction failed.
     */
    public function isFailed(): bool
    {
        return self::FAILED === $this;
    }

    /**
     * Check if the transaction is still pending.
     */
    public function isPending(): bool
    {
        return self::PENDING === $this;
    }

    /**
     * Check if the transaction is in a final state (success or failed).
     */
    public function isFinal(): bool
    {
        return $this->isSuccessful() || $this->isFailed();
    }
}
