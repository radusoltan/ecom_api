<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\Infrastructure\Persistence\Doctrine\Entity;

use App\User\Domain\Model\PasswordResetToken;
use App\User\Domain\ValueObject\UserId;
use App\User\Infrastructure\Persistence\Doctrine\Entity\PasswordResetTokenEntity;
use PHPUnit\Framework\TestCase;

final class PasswordResetTokenEntityTest extends TestCase
{
    public function testFromDomainModelRoundtrip(): void
    {
        $id = '00000000-0000-4000-8000-000000000001';
        $userId = UserId::generate();
        $token = bin2hex(random_bytes(32));
        $expiresAt = new \DateTimeImmutable('+1 hour');
        $createdAt = new \DateTimeImmutable();

        $domainToken = PasswordResetToken::reconstituteFromPersistence(
            id: $id,
            userId: $userId,
            token: $token,
            expiresAt: $expiresAt,
            createdAt: $createdAt,
            usedAt: null,
        );

        $entity = PasswordResetTokenEntity::fromDomainModel($domainToken);

        self::assertSame($id, $entity->getId());
        self::assertSame($userId->toString(), $entity->getUserId());
        self::assertSame($token, $entity->getToken());
        self::assertSame($expiresAt, $entity->getExpiresAt());
        self::assertSame($createdAt, $entity->getCreatedAt());
        self::assertNull($entity->getUsedAt());
        self::assertFalse($entity->isUsed());

        // Roundtrip
        $restored = $entity->toDomainModel();
        self::assertSame($id, $restored->id());
        self::assertSame($userId->toString(), $restored->userId()->toString());
        self::assertSame($token, $restored->token());
        self::assertSame($expiresAt, $restored->expiresAt());
        self::assertSame($createdAt, $restored->createdAt());
        self::assertNull($restored->usedAt());
        self::assertFalse($restored->isUsed());
    }

    public function testFromDomainModelRoundtripWithUsedAt(): void
    {
        $id = '00000000-0000-4000-8000-000000000002';
        $userId = UserId::generate();
        $token = bin2hex(random_bytes(32));
        $expiresAt = new \DateTimeImmutable('+1 hour');
        $createdAt = new \DateTimeImmutable('-30 minutes');
        $usedAt = new \DateTimeImmutable('-5 minutes');

        $domainToken = PasswordResetToken::reconstituteFromPersistence(
            id: $id,
            userId: $userId,
            token: $token,
            expiresAt: $expiresAt,
            createdAt: $createdAt,
            usedAt: $usedAt,
        );

        $entity = PasswordResetTokenEntity::fromDomainModel($domainToken);

        self::assertSame($usedAt, $entity->getUsedAt());
        self::assertTrue($entity->isUsed());

        $restored = $entity->toDomainModel();
        self::assertSame($usedAt, $restored->usedAt());
        self::assertTrue($restored->isUsed());
    }

    public function testIsExpiredReturnsTrueForPastExpiry(): void
    {
        $id = '00000000-0000-4000-8000-000000000003';
        $userId = UserId::generate();
        $expiresAt = new \DateTimeImmutable('-1 hour');
        $createdAt = new \DateTimeImmutable('-2 hours');

        $domainToken = PasswordResetToken::reconstituteFromPersistence(
            id: $id,
            userId: $userId,
            token: 'sometoken123',
            expiresAt: $expiresAt,
            createdAt: $createdAt,
            usedAt: null,
        );

        $entity = PasswordResetTokenEntity::fromDomainModel($domainToken);
        self::assertTrue($entity->isExpired());
    }

    public function testIsExpiredReturnsFalseForFutureExpiry(): void
    {
        $id = '00000000-0000-4000-8000-000000000004';
        $userId = UserId::generate();
        $expiresAt = new \DateTimeImmutable('+2 hours');
        $createdAt = new \DateTimeImmutable();

        $domainToken = PasswordResetToken::reconstituteFromPersistence(
            id: $id,
            userId: $userId,
            token: 'futuretoken456',
            expiresAt: $expiresAt,
            createdAt: $createdAt,
            usedAt: null,
        );

        $entity = PasswordResetTokenEntity::fromDomainModel($domainToken);
        self::assertFalse($entity->isExpired());
    }

    public function testMarkAsUsedSetsUsedAt(): void
    {
        $entity = new PasswordResetTokenEntity(
            id: '00000000-0000-4000-8000-000000000005',
            userId: UserId::generate()->toString(),
            token: 'markusedtoken789',
            expiresAt: new \DateTimeImmutable('+1 hour'),
            createdAt: new \DateTimeImmutable(),
            usedAt: null,
        );

        self::assertFalse($entity->isUsed());
        self::assertNull($entity->getUsedAt());

        $entity->markAsUsed();

        self::assertTrue($entity->isUsed());
        self::assertInstanceOf(\DateTimeImmutable::class, $entity->getUsedAt());
    }

    public function testConstructorSetsAllFields(): void
    {
        $id = '00000000-0000-4000-8000-000000000006';
        $userId = '00000000-0000-4000-8000-000000000099';
        $token = 'constructortoken';
        $expiresAt = new \DateTimeImmutable('+1 hour');
        $createdAt = new \DateTimeImmutable();
        $usedAt = new \DateTimeImmutable();

        $entity = new PasswordResetTokenEntity(
            id: $id,
            userId: $userId,
            token: $token,
            expiresAt: $expiresAt,
            createdAt: $createdAt,
            usedAt: $usedAt,
        );

        self::assertSame($id, $entity->getId());
        self::assertSame($userId, $entity->getUserId());
        self::assertSame($token, $entity->getToken());
        self::assertSame($expiresAt, $entity->getExpiresAt());
        self::assertSame($createdAt, $entity->getCreatedAt());
        self::assertSame($usedAt, $entity->getUsedAt());
    }
}
