<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

use App\Catalog\Domain\Exception\InvalidProductNameException;

/**
 * Product Name Value Object
 *
 * Business Rules:
 * - min_length: 3 characters
 * - max_length: 255 characters
 * - Cannot be empty or whitespace only
 * - Trims leading/trailing whitespace
 * - Normalizes multiple consecutive spaces to single space
 */
final readonly class ProductName implements \Stringable
{
    private const MIN_LENGTH = 3;
    private const MAX_LENGTH = 255;

    private function __construct(
        private string $value
    ) {}

    public static function fromString(string $value): self
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw InvalidProductNameException::empty();
        }

        // Normalize multiple spaces to single space
        $normalized = (string) preg_replace('/\s+/', ' ', $trimmed);

        $length = mb_strlen($normalized);

        if ($length < self::MIN_LENGTH) {
            throw InvalidProductNameException::tooShort($normalized, self::MIN_LENGTH);
        }

        if ($length > self::MAX_LENGTH) {
            throw InvalidProductNameException::tooLong($normalized, self::MAX_LENGTH);
        }

        return new self($normalized);
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
