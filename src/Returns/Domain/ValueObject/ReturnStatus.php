<?php

declare(strict_types=1);

namespace App\Returns\Domain\ValueObject;

/**
 * Value Object representing the status of a ReturnRequest.
 *
 * State Machine:
 * requested → approved → received → inspected → completed / rejected
 *
 * Business Rules:
 * - Can only transition from requested to approved or rejected
 * - Can only transition from approved to received
 * - Can only transition from received to inspected
 * - Can only transition from inspected to completed or rejected
 * - Once completed or rejected, no further transitions allowed
 */
final readonly class ReturnStatus
{
    public const REQUESTED = 'requested';   // Customer requested return (RMA)
    public const APPROVED = 'approved';     // Return approved, awaiting item
    public const RECEIVED = 'received';     // Item received at warehouse
    public const INSPECTED = 'inspected';   // Item inspected, awaiting refund
    public const COMPLETED = 'completed';   // Refund issued, return complete
    public const REJECTED = 'rejected';     // Return rejected (from requested or inspected)

    private const VALID_STATUSES = [
        self::REQUESTED,
        self::APPROVED,
        self::RECEIVED,
        self::INSPECTED,
        self::COMPLETED,
        self::REJECTED,
    ];

    /**
     * Valid status transitions matrix.
     * Key = current status, Value = array of allowed next statuses.
     */
    private const TRANSITIONS = [
        self::REQUESTED => [self::APPROVED, self::REJECTED],
        self::APPROVED => [self::RECEIVED],
        self::RECEIVED => [self::INSPECTED],
        self::INSPECTED => [self::COMPLETED, self::REJECTED],
        self::COMPLETED => [],  // Terminal state
        self::REJECTED => [],   // Terminal state
    ];

    private function __construct(
        private string $value,
    ) {
        if (!in_array($this->value, self::VALID_STATUSES, true)) {
            throw new \InvalidArgumentException(sprintf('Invalid return status: "%s". Must be one of: %s', $value, implode(', ', self::VALID_STATUSES)));
        }
    }

    public static function requested(): self
    {
        return new self(self::REQUESTED);
    }

    public static function approved(): self
    {
        return new self(self::APPROVED);
    }

    public static function received(): self
    {
        return new self(self::RECEIVED);
    }

    public static function inspected(): self
    {
        return new self(self::INSPECTED);
    }

    public static function completed(): self
    {
        return new self(self::COMPLETED);
    }

    public static function rejected(): self
    {
        return new self(self::REJECTED);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isRequested(): bool
    {
        return self::REQUESTED === $this->value;
    }

    public function isApproved(): bool
    {
        return self::APPROVED === $this->value;
    }

    public function isReceived(): bool
    {
        return self::RECEIVED === $this->value;
    }

    public function isInspected(): bool
    {
        return self::INSPECTED === $this->value;
    }

    public function isCompleted(): bool
    {
        return self::COMPLETED === $this->value;
    }

    public function isRejected(): bool
    {
        return self::REJECTED === $this->value;
    }

    public function isTerminal(): bool
    {
        return $this->isCompleted() || $this->isRejected();
    }

    /**
     * Check if transition to a new status is allowed.
     */
    public function canTransitionTo(self $newStatus): bool
    {
        $allowedTransitions = self::TRANSITIONS[$this->value] ?? [];

        return in_array($newStatus->value, $allowedTransitions, true);
    }

    /**
     * Get all allowed next statuses from current status.
     *
     * @return array<string>
     */
    public function getAllowedTransitions(): array
    {
        return self::TRANSITIONS[$this->value] ?? [];
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
