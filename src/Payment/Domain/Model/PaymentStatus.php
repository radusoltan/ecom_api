<?php

declare(strict_types=1);

namespace App\Payment\Domain\Model;

/**
 * Payment Status Value Object with State Machine.
 *
 * Business Rules:
 * - Valid transitions enforce payment lifecycle
 * - PENDING: Initial state, awaiting authorization
 * - AUTHORIZED: Funds reserved but not captured
 * - CAPTURED: Funds successfully captured
 * - PARTIALLY_REFUNDED: Some funds returned to customer
 * - REFUNDED: All funds returned to customer
 * - FAILED: Payment processing failed
 * - CANCELLED: Payment cancelled before authorization
 * - EXPIRED: Authorization expired before capture
 * - VOIDED: Authorization cancelled before capture
 * - DISPUTED: Customer initiated chargeback/dispute
 *
 * State Transitions:
 * - PENDING → AUTHORIZED, FAILED, CANCELLED
 * - AUTHORIZED → CAPTURED, EXPIRED, VOIDED, FAILED
 * - CAPTURED → REFUNDED, PARTIALLY_REFUNDED, DISPUTED
 * - PARTIALLY_REFUNDED → REFUNDED, PARTIALLY_REFUNDED
 * - Terminal states (REFUNDED, FAILED, CANCELLED, EXPIRED, VOIDED) cannot transition
 */
enum PaymentStatus: string
{
    case PENDING = 'pending';
    case AUTHORIZED = 'authorized';
    case CAPTURED = 'captured';
    case PARTIALLY_REFUNDED = 'partially_refunded';
    case REFUNDED = 'refunded';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';
    case VOIDED = 'voided';
    case DISPUTED = 'disputed';

    /**
     * Valid state transitions map.
     */
    private const VALID_TRANSITIONS = [
        'pending' => ['authorized', 'failed', 'cancelled'],
        'authorized' => ['captured', 'expired', 'voided', 'failed'],
        'captured' => ['refunded', 'partially_refunded', 'disputed'],
        'partially_refunded' => ['refunded', 'partially_refunded'],
        'refunded' => [],
        'failed' => [],
        'cancelled' => [],
        'expired' => [],
        'voided' => [],
        'disputed' => [],
    ];

    /**
     * Check if transition to new status is allowed.
     */
    public function canTransitionTo(PaymentStatus $newStatus): bool
    {
        return in_array($newStatus->value, self::VALID_TRANSITIONS[$this->value] ?? [], true);
    }

    /**
     * Attempt to transition to new status.
     *
     * @throws \DomainException if transition is not allowed
     */
    public function transitionTo(PaymentStatus $newStatus): PaymentStatus
    {
        if (!$this->canTransitionTo($newStatus)) {
            throw new \DomainException(sprintf('Cannot transition from %s to %s. Valid transitions: %s', $this->value, $newStatus->value, empty(self::VALID_TRANSITIONS[$this->value]) ? 'none (terminal state)' : implode(', ', self::VALID_TRANSITIONS[$this->value])));
        }

        return $newStatus;
    }

    /**
     * Check if this is a terminal state (no further transitions allowed).
     */
    public function isFinal(): bool
    {
        return empty(self::VALID_TRANSITIONS[$this->value]);
    }

    /**
     * Check if this is a successful payment state.
     */
    public function isSuccessful(): bool
    {
        return self::CAPTURED === $this || self::AUTHORIZED === $this;
    }

    /**
     * Check if payment is pending.
     */
    public function isPending(): bool
    {
        return self::PENDING === $this;
    }

    /**
     * Check if payment is authorized.
     */
    public function isAuthorized(): bool
    {
        return self::AUTHORIZED === $this;
    }

    /**
     * Check if payment is captured.
     */
    public function isCaptured(): bool
    {
        return self::CAPTURED === $this;
    }

    /**
     * Check if payment is partially refunded.
     */
    public function isPartiallyRefunded(): bool
    {
        return self::PARTIALLY_REFUNDED === $this;
    }

    /**
     * Check if payment is fully refunded.
     */
    public function isRefunded(): bool
    {
        return self::REFUNDED === $this;
    }

    /**
     * Check if payment failed.
     */
    public function isFailed(): bool
    {
        return self::FAILED === $this;
    }

    /**
     * Check if payment was cancelled.
     */
    public function isCancelled(): bool
    {
        return self::CANCELLED === $this;
    }

    /**
     * Check if payment authorization expired.
     */
    public function isExpired(): bool
    {
        return self::EXPIRED === $this;
    }

    /**
     * Check if payment was voided.
     */
    public function isVoided(): bool
    {
        return self::VOIDED === $this;
    }

    /**
     * Check if payment is disputed.
     */
    public function isDisputed(): bool
    {
        return self::DISPUTED === $this;
    }

    /**
     * Get the value as string.
     */
    public function value(): string
    {
        return $this->value;
    }
}
