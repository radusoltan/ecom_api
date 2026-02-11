<?php

declare(strict_types=1);

namespace App\Payment\Domain\Model;

use App\Shared\Domain\ValueObject\Money;
use DateTimeImmutable;

/**
 * Transaction Entity - Immutable Audit Log for Payment Operations.
 *
 * Each Transaction records a single operation (authorize, capture, refund, void, chargeback)
 * against a Payment. Transactions are never modified after creation - they serve as an
 * immutable audit trail of all payment gateway interactions.
 *
 * Business Rules:
 * - Transactions are immutable (no setters, readonly class)
 * - Each transaction has a unique ID
 * - All transactions are linked to a parent Payment
 * - Gateway transaction ID must be stored for reconciliation
 * - Raw gateway response stored for debugging/audit purposes
 * - Failed transactions must record error code and message
 * - Timestamps are immutable and set at creation time
 *
 * Design Patterns:
 * - Factory Methods: Separate static methods for each transaction type
 * - Immutable Entity: All properties readonly, no modification after creation
 * - Value Objects: Uses TransactionId, PaymentId, Money, TransactionType, TransactionStatus
 *
 * @see Payment
 * @see TransactionType
 * @see TransactionStatus
 */
final readonly class Transaction
{
    /**
     * Private constructor - use factory methods to create instances.
     */
    private function __construct(
        private TransactionId $id,
        private PaymentId $paymentId,
        private TransactionType $type,
        private Money $amount,
        private string $gatewayTransactionId,
        private ?string $gatewayResponse,
        private TransactionStatus $status,
        private ?string $errorCode,
        private ?string $errorMessage,
        private DateTimeImmutable $createdAt
    ) {
    }

    /**
     * Create a successful authorization transaction.
     *
     * Authorization reserves funds on the customer's payment method
     * but does not actually charge them.
     *
     * @param TransactionId $id Unique transaction identifier
     * @param PaymentId $paymentId Parent payment identifier
     * @param Money $amount Amount to authorize
     * @param string $gatewayTransactionId Gateway's transaction reference
     * @param string|null $gatewayResponse Raw gateway response (JSON)
     */
    public static function createAuthorization(
        TransactionId $id,
        PaymentId $paymentId,
        Money $amount,
        string $gatewayTransactionId,
        ?string $gatewayResponse = null
    ): self {
        return new self(
            id: $id,
            paymentId: $paymentId,
            type: TransactionType::AUTHORIZATION,
            amount: $amount,
            gatewayTransactionId: $gatewayTransactionId,
            gatewayResponse: $gatewayResponse,
            status: TransactionStatus::SUCCESS,
            errorCode: null,
            errorMessage: null,
            createdAt: new DateTimeImmutable()
        );
    }

    /**
     * Create a successful capture transaction.
     *
     * Capture actually charges the previously authorized funds.
     *
     * @param TransactionId $id Unique transaction identifier
     * @param PaymentId $paymentId Parent payment identifier
     * @param Money $amount Amount to capture (can be less than authorized amount for partial capture)
     * @param string $gatewayTransactionId Gateway's transaction reference
     * @param string|null $gatewayResponse Raw gateway response (JSON)
     */
    public static function createCapture(
        TransactionId $id,
        PaymentId $paymentId,
        Money $amount,
        string $gatewayTransactionId,
        ?string $gatewayResponse = null
    ): self {
        return new self(
            id: $id,
            paymentId: $paymentId,
            type: TransactionType::CAPTURE,
            amount: $amount,
            gatewayTransactionId: $gatewayTransactionId,
            gatewayResponse: $gatewayResponse,
            status: TransactionStatus::SUCCESS,
            errorCode: null,
            errorMessage: null,
            createdAt: new DateTimeImmutable()
        );
    }

    /**
     * Create a successful refund transaction.
     *
     * Refund returns money to the customer's payment method.
     *
     * @param TransactionId $id Unique transaction identifier
     * @param PaymentId $paymentId Parent payment identifier
     * @param Money $amount Amount to refund (can be less than captured amount for partial refund)
     * @param string $gatewayTransactionId Gateway's transaction reference
     * @param string|null $gatewayResponse Raw gateway response (JSON)
     */
    public static function createRefund(
        TransactionId $id,
        PaymentId $paymentId,
        Money $amount,
        string $gatewayTransactionId,
        ?string $gatewayResponse = null
    ): self {
        return new self(
            id: $id,
            paymentId: $paymentId,
            type: TransactionType::REFUND,
            amount: $amount,
            gatewayTransactionId: $gatewayTransactionId,
            gatewayResponse: $gatewayResponse,
            status: TransactionStatus::SUCCESS,
            errorCode: null,
            errorMessage: null,
            createdAt: new DateTimeImmutable()
        );
    }

    /**
     * Create a successful void transaction.
     *
     * Void cancels a previously authorized transaction before it's captured.
     * Money was never actually charged, so no refund is needed.
     *
     * @param TransactionId $id Unique transaction identifier
     * @param PaymentId $paymentId Parent payment identifier
     * @param Money $amount Amount voided (should match authorization amount)
     * @param string $gatewayTransactionId Gateway's transaction reference
     * @param string|null $gatewayResponse Raw gateway response (JSON)
     */
    public static function createVoid(
        TransactionId $id,
        PaymentId $paymentId,
        Money $amount,
        string $gatewayTransactionId,
        ?string $gatewayResponse = null
    ): self {
        return new self(
            id: $id,
            paymentId: $paymentId,
            type: TransactionType::VOID,
            amount: $amount,
            gatewayTransactionId: $gatewayTransactionId,
            gatewayResponse: $gatewayResponse,
            status: TransactionStatus::SUCCESS,
            errorCode: null,
            errorMessage: null,
            createdAt: new DateTimeImmutable()
        );
    }

    /**
     * Create a failed transaction.
     *
     * Records when a transaction attempt failed at the gateway level.
     *
     * @param TransactionId $id Unique transaction identifier
     * @param PaymentId $paymentId Parent payment identifier
     * @param TransactionType $type Type of transaction that failed
     * @param Money $amount Amount that was attempted
     * @param string $errorCode Gateway error code
     * @param string $errorMessage Human-readable error message
     * @param string|null $gatewayResponse Raw gateway response (JSON)
     */
    public static function createFailed(
        TransactionId $id,
        PaymentId $paymentId,
        TransactionType $type,
        Money $amount,
        string $errorCode,
        string $errorMessage,
        ?string $gatewayResponse = null
    ): self {
        return new self(
            id: $id,
            paymentId: $paymentId,
            type: $type,
            amount: $amount,
            gatewayTransactionId: '', // No gateway transaction ID for failed transactions
            gatewayResponse: $gatewayResponse,
            status: TransactionStatus::FAILED,
            errorCode: $errorCode,
            errorMessage: $errorMessage,
            createdAt: new DateTimeImmutable()
        );
    }

    /**
     * Reconstitute a Transaction from persistence.
     *
     * Used by the repository to reconstruct domain entities from database records.
     * This is the only way to create a Transaction with a custom createdAt timestamp.
     *
     * @internal Only for use by infrastructure layer repositories
     */
    public static function reconstituteFromPersistence(
        TransactionId $id,
        PaymentId $paymentId,
        TransactionType $type,
        Money $amount,
        string $gatewayTransactionId,
        ?string $gatewayResponse,
        TransactionStatus $status,
        ?string $errorCode,
        ?string $errorMessage,
        DateTimeImmutable $createdAt
    ): self {
        return new self(
            id: $id,
            paymentId: $paymentId,
            type: $type,
            amount: $amount,
            gatewayTransactionId: $gatewayTransactionId,
            gatewayResponse: $gatewayResponse,
            status: $status,
            errorCode: $errorCode,
            errorMessage: $errorMessage,
            createdAt: $createdAt
        );
    }

    // ==================== Getters ====================

    public function id(): TransactionId
    {
        return $this->id;
    }

    public function paymentId(): PaymentId
    {
        return $this->paymentId;
    }

    public function type(): TransactionType
    {
        return $this->type;
    }

    public function amount(): Money
    {
        return $this->amount;
    }

    public function gatewayTransactionId(): string
    {
        return $this->gatewayTransactionId;
    }

    public function gatewayResponse(): ?string
    {
        return $this->gatewayResponse;
    }

    public function status(): TransactionStatus
    {
        return $this->status;
    }

    public function errorCode(): ?string
    {
        return $this->errorCode;
    }

    public function errorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    // ==================== Query Methods ====================

    /**
     * Check if the transaction was successful.
     */
    public function isSuccessful(): bool
    {
        return $this->status->isSuccessful();
    }

    /**
     * Check if the transaction failed.
     */
    public function isFailed(): bool
    {
        return $this->status->isFailed();
    }

    /**
     * Check if the transaction is still pending.
     */
    public function isPending(): bool
    {
        return $this->status->isPending();
    }

    /**
     * Check if this is an authorization transaction.
     */
    public function isAuthorization(): bool
    {
        return $this->type->isAuthorization();
    }

    /**
     * Check if this is a capture transaction.
     */
    public function isCapture(): bool
    {
        return $this->type->isCapture();
    }

    /**
     * Check if this is a refund transaction.
     */
    public function isRefund(): bool
    {
        return $this->type->isRefund();
    }

    /**
     * Check if this is a void transaction.
     */
    public function isVoid(): bool
    {
        return $this->type->isVoid();
    }

    /**
     * Check if this transaction reverses a previous transaction.
     * Reversing transactions include: REFUND, VOID, CHARGEBACK.
     */
    public function isReversing(): bool
    {
        return $this->type->isReversing();
    }
}
