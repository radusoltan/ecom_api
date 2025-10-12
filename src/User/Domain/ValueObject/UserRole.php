<?php

declare(strict_types=1);

namespace App\User\Domain\ValueObject;

use InvalidArgumentException;
use Stringable;

final readonly class UserRole implements Stringable
{
    public const ROLE_USER = 'ROLE_USER';
    public const ROLE_ADMIN = 'ROLE_ADMIN';
    public const ROLE_SUPER_ADMIN = 'ROLE_SUPER_ADMIN';

    private const VALID_ROLES = [
        self::ROLE_USER,
        self::ROLE_ADMIN,
        self::ROLE_SUPER_ADMIN,
    ];

    private function __construct(
        private string $value
    ) {
        if (!in_array($value, self::VALID_ROLES, true)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid user role: "%s". Must be one of: %s',
                    $value,
                    implode(', ', self::VALID_ROLES)
                )
            );
        }
    }

    public static function user(): self
    {
        return new self(self::ROLE_USER);
    }

    public static function admin(): self
    {
        return new self(self::ROLE_ADMIN);
    }

    public static function superAdmin(): self
    {
        return new self(self::ROLE_SUPER_ADMIN);
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

    public function isSuperAdmin(): bool
    {
        return $this->value === self::ROLE_SUPER_ADMIN;
    }

    public function isAdmin(): bool
    {
        return in_array($this->value, [self::ROLE_ADMIN, self::ROLE_SUPER_ADMIN], true);
    }
}
