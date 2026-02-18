<?php

declare(strict_types=1);

namespace App\Returns\Domain\ValueObject;

use Symfony\Component\Uid\Ulid;

/**
 * Value Object representing a unique identifier for a ReturnRequest.
 *
 * Uses ULID (Universally Unique Lexicographically Sortable Identifier) for:
 * - Time-ordered IDs (better for database indexes)
 * - 128-bit uniqueness
 * - URL-safe representation
 */
final readonly class ReturnRequestId
{
    private function __construct(
        private string $value,
    ) {
    }

    public static function generate(): self
    {
        return new self((string) new Ulid());
    }

    public static function fromString(string $value): self
    {
        if (!Ulid::isValid($value)) {
            throw new \InvalidArgumentException(sprintf('Invalid ReturnRequestId: "%s". Must be a valid ULID.', $value));
        }

        return new self($value);
    }

    public function toString(): string
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
