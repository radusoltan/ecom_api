<?php

declare(strict_types=1);

namespace App\Shipping\Domain\Model;

final readonly class ShipmentStatus
{
    public const PENDING = 'pending';
    public const DISPATCHED = 'dispatched';
    public const IN_TRANSIT = 'in_transit';
    public const DELIVERED = 'delivered';
    public const CANCELLED = 'cancelled';
    public const FAILED = 'failed';

    private const VALID_STATUSES = [
        self::PENDING,
        self::DISPATCHED,
        self::IN_TRANSIT,
        self::DELIVERED,
        self::CANCELLED,
        self::FAILED,
    ];

    private const VALID_TRANSITIONS = [
        self::PENDING => [self::DISPATCHED, self::CANCELLED],
        self::DISPATCHED => [self::IN_TRANSIT, self::DELIVERED, self::FAILED],
        self::IN_TRANSIT => [self::DELIVERED, self::FAILED],
        self::DELIVERED => [],
        self::CANCELLED => [],
        self::FAILED => [],
    ];

    private function __construct(private string $value)
    {
        if (!in_array($value, self::VALID_STATUSES, true)) {
            throw new \InvalidArgumentException(sprintf('Invalid shipment status: "%s". Valid statuses are: %s', $value, implode(', ', self::VALID_STATUSES)));
        }
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public static function pending(): self
    {
        return new self(self::PENDING);
    }

    public static function dispatched(): self
    {
        return new self(self::DISPATCHED);
    }

    public static function inTransit(): self
    {
        return new self(self::IN_TRANSIT);
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

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function isPending(): bool
    {
        return self::PENDING === $this->value;
    }

    public function isDispatched(): bool
    {
        return self::DISPATCHED === $this->value;
    }

    public function isInTransit(): bool
    {
        return self::IN_TRANSIT === $this->value;
    }

    public function isDelivered(): bool
    {
        return self::DELIVERED === $this->value;
    }

    public function isCancelled(): bool
    {
        return self::CANCELLED === $this->value;
    }

    public function isFailed(): bool
    {
        return self::FAILED === $this->value;
    }

    public function canTransitionTo(self $newStatus): bool
    {
        return in_array($newStatus->value, self::VALID_TRANSITIONS[$this->value] ?? [], true);
    }

    public function isFinal(): bool
    {
        return self::DELIVERED === $this->value || self::CANCELLED === $this->value || self::FAILED === $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
