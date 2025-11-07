<?php

declare(strict_types=1);

namespace App\User\Domain\ValueObject;

final readonly class Username implements \Stringable
{
    private const MIN_LENGTH = 3;
    private const MAX_LENGTH = 50;

    private function __construct(
        private string $value
    ) {
        $trimmed = trim($value);

        if ('' === $trimmed || '0' === $trimmed) {
            throw new \InvalidArgumentException('Username cannot be empty');
        }

        if (strlen($trimmed) < self::MIN_LENGTH) {
            throw new \InvalidArgumentException(sprintf('Username must be at least %d characters long', self::MIN_LENGTH));
        }

        if (strlen($trimmed) > self::MAX_LENGTH) {
            throw new \InvalidArgumentException(sprintf('Username cannot exceed %d characters', self::MAX_LENGTH));
        }

        // Allow alphanumeric, underscore, hyphen
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $trimmed)) {
            throw new \InvalidArgumentException('Username can only contain alphanumeric characters, underscore and hyphen');
        }
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function toString(): string
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
