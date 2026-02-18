<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

final readonly class Slug
{
    private function __construct(
        private string $value,
    ) {
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value)) {
            throw new \InvalidArgumentException('Invalid slug format');
        }
    }

    public static function fromString(string $value): self
    {
        $trimmed = trim($value);
        if ('' === $trimmed) {
            throw new \InvalidArgumentException('Slug cannot be empty');
        }

        return new self(strtolower($trimmed));
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(Slug $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
