<?php

declare(strict_types=1);

namespace App\Review\Domain\ValueObject;

/**
 * Value object for product rating (1-5 stars).
 */
final readonly class Rating
{
    private const MIN_RATING = 1;
    private const MAX_RATING = 5;

    private function __construct(
        private int $value,
    ) {
        $this->validate($value);
    }

    public static function fromInt(int $value): self
    {
        return new self($value);
    }

    public function value(): int
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    private function validate(int $value): void
    {
        if ($value < self::MIN_RATING || $value > self::MAX_RATING) {
            throw new \InvalidArgumentException(sprintf('Rating must be between %d and %d', self::MIN_RATING, self::MAX_RATING));
        }
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
