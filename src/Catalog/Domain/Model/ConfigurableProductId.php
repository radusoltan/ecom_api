<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

final readonly class ConfigurableProductId
{
    private function __construct(
        private string $value,
    ) {
        if ('' === $value) {
            throw new \InvalidArgumentException('ConfigurableProduct ID cannot be empty');
        }
    }

    public static function generate(): self
    {
        return new self(\Symfony\Component\Uid\Uuid::v7()->toString());
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function value(): string
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
