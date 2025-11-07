<?php

declare(strict_types=1);

namespace App\Cart\Domain\Model;

use Symfony\Component\Uid\Ulid;

/**
 * CartId Value Object.
 *
 * Uses ULID for sortable, time-based unique identifiers
 */
final readonly class CartId implements \Stringable
{
    private function __construct(private string $value)
    {
        if ('' === $value || '0' === $value) {
            throw new \InvalidArgumentException('CartId cannot be empty');
        }

        // Validate ULID format (26 characters, base32 encoded)
        if (!Ulid::isValid($value)) {
            throw new \InvalidArgumentException(sprintf('Invalid CartId format: "%s". Must be a valid ULID', $value));
        }
    }

    public static function generate(): self
    {
        return new self((string) new Ulid());
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(CartId $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
