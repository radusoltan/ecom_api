<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Model;

/**
 * WarehouseCode Value Object
 *
 * Represents a unique code identifier for a warehouse.
 * Format: 3-10 uppercase alphanumeric characters with optional hyphens (e.g., "WH001", "WH-MAIN", "NYC", "LONDON-1")
 *
 * Business Rules:
 * - Must be unique within a tenant
 * - 3-10 characters
 * - Uppercase alphanumeric (A-Z, 0-9) with optional hyphens
 * - No spaces or other special characters
 */
final readonly class WarehouseCode implements \Stringable
{
    private const MIN_LENGTH = 3;
    private const MAX_LENGTH = 10;
    private const PATTERN = '/^[A-Z0-9\-]+$/';

    private function __construct(
        private string $value,
    ) {
        $this->validate();
    }

    public static function fromString(string $code): self
    {
        return new self(strtoupper(trim($code)));
    }

    private function validate(): void
    {
        $length = strlen($this->value);

        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Warehouse code must be between %d and %d characters long',
                    self::MIN_LENGTH,
                    self::MAX_LENGTH
                )
            );
        }

        if (!preg_match(self::PATTERN, $this->value)) {
            throw new \InvalidArgumentException(
                'Warehouse code must contain only uppercase alphanumeric characters (A-Z, 0-9) and hyphens'
            );
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
