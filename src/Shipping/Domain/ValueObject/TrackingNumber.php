<?php

declare(strict_types=1);

namespace App\Shipping\Domain\ValueObject;

final readonly class TrackingNumber implements \Stringable
{
    private function __construct(private string $value)
    {
        $trimmed = trim($value);
        if ('' === $trimmed) {
            throw new \InvalidArgumentException('Tracking number cannot be empty');
        }
        if (strlen($trimmed) > 100) {
            throw new \InvalidArgumentException('Tracking number cannot exceed 100 characters');
        }
    }

    public static function fromString(string $value): self
    {
        return new self(trim($value));
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
