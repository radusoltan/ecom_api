<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Model;

use Symfony\Component\Uid\Uuid;

final readonly class StockItemId implements \Stringable
{
    private function __construct(
        private string $value,
    ) {
    }

    public static function generate(): self
    {
        return new self((string) Uuid::v7());
    }

    public static function fromString(string $id): self
    {
        if (!Uuid::isValid($id)) {
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
