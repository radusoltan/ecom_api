<?php

declare(strict_types=1);

namespace App\Order\Domain\Model;

use InvalidArgumentException;

/**
 * Order Status Value Object
 *
 * Business Rules:
 * - Valid transitions: pending → processing → shipped → delivered
 * - Can cancel from: pending, processing
 * - Cannot modify after: shipped, delivered, cancelled
 */
final readonly class OrderStatus
{
    public const PENDING = 'pending';
    public const PAID = 'paid';
    public const PROCESSING = 'processing';
    public const SHIPPED = 'shipped';
    public const DELIVERED = 'delivered';
    public const CANCELLED = 'cancelled';

    private const VALID_STATUSES = [
        self::PENDING,
        self::PAID,
        self::PROCESSING,
        self::SHIPPED,
        self::DELIVERED,
        self::CANCELLED,
    ];

    private const VALID_TRANSITIONS = [
        self::PENDING => [self::PAID, self::PROCESSING, self::CANCELLED],
        self::PAID => [self::PROCESSING, self::CANCELLED],
        self::PROCESSING => [self::PAID, self::SHIPPED, self::CANCELLED],
        self::SHIPPED => [self::DELIVERED],
        self::DELIVERED => [],
        self::CANCELLED => [],
    ];

    private function __construct(private string $value)
    {
        if (!in_array($value, self::VALID_STATUSES, true)) {
            throw new InvalidArgumentException(
                sprintf('Invalid order status: "%s". Valid statuses are: %s', $value, implode(', ', self::VALID_STATUSES))
            );
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

    public static function paid(): self
    {
        return new self(self::PAID);
    }

    public static function processing(): self
    {
        return new self(self::PROCESSING);
    }

    public static function shipped(): self
    {
        return new self(self::SHIPPED);
    }

    public static function delivered(): self
    {
        return new self(self::DELIVERED);
    }

    public static function cancelled(): self
    {
        return new self(self::CANCELLED);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(OrderStatus $other): bool
    {
        return $this->value === $other->value;
    }

    public function isPending(): bool
    {
        return $this->value === self::PENDING;
    }

    public function isPaid(): bool
    {
        return $this->value === self::PAID;
    }

    public function isProcessing(): bool
    {
        return $this->value === self::PROCESSING;
    }

    public function isShipped(): bool
    {
        return $this->value === self::SHIPPED;
    }

    public function isDelivered(): bool
    {
        return $this->value === self::DELIVERED;
    }

    public function isCancelled(): bool
    {
        return $this->value === self::CANCELLED;
    }

    public function canTransitionTo(OrderStatus $newStatus): bool
    {
        return in_array($newStatus->value, self::VALID_TRANSITIONS[$this->value] ?? [], true);
    }

    public function isFinal(): bool
    {
        return $this->value === self::DELIVERED || $this->value === self::CANCELLED;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
