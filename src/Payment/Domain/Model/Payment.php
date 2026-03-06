<?php

declare(strict_types=1);

namespace App\Payment\Domain\Model;

use App\Payment\Domain\Event\PaymentAuthorized;
use App\Payment\Domain\Event\PaymentCancelled;
use App\Payment\Domain\Event\PaymentCaptured;
use App\Payment\Domain\Event\PaymentCreated;
use App\Payment\Domain\Event\PaymentFailed;
use App\Payment\Domain\Event\PaymentRefunded;
use App\Payment\Domain\Event\PaymentRetryAttempted;
use App\Payment\Domain\Event\PaymentRetryExhausted;
use App\Payment\Domain\Event\PaymentRetryScheduled;
use App\Payment\Domain\ValueObject\PaymentGateway;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Payment\Domain\ValueObject\PaymentMethod;
use App\Payment\Domain\ValueObject\PaymentStatus;
use App\Payment\Domain\ValueObject\RetryPolicy;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Payment Aggregate Root.
 *
 * Manages payment lifecycle:
 * - Create: Initialize payment for an order
 * - Authorize: Reserve funds (pre-authorization)
 * - Capture: Complete payment and transfer funds
 * - Refund: Return funds to customer
 * - Cancel: Cancel before capture
 * - Fail: Handle payment failures
 *
 * Business Rules:
 * - Payment amount must match order total
 * - Status transitions must follow valid state machine
 * - Gateway transaction ID required after authorization
 * - Refunds only allowed for captured payments
 * - Partial refunds supported (up to captured amount)
 * - Multi-currency support (amount stored in cents)
 * - Retry Policy:
 *   - Maximum 3 retry attempts for transient failures
 *   - Exponential backoff: 1h, 4h, 24h
 *   - Only retry transient errors (card_declined, insufficient_funds, processing_error)
 *   - Never retry: expired_card, fraudulent, invalid_card_number
 */
final class Payment extends AggregateRoot
{
    private PaymentId $id;
    private TenantId $tenantId;
    private string $orderId; // Reference to Order aggregate
    private Money $amount;
    private PaymentMethod $method;
    private PaymentGateway $gateway;
    private PaymentStatus $status;
    private ?string $gatewayTransactionId;
    private ?string $errorMessage;
    private ?string $errorCode; // Normalized error code for retry logic
    private Money $refundedAmount;
    private ?string $idempotencyKey; // Prevents duplicate payment processing
    private int $retryCount; // Number of retry attempts made (0-indexed)
    private ?\DateTimeImmutable $nextRetryAt; // Scheduled time for next retry
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    private function __construct()
    {
    }

    public static function create(
        PaymentId $id,
        TenantId $tenantId,
        string $orderId,
        Money $amount,
        PaymentMethod $method,
        PaymentGateway $gateway,
        ?string $idempotencyKey = null,
    ): self {
        self::validateAmount($amount);

        $payment = new self();
        $payment->id = $id;
        $payment->tenantId = $tenantId;
        $payment->orderId = $orderId;
        $payment->amount = $amount;
        $payment->method = $method;
        $payment->gateway = $gateway;
        $payment->status = PaymentStatus::pending();
        $payment->gatewayTransactionId = null;
        $payment->errorMessage = null;
        $payment->errorCode = null;
        $payment->idempotencyKey = $idempotencyKey;
        $payment->refundedAmount = Money::fromScalars(0, $amount->getCurrency()->getCurrencyCode());
        $payment->retryCount = 0;
        $payment->nextRetryAt = null;
        $payment->createdAt = new \DateTimeImmutable();
        $payment->updatedAt = new \DateTimeImmutable();

        $payment->recordEvent(new PaymentCreated(
            $payment->id,
            $payment->tenantId,
            $payment->orderId,
            $payment->amount,
            $payment->gateway->value()
        ));

        return $payment;
    }

