<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Model;

/**
 * Value Object representing a PriceList name.
 *
 * Business Rules:
 * - min_length: 3
 * - max_length: 100
 * - must_not_be_empty: true
 * - trimmed: true
 */
final readonly class PriceListName
{
    private const MIN_LENGTH = 3;
    private const MAX_LENGTH = 100;

    private function __construct(
        private string $value,
    ) {
        $trimmed = trim($this->value);

        if (empty($trimmed)) {
            throw new \InvalidArgumentException('PriceList name cannot be empty');
        }

        $length = mb_strlen($trimmed);

        if ($length < self::MIN_LENGTH) {
            throw new \InvalidArgumentException(sprintf('PriceList name must be at least %d characters long, got %d', self::MIN_LENGTH, $length));
        }

        if ($length > self::MAX_LENGTH) {
            throw new \InvalidArgumentException(sprintf('PriceList name cannot exceed %d characters, got %d', self::MAX_LENGTH, $length));
        }
    }

    public static function fromString(string $value): self
    {
        return new self(trim($value));
    }

    public function value(): string
    {
        return $this->value;
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
