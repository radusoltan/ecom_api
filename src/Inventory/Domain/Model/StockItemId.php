<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Model;

use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\Uuid;

final readonly class StockItemId implements \Stringable
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
        // Accept both UUID (for backward compatibility with existing data) and ULID
        if (!Uuid::isValid($id) && !Ulid::isValid($id)) {
            throw new \InvalidArgumentException(sprintf('Invalid StockItemId: %s', $id));
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
