<?php

declare(strict_types=1);

namespace App\User\Domain\ValueObject;

use InvalidArgumentException;
use Stringable;

final readonly class HashedPassword implements Stringable
{
    private function __construct(
        private string $value
    ) {
        if (trim($value) === '' || trim($value) === '0') {
            throw new InvalidArgumentException('Hashed password cannot be empty');
        }

        // Basic validation - bcrypt hashes are 60 characters
        if (strlen($value) < 20) {
            throw new InvalidArgumentException('Invalid hashed password format');
        }
    }

    public static function fromHash(string $hash): self
    {
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
