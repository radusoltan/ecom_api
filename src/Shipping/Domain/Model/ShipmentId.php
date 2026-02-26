<?php

declare(strict_types=1);

namespace App\Shipping\Domain\Model;

use Symfony\Component\Uid\Uuid;

final readonly class ShipmentId implements \Stringable
{
    private function __construct(private string $value)
    {
        if ('' === $value || '0' === $value) {
            throw new \InvalidArgumentException('ShipmentId cannot be empty');
        }

        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value)) {
            throw new \InvalidArgumentException(sprintf('Invalid ShipmentId format: "%s"', $value));
        }
    }

    public static function generate(): self
    {
        return new self((string) Uuid::v4());
    }

    public static function fromString(string $value): self
    {
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
