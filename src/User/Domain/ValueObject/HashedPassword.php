<?php

declare(strict_types=1);

namespace App\User\Domain\ValueObject;

final readonly class HashedPassword implements \Stringable
{
    private function __construct(
        private string $value,
    ) {
        if ('' === trim($value) || '0' === trim($value)) {
            throw new \InvalidArgumentException('Hashed password cannot be empty');
        }

        // Basic validation - bcrypt hashes are 60 characters
        if (strlen($value) < 20) {
            throw new \InvalidArgumentException('Invalid hashed password format');
        }
    }

    public static function fromHash(string $hash): self
    {
        return new self($hash);
    }

    /**
     * Create a hashed password from a plain password.
     * Uses bcrypt hashing algorithm.
     */
    public static function fromPlainPassword(string $plainPassword): self
    {
        if ('' === trim($plainPassword) || '0' === trim($plainPassword)) {
            throw new \InvalidArgumentException('Plain password cannot be empty');
        }

        if (strlen($plainPassword) < 8) {
            throw new \InvalidArgumentException('Password must be at least 8 characters long');
        }

        // password_hash() always returns non-empty-string in PHP 8.3 (never null or false)
        $hash = password_hash($plainPassword, PASSWORD_BCRYPT);

        return new self($hash);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
