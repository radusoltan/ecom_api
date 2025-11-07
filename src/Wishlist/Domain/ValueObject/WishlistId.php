<?php

declare(strict_types=1);

namespace App\Wishlist\Domain\ValueObject;

use Symfony\Component\Uid\Uuid;

/**
 * Value object for Wishlist ID.
 */
final readonly class WishlistId
{
    private function __construct(
        private string $value
    ) {
        if (empty($value)) {
            throw new \InvalidArgumentException('Wishlist ID cannot be empty');
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
