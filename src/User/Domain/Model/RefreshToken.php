<?php

declare(strict_types=1);

namespace App\User\Domain\Model;

/**
 * RefreshToken Domain Model.
 *
 * Represents a JWT refresh token for user session management.
 *
 * Business Rules:
 * - Tokens have an expiration date
 * - Tokens are bound to a specific user (by username/email)
 * - Tokens can only be used while valid (not expired)
 */
final class RefreshToken
{
    private function __construct(
        private readonly int|string|null $id,
        private readonly string $refreshToken,
        private readonly string $username,
        private readonly \DateTimeInterface $valid,
    ) {
    }

    public static function create(
        string $refreshToken,
        string $username,
        \DateTimeInterface $valid,
    ): self {
        return new self(null, $refreshToken, $username, $valid);
    }

    public static function reconstituteFromPersistence(
        int|string|null $id,
        string $refreshToken,
        string $username,
        \DateTimeInterface $valid,
    ): self {
        return new self($id, $refreshToken, $username, $valid);
    }

    public function id(): int|string|null
    {
        return $this->id;
    }

    public function refreshToken(): string
    {
        return $this->refreshToken;
    }

    public function username(): string
    {
        return $this->username;
    }

    public function valid(): \DateTimeInterface
    {
        return $this->valid;
    }

    public function isValid(): bool
    {
        return $this->valid >= new \DateTimeImmutable();
    }
}
