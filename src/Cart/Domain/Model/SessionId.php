<?php

declare(strict_types=1);

namespace App\Cart\Domain\Model;

use Symfony\Component\Uid\Uuid;

/**
 * SessionId Value Object.
 *
 * Represents a guest session for cart tracking
 * Uses UUID v4 for anonymous session identification
 */
final readonly class SessionId implements \Stringable
{
    private function __construct(private string $value)
    {
        if ('' === $value || '0' === $value) {
            throw new \InvalidArgumentException('SessionId cannot be empty');
        }

        if (!Uuid::isValid($value)) {
            throw new \InvalidArgumentException(sprintf('Invalid SessionId format: "%s". Must be a valid UUID', $value));
        }
    }

    public static function generate(): self
    {
        return new self(Uuid::v4()->toString());
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(SessionId $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
