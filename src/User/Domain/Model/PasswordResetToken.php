<?php

declare(strict_types=1);

namespace App\User\Domain\Model;

use App\User\Domain\ValueObject\UserId;

/**
 * PasswordResetToken Domain Model.
 *
 * Represents a secure token for password reset functionality.
 *
 * Business Rules:
 * - Tokens expire after a configurable period (typically 1 hour)
 * - Tokens can only be used once
 * - Immutable once created (markAsUsed conceptually mutates state)
 */
final class PasswordResetToken
{
    private function __construct(
        private readonly string $id,
        private readonly UserId $userId,
        private readonly string $token,
        private readonly \DateTimeImmutable $expiresAt,
        private readonly \DateTimeImmutable $createdAt,
        private ?\DateTimeImmutable $usedAt,
    ) {
    }

    public static function create(
        string $id,
        UserId $userId,
        string $token,
        \DateTimeImmutable $expiresAt,
        ?\DateTimeImmutable $createdAt = null,
    ): self {
        return new self($id, $userId, $token, $expiresAt, $createdAt ?? new \DateTimeImmutable(), null);
    }

    public static function reconstituteFromPersistence(
        string $id,
        UserId $userId,
        string $token,
        \DateTimeImmutable $expiresAt,
        \DateTimeImmutable $createdAt,
        ?\DateTimeImmutable $usedAt,
    ): self {
        return new self($id, $userId, $token, $expiresAt, $createdAt, $usedAt);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function token(): string
    {
        return $this->token;
    }

    public function expiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function usedAt(): ?\DateTimeImmutable
    {
        return $this->usedAt;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt < new \DateTimeImmutable();
    }

    public function isUsed(): bool
    {
        return null !== $this->usedAt;
    }

    public function markAsUsed(): void
    {
        $this->usedAt = new \DateTimeImmutable();
    }
}
