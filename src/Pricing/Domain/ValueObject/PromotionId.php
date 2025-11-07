<?php

declare(strict_types=1);

namespace App\Pricing\Domain\ValueObject;

use Symfony\Component\Uid\Ulid;

final readonly class PromotionId
{
    private function __construct(private string $value)
    {
        if (!Ulid::isValid($this->value)) {
            throw new \InvalidArgumentException(sprintf('Invalid PromotionId: "%s". Must be a valid ULID.', $this->value));
        }
    }

    public static function generate(): self
    {
        return new self((string) new Ulid());
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
