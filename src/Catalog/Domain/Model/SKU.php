<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

final readonly class SKU
{
    private const PATTERN = '/^[A-Z]{3}-[A-Z]{3}-[0-9]{6}$/';

    private function __construct(
        private string $value
    ) {
        if (!preg_match(self::PATTERN, $value)) {
            throw new \InvalidArgumentException(
                'SKU must match pattern: AAA-BBB-000000 (upper-case letters and 6 digits)'
            );
        }
    }

    public static function fromString(string $value): self
    {
        return new self(strtoupper(trim($value)));
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(SKU $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
