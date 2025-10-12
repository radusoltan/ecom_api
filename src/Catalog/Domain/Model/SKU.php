<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

final readonly class SKU
{
    private const PATTERN = '/^[A-Z0-9\-]{3,50}$/';

    private function __construct(
        private string $value
    ) {
        if (!preg_match(self::PATTERN, $value)) {
            throw new \InvalidArgumentException(
                'SKU must match pattern: uppercase letters, numbers, and hyphens, 3-50 chars'
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
