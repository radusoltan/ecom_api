<?php

declare(strict_types=1);

namespace App\Order\Domain\ValueObject;

/**
 * Fulfillment Status Value Object.
 *
 * Represents the current status of an order's fulfillment process.
 *
 * Workflow States:
 * - PENDING: Order placed, awaiting warehouse assignment
 * - ASSIGNED: Order assigned to warehouse, awaiting processing
 * - PICKING: Items being picked from warehouse shelves
 * - PACKING: Items being packed for shipment
 * - SHIPPING: Package shipped, in transit
 * - DELIVERED: Package delivered to customer
 * - CANCELLED: Fulfillment cancelled
 * - FAILED: Fulfillment failed (e.g., items not available)
 */
final readonly class FulfillmentStatus
{
    private const PENDING = 'pending';
    private const ASSIGNED = 'assigned';
    private const PICKING = 'picking';
    private const PACKING = 'packing';
    private const SHIPPING = 'shipping';
    private const DELIVERED = 'delivered';
    private const CANCELLED = 'cancelled';
    private const FAILED = 'failed';

    private const VALID_STATUSES = [
        self::PENDING,
        self::ASSIGNED,
        self::PICKING,
        self::PACKING,
        self::SHIPPING,
        self::DELIVERED,
        self::CANCELLED,
        self::FAILED,
    ];

    /**
     * Valid state transitions.
     */
    private const ALLOWED_TRANSITIONS = [
        self::PENDING => [self::ASSIGNED, self::CANCELLED, self::FAILED],
        self::ASSIGNED => [self::PICKING, self::CANCELLED, self::FAILED],
        self::PICKING => [self::PACKING, self::CANCELLED, self::FAILED],
        self::PACKING => [self::SHIPPING, self::CANCELLED, self::FAILED],
        self::SHIPPING => [self::DELIVERED, self::FAILED],
        self::DELIVERED => [], // Final state
        self::CANCELLED => [], // Final state
        self::FAILED => [], // Final state
    ];

    private string $value;

    private function __construct(string $value)
    {
        if (!in_array($value, self::VALID_STATUSES, true)) {
            throw new \DomainException(sprintf('Invalid fulfillment status: %s', $value));
        }
        $this->value = $value;
    }

    public static function pending(): self
    {
        return new self(self::PENDING);
    }

    public static function assigned(): self
    {
        return new self(self::ASSIGNED);
    }

    public static function picking(): self
    {
        return new self(self::PICKING);
    }

    public static function packing(): self
    {
        return new self(self::PACKING);
    }

    public static function shipping(): self
    {
        return new self(self::SHIPPING);
    }

    public static function delivered(): self
    {
        return new self(self::DELIVERED);
    }

    public static function cancelled(): self
    {
        return new self(self::CANCELLED);
    }

    public static function failed(): self
    {
        return new self(self::FAILED);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function toString(): string
    {
        return $this->value;
    }

    /**
     * Check if transition to another status is allowed.
     */
    public function canTransitionTo(self $newStatus): bool
    {
        $allowedStatuses = self::ALLOWED_TRANSITIONS[$this->value] ?? [];

        return in_array($newStatus->value, $allowedStatuses, true);
    }

    /**
     * Get all statuses that can be transitioned to from current status.
     *
     * @return string[]
     */
    public function getAllowedTransitions(): array
    {
        return self::ALLOWED_TRANSITIONS[$this->value] ?? [];
    }

    /**
     * Check if status is a final state (cannot transition further).
     */
    public function isFinalState(): bool
    {
        return empty(self::ALLOWED_TRANSITIONS[$this->value]);
    }

    /**
     * Check if status is completed (delivered).
     */
    public function isCompleted(): bool
    {
        return self::DELIVERED === $this->value;
    }

    /**
     * Check if status is cancelled.
     */
    public function isCancelled(): bool
    {
        return self::CANCELLED === $this->value;
    }

    /**
     * Check if status is failed.
     */
    public function isFailed(): bool
    {
        return self::FAILED === $this->value;
    }

    /**
     * Check if fulfillment is in progress (not final state).
     */
    public function isInProgress(): bool
    {
        return !$this->isFinalState();
    }

    /**
     * Check if status is pending assignment.
     */
    public function isPending(): bool
    {
        return self::PENDING === $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Get all valid status values.
     *
     * @return string[]
     */
    public static function getAllStatuses(): array
    {
        return self::VALID_STATUSES;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
