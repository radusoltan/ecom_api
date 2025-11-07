<?php

declare(strict_types=1);

namespace App\Payment\Domain\ValueObject;

/**
 * Payment Status Value Object.
 *
 * Payment lifecycle states:
 * - pending: Payment initiated, awaiting authorization
 * - authorized: Funds reserved, not yet captured
 * - captured: Payment completed, funds transferred
 * - refunded: Payment refunded to customer
 * - failed: Payment processing failed
 * - cancelled: Payment cancelled before capture
 */
final readonly class PaymentStatus
{
    private const PENDING = 'pending';
    private const AUTHORIZED = 'authorized';
    private const CAPTURED = 'captured';
    private const REFUNDED = 'refunded';
    private const FAILED = 'failed';
    private const CANCELLED = 'cancelled';

    private const VALID_STATUSES = [
        self::PENDING,
        self::AUTHORIZED,
        self::CAPTURED,
        self::REFUNDED,
        self::FAILED,
        self::CANCELLED,
    ];

    // Valid status transitions
    private const VALID_TRANSITIONS = [
        self::PENDING => [self::AUTHORIZED, self::FAILED, self::CANCELLED],
        self::AUTHORIZED => [self::CAPTURED, self::CANCELLED, self::FAILED],
        self::CAPTURED => [self::REFUNDED],
        self::REFUNDED => [],
        self::FAILED => [],
        self::CANCELLED => [],
    ];

    private function __construct(
        private string $value
    ) {
        if (!in_array($value, self::VALID_STATUSES, true)) {
            throw new \InvalidArgumentException(sprintf('Invalid payment status: "%s". Allowed: %s', $value, implode(', ', self::VALID_STATUSES)));
        }
    }

    public static function pending(): self
    {
        return new self(self::PENDING);
    }

    public static function authorized(): self
    {
        return new self(self::AUTHORIZED);
    }

    public static function captured(): self
    {
        return new self(self::CAPTURED);
    }

    public static function refunded(): self
    {
        return new self(self::REFUNDED);
    }

    public static function failed(): self
    {
        return new self(self::FAILED);
    }

    public static function cancelled(): self
    {
        return new self(self::CANCELLED);
    }

    public static function fromString(string $value): self
    {
        return new self(strtolower($value));
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isPending(): bool
    {
        return self::PENDING === $this->value;
    }

    public function isAuthorized(): bool
    {
        return self::AUTHORIZED === $this->value;
    }

    public function isCaptured(): bool
    {
        return self::CAPTURED === $this->value;
    }

    public function isRefunded(): bool
    {
        return self::REFUNDED === $this->value;
    }

    public function isFailed(): bool
    {
        return self::FAILED === $this->value;
    }

    public function isCancelled(): bool
    {
        return self::CANCELLED === $this->value;
    }

    public function canTransitionTo(self $newStatus): bool
    {
        $allowedTransitions = self::VALID_TRANSITIONS[$this->value] ?? [];

        return in_array($newStatus->value, $allowedTransitions, true);
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
