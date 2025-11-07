<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Model;

final readonly class Quantity implements \Stringable
{
    /**
     * Business Rules:
     * - quantity_min: 0
     * - quantity_max: 999999
     */
    private const MIN_QUANTITY = 0;
    private const MAX_QUANTITY = 999999;

    private function __construct(
        private int $value,
    ) {
        if ($value < self::MIN_QUANTITY) {
            throw new \InvalidArgumentException(sprintf('Quantity cannot be negative: %d', $value));
        }

        if ($value > self::MAX_QUANTITY) {
            throw new \InvalidArgumentException(sprintf('Quantity cannot exceed %d: %d', self::MAX_QUANTITY, $value));
        }
    }

    public static function fromInt(int $value): self
    {
        return new self($value);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function value(): int
    {
        return $this->value;
    }

    public function add(self $other): self
    {
        return new self($this->value + $other->value);
    }

    public function subtract(self $other): self
    {
        return new self($this->value - $other->value);
    }

    public function isZero(): bool
    {
        return 0 === $this->value;
    }

    public function isPositive(): bool
    {
        return $this->value > 0;
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->value > $other->value;
    }

    public function isGreaterThanOrEqual(self $other): bool
    {
        return $this->value >= $other->value;
    }

    public function isLessThan(self $other): bool
    {
        return $this->value < $other->value;
    }

    public function isLessThanOrEqual(self $other): bool
    {
        return $this->value <= $other->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function toString(): string
    {
        return (string) $this->value;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
