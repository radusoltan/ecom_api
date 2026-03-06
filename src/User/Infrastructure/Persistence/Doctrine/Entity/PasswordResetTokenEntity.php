<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Persistence\Doctrine\Entity;

use App\User\Domain\Model\PasswordResetToken;
use App\User\Domain\ValueObject\UserId;
use Doctrine\ORM\Mapping as ORM;

/**
 * Password Reset Token Entity.
 *
 * Stores secure tokens for password reset functionality.
 * Tokens expire after 1 hour and can only be used once.
 */
#[ORM\Entity]
#[ORM\Table(name: 'password_reset_tokens')]
#[ORM\Index(name: 'idx_token', columns: ['token'])]
#[ORM\Index(name: 'idx_user_id', columns: ['user_id'])]
#[ORM\Index(name: 'idx_expires_at', columns: ['expires_at'])]
class PasswordResetTokenEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private string $id;

    #[ORM\Column(name: 'user_id', type: 'uuid')]
    private string $userId;

    #[ORM\Column(type: 'string', length: 64, unique: true)]
    private string $token;

    #[ORM\Column(name: 'expires_at', type: 'datetimetz_immutable')]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(name: 'used_at', type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $usedAt = null;

    #[ORM\Column(name: 'created_at', type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $id,
        string $userId,
        string $token,
        \DateTimeImmutable $expiresAt,
        \DateTimeImmutable $createdAt,
        ?\DateTimeImmutable $usedAt = null,
    ) {
        $this->id = $id;
        $this->userId = $userId;
        $this->token = $token;
        $this->expiresAt = $expiresAt;
        $this->createdAt = $createdAt;
        $this->usedAt = $usedAt;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getUsedAt(): ?\DateTimeImmutable
    {
        return $this->usedAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
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

    /**
     * Creates a Doctrine entity from a PasswordResetToken domain model.
     */
    public static function fromDomainModel(PasswordResetToken $token): self
    {
        return new self(
            id: $token->id(),
            userId: $token->userId()->toString(),
            token: $token->token(),
            expiresAt: $token->expiresAt(),
            createdAt: $token->createdAt(),
            usedAt: $token->usedAt(),
        );
    }

    /**
     * Converts this entity back to a PasswordResetToken domain model.
     */
    public function toDomainModel(): PasswordResetToken
    {
        return PasswordResetToken::reconstituteFromPersistence(
            id: $this->id,
            userId: UserId::fromString($this->userId),
            token: $this->token,
            expiresAt: $this->expiresAt,
            createdAt: $this->createdAt,
            usedAt: $this->usedAt,
        );
    }
}
