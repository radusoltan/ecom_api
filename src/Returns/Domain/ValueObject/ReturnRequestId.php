<?php

declare(strict_types=1);

namespace App\Returns\Domain\ValueObject;

use Symfony\Component\Uid\Uuid;

/**
 * Value Object representing a unique identifier for a ReturnRequest.
 *
 * Uses UUID v7 (time-ordered) for:
 * - Time-ordered IDs (better for database indexes)
 * - 128-bit uniqueness
 * - Native PostgreSQL uuid type support
 */
final readonly class ReturnRequestId
{
    private function __construct(
        private string $value,
    ) {
    }

    public static function generate(): self
    {
        return new self((string) Uuid::v7());
    }

    public static function fromString(string $value): self
    {
        if (!Uuid::isValid($value)) {
            throw new \InvalidArgumentException(sprintf('Invalid ReturnRequestId: "%s". Must be a valid UUID.', $value));
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