    public static function reconstituteFromPersistence(
        PaymentId $id,
        TenantId $tenantId,
        string $orderId,
        Money $amount,
        PaymentMethod $method,
        PaymentGateway $gateway,
        PaymentStatus $status,
        ?string $gatewayTransactionId,
        ?string $errorMessage,
        Money $refundedAmount,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
        // Optional fields with defaults for backwards compatibility
        ?string $errorCode = null,
        int $retryCount = 0,
        ?\DateTimeImmutable $nextRetryAt = null,
        ?string $idempotencyKey = null,
    ): self {
        $payment = new self();
        $payment->id = $id;
        $payment->tenantId = $tenantId;
        $payment->orderId = $orderId;
        $payment->amount = $amount;
        $payment->method = $method;
        $payment->gateway = $gateway;
        $payment->status = $status;
        $payment->gatewayTransactionId = $gatewayTransactionId;
        $payment->errorMessage = $errorMessage;
        $payment->errorCode = $errorCode;
        $payment->idempotencyKey = $idempotencyKey;
        $payment->refundedAmount = $refundedAmount;
        $payment->retryCount = $retryCount;
        $payment->nextRetryAt = $nextRetryAt;
        $payment->createdAt = $createdAt;
        $payment->updatedAt = $updatedAt;

        return $payment;
    }

