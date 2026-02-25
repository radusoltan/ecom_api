<?php

declare(strict_types=1);

namespace App\Pricing\Domain\ValueObject;

use Symfony\Component\Uid\Uuid;

final readonly class PromotionId
{
    private function __construct(private string $value)
    {
        if (!Uuid::isValid($this->value)) {
            throw new \InvalidArgumentException(sprintf('Invalid PromotionId: "%s". Must be a valid UUID.', $this->value));
        }
    }

    public static function generate(): self
    {
        return new self((string) Uuid::v7());
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
}
