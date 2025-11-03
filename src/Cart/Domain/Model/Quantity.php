<?php

declare(strict_types=1);

namespace App\Cart\Domain\Model;

use InvalidArgumentException;

/**
 * Quantity Value Object
 *
 * Business Rules:
 * - min: 1
 * - max: 999
 * - integer only
 */
final readonly class Quantity
{
    private const MIN_QUANTITY = 1;
    private const MAX_QUANTITY = 999;

    private function __construct(private int $value)
    {
        if ($value < self::MIN_QUANTITY) {
            throw new InvalidArgumentException(
                sprintf('Quantity must be at least %d, %d given', self::MIN_QUANTITY, $value)
            );
        }

        if ($value > self::MAX_QUANTITY) {
            throw new InvalidArgumentException(
                sprintf('Quantity cannot exceed %d, %d given', self::MAX_QUANTITY, $value)
            );
        }
    }

    public static function fromInt(int $value): self
    {
        return new self($value);
    }

    public function toInt(): int
    {
        return $this->value;
    }

    public function add(Quantity $other): self
    {
        return new self($this->value + $other->value);
    }

    public function subtract(Quantity $other): self
    {
        return new self($this->value - $other->value);
    }

    public function equals(Quantity $other): bool
    {
        return $this->value === $other->value;
    }

    public function isGreaterThan(Quantity $other): bool
    {
        return $this->value > $other->value;
    }

    public function isLessThan(Quantity $other): bool
    {
        return $this->value < $other->value;
    }
}