<?php

declare(strict_types=1);

namespace App\Payment\Domain\ValueObject;

/**
 * Refund Status Value Object.
 *
 * Refund lifecycle states:
 * - pending: Refund initiated, awaiting processing
 * - succeeded: Refund successfully processed
 * - failed: Refund processing failed
 *
 * Business Rules:
 * Valid transitions:
 * - pending → [succeeded, failed]
 * - succeeded → [] (terminal state)
 * - failed → [] (terminal state)
 */
final readonly class RefundStatus
{
    private const PENDING = 'pending';
    private const SUCCEEDED = 'succeeded';
    private const FAILED = 'failed';

    private const VALID_STATUSES = [
        self::PENDING,
        self::SUCCEEDED,
        self::FAILED,
    ];

    // Valid status transitions
    private const VALID_TRANSITIONS = [
        self::PENDING => [self::SUCCEEDED, self::FAILED],
        self::SUCCEEDED => [],
        self::FAILED => [],
    ];

    private function __construct(private string $value)
    {
        if (!in_array($value, self::VALID_STATUSES, true)) {
            throw new \InvalidArgumentException(sprintf('Invalid refund status: "%s". Allowed: %s', $value, implode(', ', self::VALID_STATUSES)));
        }
    }

    /**
     * Create a pending refund status.
     */
    public static function pending(): self
    {
        return new self(self::PENDING);
    }

    /**
     * Create a succeeded refund status.
     */
    public static function succeeded(): self
    {
        return new self(self::SUCCEEDED);
    }

    /**
     * Create a failed refund status.
     */
    public static function failed(): self
    {
        return new self(self::FAILED);
    }

    /**
     * Create RefundStatus from a string value.
     */
    public static function fromString(string $value): self
    {
        return new self(strtolower($value));
    }

    /**
     * Get the status value as a string.
     */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * Check if refund is pending.
     */
    public function isPending(): bool
    {
        return self::PENDING === $this->value;
    }

    /**
     * Check if refund succeeded.
     */
    public function isSucceeded(): bool
    {
        return self::SUCCEEDED === $this->value;
    }

    /**
     * Check if refund failed.
     */
    public function isFailed(): bool
    {
        return self::FAILED === $this->value;
    }

    /**
     * Check if this status can transition to the given status.
     */
    public function canTransitionTo(RefundStatus $newStatus): bool
    {
        $allowedTransitions = self::VALID_TRANSITIONS[$this->value] ?? [];

        return in_array($newStatus->value, $allowedTransitions, true);
    }

    /**
     * Check if this is a final (terminal) status.
     */
    public function isFinal(): bool
    {
        return self::SUCCEEDED === $this->value || self::FAILED === $this->value;
    }

    /**
     * Check equality with another RefundStatus.
     */
    public function equals(RefundStatus $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
