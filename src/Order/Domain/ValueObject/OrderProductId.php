<?php

declare(strict_types=1);

namespace App\Order\Domain\ValueObject;

use Symfony\Component\Uid\Uuid;

/**
 * Product identifier within the Order bounded context.
 *
 * Anti-Corruption Layer value object that decouples Order from Catalog.
 * Represents a product reference without importing Catalog domain types.
 */
final readonly class OrderProductId
{
    private function __construct(
        private string $value,
    ) {
    }

    public static function generate(): self
    {
        return new self(Uuid::v7()->toString());
    }

    public static function fromString(string $value): self
    {
        if (!Uuid::isValid($value)) {
            throw new \InvalidArgumentException('Invalid OrderProductId');
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
