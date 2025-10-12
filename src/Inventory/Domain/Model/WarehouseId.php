<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Model;

use Symfony\Component\Uid\Ulid;

final readonly class WarehouseId implements \Stringable
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
        // Accept both ULID and UUID formats for backward compatibility
        if (!Ulid::isValid($id) && !self::isValidUuid($id)) {
            throw new \InvalidArgumentException(sprintf('Invalid WarehouseId: %s', $id));
        }

        return new self($id);
    }

    private static function isValidUuid(string $id): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id);
    }

    public static function default(): self
    {
        // Default warehouse for single-warehouse tenants
        return new self('01JABS00000000000000000000');
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
