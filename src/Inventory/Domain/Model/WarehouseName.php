<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Model;

final readonly class WarehouseName implements \Stringable
{
    private const MIN_LENGTH = 2;
    private const MAX_LENGTH = 100;

    private function __construct(
        private string $value,
    ) {
        $this->validate();
    }

    public static function fromString(string $name): self
    {
        return new self(trim($name));
    }

    private function validate(): void
    {
        $length = mb_strlen($this->value);

        if ($length < self::MIN_LENGTH) {
            throw new \InvalidArgumentException(
                sprintf('Warehouse name must be at least %d characters long', self::MIN_LENGTH)
            );
        }

        if ($length > self::MAX_LENGTH) {
            throw new \InvalidArgumentException(
                sprintf('Warehouse name cannot exceed %d characters', self::MAX_LENGTH)
            );
        }

        if (empty(trim($this->value))) {
            throw new \InvalidArgumentException('Warehouse name cannot be empty or whitespace only');
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

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
