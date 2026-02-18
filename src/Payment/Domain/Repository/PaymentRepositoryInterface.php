<?php

declare(strict_types=1);

namespace App\Payment\Domain\Repository;

use App\Payment\Domain\Model\Payment;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Payment Repository Interface (Port).
 *
 * Defines persistence operations for the Payment aggregate.
 * This is a port in the hexagonal architecture - implementations are adapters.
 *
 * Multi-tenancy: All operations respect tenant boundaries via TenantId.
 * The repository implementation MUST dispatch domain events after save().
 *
 * @see Payment
 */
interface PaymentRepositoryInterface
{
    /**
     * Save a payment (insert or update).
     *
     * MUST dispatch domain events recorded in the aggregate after persistence.
     *
     * @param Payment $payment The payment aggregate to persist
     */
    public function save(Payment $payment): void;

    /**
     * Find payment by ID.
     *
     * Returns null if not found or belongs to different tenant.
     *
     * @param PaymentId $id The payment identifier
     *
     * @return Payment|null The payment aggregate or null if not found
     */
    public function findById(PaymentId $id): ?Payment;

    /**
     * Find payment by order ID.
     *
     * Returns the first payment found for the order.
     * For multiple payments, use findAllByOrderId().
     *
     * @param string $orderId The order identifier (UUID string)
     *
     * @return Payment|null The payment aggregate or null if not found
     */
    public function findByOrderId(string $orderId): ?Payment;

    /**
     * Find payment by gateway payment intent ID.
     *
     * Used for webhook processing and reconciliation with payment gateway.
     *
     * @param string $gatewayPaymentId The payment intent ID from the gateway
     *
     * @return Payment|null The payment aggregate or null if not found
     */
    public function findByGatewayPaymentId(string $gatewayPaymentId): ?Payment;

    /**
     * Find payment by idempotency key (for duplicate prevention).
     *
     * Idempotency keys prevent duplicate payment attempts for the same operation.
     * Typically used in retry scenarios or concurrent requests.
     *
     * @param string   $idempotencyKey Unique key for this payment operation
     * @param TenantId $tenantId       Tenant context
     *
     * @return Payment|null The payment aggregate or null if not found
     */
    public function findByIdempotencyKey(string $idempotencyKey, TenantId $tenantId): ?Payment;

    /**
     * Get all payments for an order (supports multiple payment attempts).
     *
     * An order may have multiple payment attempts (failed payments, partial payments, etc.).
     * Returns all payments ordered by creation date descending (newest first).
     *
     * @param string $orderId The order identifier (UUID string)
     *
     * @return Payment[] Array of payment aggregates (empty array if none found)
     */
    public function findAllByOrderId(string $orderId): array;

    /**
     * Get all payments for the current tenant context.
     *
     * Returns all payments ordered by creation date descending.
     * Tenant isolation is enforced via RLS / TenantId parameter.
     *
     * @param TenantId $tenantId Tenant context
     *
     * @return Payment[] Array of payment aggregates
     */
    public function findAll(TenantId $tenantId): array;

    /**
     * Get payments requiring retry (for scheduled retry job).
     *
     * Finds payments in FAILED status with retry scheduled at or before the given time.
     * Used by the retry job scheduler to process failed payments.
     *
     * Business Rules:
     * - Only returns payments with retryCount < maxRetries (from RetryPolicy)
     * - Only returns payments where nextRetryAt <= $before
     * - Only returns payments in FAILED status with transient error codes
     *
     * @param \DateTimeImmutable $before Find payments with nextRetryAt before or at this time
     *
     * @return Payment[] Array of payment aggregates requiring retry
     */
    public function findPaymentsForRetry(\DateTimeImmutable $before): array;
}
