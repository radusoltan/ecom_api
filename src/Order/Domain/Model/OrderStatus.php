<?php

declare(strict_types=1);

namespace App\Order\Domain\Model;

/**
 * Order Status Value Object.
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
            throw new \InvalidArgumentException(sprintf('Invalid order status: "%s". Valid statuses are: %s', $value, implode(', ', self::VALID_STATUSES)));
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
        return self::PENDING === $this->value;
    }

    public function isPaid(): bool
    {
        return self::PAID === $this->value;
    }

    public function isProcessing(): bool
    {
        return self::PROCESSING === $this->value;
    }

    public function isShipped(): bool
    {
        return self::SHIPPED === $this->value;
    }

    public function isDelivered(): bool
    {
        return self::DELIVERED === $this->value;
    }

    public function isCancelled(): bool
    {
        return self::CANCELLED === $this->value;
    }

    public function canTransitionTo(OrderStatus $newStatus): bool
    {
        return in_array($newStatus->value, self::VALID_TRANSITIONS[$this->value] ?? [], true);
    }

    public function isFinal(): bool
    {
        return self::DELIVERED === $this->value || self::CANCELLED === $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
