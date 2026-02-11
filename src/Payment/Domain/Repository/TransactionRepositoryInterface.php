<?php

declare(strict_types=1);

namespace App\Payment\Domain\Repository;

use App\Payment\Domain\Model\Transaction;
use App\Payment\Domain\Model\TransactionId;
use App\Payment\Domain\Model\PaymentId;

/**
 * Transaction Repository Interface (Port).
 *
 * Defines persistence operations for the Transaction entity.
 * Transactions are immutable audit logs of payment operations.
 *
 * This is a port in the hexagonal architecture - implementations are adapters.
 *
 * Design Principles:
 * - Transactions are write-only (no update operations)
 * - Transactions serve as an immutable audit trail
 * - Each transaction records a single payment gateway interaction
 * - Transactions are always linked to a parent Payment aggregate
 *
 * @see Transaction
 * @see Payment
 */
interface TransactionRepositoryInterface
{
    /**
     * Save a transaction.
     *
     * Transactions are immutable - this method only inserts, never updates.
     * Once created, a transaction cannot be modified.
     *
     * @param Transaction $transaction The transaction entity to persist
     */
    public function save(Transaction $transaction): void;

    /**
     * Find transaction by ID.
     *
     * Returns null if not found.
     *
     * @param TransactionId $id The transaction identifier
     *
     * @return Transaction|null The transaction entity or null if not found
     */
    public function findById(TransactionId $id): ?Transaction;

    /**
     * Get all transactions for a payment (audit log).
     *
     * Returns the complete transaction history for a payment, ordered by creation date
     * ascending (oldest first). This provides a chronological audit trail of all
     * payment gateway interactions.
     *
     * Use Cases:
     * - Display transaction history in admin panel
     * - Audit payment operations
     * - Reconciliation with payment gateway
     * - Debugging payment issues
     *
     * @param PaymentId $paymentId The payment identifier
     *
     * @return Transaction[] Array of transaction entities (empty array if none found)
     */
    public function findByPaymentId(PaymentId $paymentId): array;

    /**
     * Find transaction by gateway transaction ID.
     *
     * Used for webhook processing and reconciliation with payment gateway.
     * Gateway transaction IDs are unique identifiers from the payment processor
     * (e.g., Stripe charge ID, PayPal transaction ID).
     *
     * Use Cases:
     * - Process webhook notifications from payment gateway
     * - Reconcile transactions with gateway statements
     * - Handle duplicate webhook deliveries (idempotency)
     *
     * @param string $gatewayTransactionId The transaction ID from the payment gateway
     *
     * @return Transaction|null The transaction entity or null if not found
     */
    public function findByGatewayTransactionId(string $gatewayTransactionId): ?Transaction;
}
