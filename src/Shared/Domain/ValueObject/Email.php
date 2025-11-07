<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

final readonly class Email implements \Stringable
{
    private function __construct(private string $value)
    {
        if (strlen($value) > 255) {
            throw new \InvalidArgumentException('Email address cannot exceed 255 characters');
        }

        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException(sprintf('Invalid email address: "%s"', $value));
        }
    }

    public static function fromString(string $value): self
    {
        return new self(strtolower(trim($value)));
    }

    public function value(): string
    {
        return $this->value;
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(Email $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