    public function authorize(string $gatewayTransactionId): void
    {
        if (!$this->status->canTransitionTo(PaymentStatus::authorized())) {
            throw new \InvalidArgumentException(sprintf('Cannot authorize payment in status: %s', $this->status->value()));
        }

        if (empty($gatewayTransactionId)) {
            throw new \InvalidArgumentException('Gateway transaction ID is required for authorization');
        }

        $this->status = PaymentStatus::authorized();
        $this->gatewayTransactionId = $gatewayTransactionId;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new PaymentAuthorized(
            $this->id,
            $this->tenantId,
            $gatewayTransactionId
        ));
    }

    public function capture(): void
    {
        if (!$this->status->canTransitionTo(PaymentStatus::captured())) {
            throw new \InvalidArgumentException(sprintf('Cannot capture payment in status: %s', $this->status->value()));
        }

        $this->status = PaymentStatus::captured();
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new PaymentCaptured(
            $this->id,
            $this->tenantId,
            $this->amount,
            $this->orderId
        ));
    }

    public function refund(Money $refundAmount, string $reason): void
    {
        if (!$this->status->canTransitionTo(PaymentStatus::refunded())) {
            throw new \InvalidArgumentException(sprintf('Cannot refund payment in status: %s', $this->status->value()));
        }

        if ($refundAmount->isZero() || $refundAmount->isNegative()) {
            throw new \InvalidArgumentException('Refund amount must be greater than 0');
        }

        $maxRefundable = $this->amount->subtract($this->refundedAmount);
        if ($refundAmount->isGreaterThan($maxRefundable)) {
            throw new \InvalidArgumentException(sprintf('Refund amount (%d) exceeds available amount (%d)', $refundAmount->getAmount(), $maxRefundable->getAmount()));
        }

        if (empty($reason)) {
            throw new \InvalidArgumentException('Refund reason is required');
        }

        $this->refundedAmount = $this->refundedAmount->add($refundAmount);
        $this->status = PaymentStatus::refunded();
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new PaymentRefunded(
            $this->id,
            $this->tenantId,
            $refundAmount,
            $reason
        ));
    }

    public function cancel(string $reason): void
    {
        if (!$this->status->canTransitionTo(PaymentStatus::cancelled())) {
            throw new \InvalidArgumentException(sprintf('Cannot cancel payment in status: %s', $this->status->value()));
        }

        if (empty($reason)) {
            throw new \InvalidArgumentException('Cancellation reason is required');
        }

        $this->status = PaymentStatus::cancelled();
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new PaymentCancelled(
            $this->id,
            $this->tenantId,
            $reason
        ));
    }

    public function markAsFailed(string $errorMessage, ?string $errorCode = null): void
    {
        if (!$this->status->canTransitionTo(PaymentStatus::failed())) {
            throw new \InvalidArgumentException(sprintf('Cannot mark payment as failed in status: %s', $this->status->value()));
        }

        if (empty($errorMessage)) {
            throw new \InvalidArgumentException('Error message is required');
        }

        $this->status = PaymentStatus::failed();
        $this->errorMessage = $errorMessage;
        $this->errorCode = $errorCode;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new PaymentFailed(
            $this->id,
            $this->tenantId,
            $errorMessage
        ));
    }

    /**
     * Schedule a retry for this payment.
     *
     * @param RetryPolicy $policy Retry policy to use
     *
     * @throws \InvalidArgumentException if max retries reached
     */
    public function scheduleRetry(RetryPolicy $policy): void
    {
        if (!$this->status->isFailed()) {
            throw new \InvalidArgumentException('Can only schedule retry for failed payments');
        }

        if ($this->retryCount >= $policy->maxAttempts()) {
            throw new \InvalidArgumentException(sprintf('Cannot schedule retry. Max attempts (%d) reached', $policy->maxAttempts()));
        }

        $this->nextRetryAt = $policy->calculateNextRetryTime($this->retryCount);
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new PaymentRetryScheduled(
            $this->id,
            $this->tenantId,
            $this->retryCount + 1, // Next attempt number (1-indexed)
            $this->nextRetryAt,
            $this->errorCode ?? 'unknown',
            $this->orderId
        ));
    }

    /**
     * Record that a retry attempt was made.
     *
     * @param bool        $wasSuccessful Whether the retry succeeded
     * @param string|null $errorCode     Error code if retry failed
     * @param string|null $errorMessage  Error message if retry failed
     */
    public function recordRetryAttempt(bool $wasSuccessful, ?string $errorCode = null, ?string $errorMessage = null): void
    {
        ++$this->retryCount;
        $this->updatedAt = new \DateTimeImmutable();

        if (!$wasSuccessful) {
            $this->errorCode = $errorCode;
            $this->errorMessage = $errorMessage;
        }

        $this->recordEvent(new PaymentRetryAttempted(
            $this->id,
            $this->tenantId,
            $this->retryCount,
            $wasSuccessful,
            $errorCode,
            $errorMessage
        ));
    }

    /**
     * Mark all retries as exhausted.
     *
     * @param RetryPolicy $policy Retry policy used
     */
    public function markRetryExhausted(RetryPolicy $policy): void
    {
        if ($this->retryCount < $policy->maxAttempts()) {
            throw new \InvalidArgumentException('Cannot mark retry exhausted before reaching max attempts');
        }

        $this->nextRetryAt = null;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new PaymentRetryExhausted(
            $this->id,
            $this->tenantId,
            $this->retryCount,
            $this->errorCode ?? 'unknown',
            $this->errorMessage ?? 'Unknown error',
            $this->orderId
        ));
    }

    /**
     * Check if payment is eligible for retry.
     */
    public function canRetry(RetryPolicy $policy): bool
    {
        return $this->status->isFailed()
            && $this->retryCount < $policy->maxAttempts()
            && null !== $this->errorCode
            && $policy->isRetryable($this->errorCode);
    }

    /**
     * Check if payment is due for retry.
     */
    public function isDueForRetry(?\DateTimeImmutable $now = null): bool
    {
        if (null === $this->nextRetryAt) {
            return false;
        }

        $now = $now ?? new \DateTimeImmutable();

        return $now >= $this->nextRetryAt;
    }

    private static function validateAmount(Money $amount): void
    {
        if ($amount->isZero() || $amount->isNegative()) {
            throw new \InvalidArgumentException(sprintf('Payment amount must be greater than 0. Got: %d cents', $amount->getAmount()));
        }

        // Maximum amount: $1,000,000.00 (100,000,000 cents)
        if ($amount->getAmount() > 100_000_000) {
            throw new \InvalidArgumentException(sprintf('Payment amount exceeds maximum allowed ($1,000,000.00). Got: %d cents', $amount->getAmount()));
        }
    }

    // Getters
    public function id(): PaymentId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function orderId(): string
    {
        return $this->orderId;
    }

    public function amount(): Money
    {
        return $this->amount;
    }

    public function currency(): string
    {
        return $this->amount->getCurrency()->getCurrencyCode();
    }

    public function method(): PaymentMethod
    {
        return $this->method;
    }

    public function gateway(): PaymentGateway
    {
        return $this->gateway;
    }

    public function status(): PaymentStatus
    {
        return $this->status;
    }

    public function gatewayTransactionId(): ?string
    {
        return $this->gatewayTransactionId;
    }

    public function errorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function errorCode(): ?string
    {
        return $this->errorCode;
    }

    public function idempotencyKey(): ?string
    {
        return $this->idempotencyKey;
    }

    public function refundedAmount(): Money
    {
        return $this->refundedAmount;
    }

    public function retryCount(): int
    {
        return $this->retryCount;
    }

    public function nextRetryAt(): ?\DateTimeImmutable
    {
        return $this->nextRetryAt;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
