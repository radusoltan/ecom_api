<?php

declare(strict_types=1);

namespace App\Cart\Domain\Model;

use Stringable;
use InvalidArgumentException;
use Symfony\Component\Uid\Ulid;

/**
 * CartItemId Value Object
 *
 * Uses ULID for sortable, time-based unique identifiers
 */
final readonly class CartItemId implements Stringable
{
    private function __construct(private string $value)
    {
        if ($value === '' || $value === '0') {
            throw new InvalidArgumentException('CartItemId cannot be empty');
        }

        if (!Ulid::isValid($value)) {
            throw new InvalidArgumentException(sprintf('Invalid CartItemId format: "%s". Must be a valid ULID', $value));
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

    public function equals(CartItemId $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}