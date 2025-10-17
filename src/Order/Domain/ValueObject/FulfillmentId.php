<?php

declare(strict_types=1);

namespace App\Order\Domain\ValueObject;

use Symfony\Component\Uid\Ulid;

/**
 * Fulfillment ID Value Object
 *
 * Unique identifier for a fulfillment process.
 * Uses ULID for time-based sortable identifiers.
 */
final readonly class FulfillmentId implements \Stringable
{
    private function __construct(
        private string $value,
    ) {}

    public static function generate(): self
    {
        return new self((string) new Ulid());
    }

    public static function fromString(string $id): self
    {
        if (!Ulid::isValid($id)) {
            throw new \InvalidArgumentException(sprintf('Invalid FulfillmentId: %s', $id));
        }

        return new self($id);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
