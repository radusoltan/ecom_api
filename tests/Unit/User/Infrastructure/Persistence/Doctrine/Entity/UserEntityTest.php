<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\Infrastructure\Persistence\Doctrine\Entity;

use App\Shared\Domain\ValueObject\Email;
use App\User\Domain\Model\User;
use App\User\Domain\ValueObject\HashedPassword;
use App\User\Domain\ValueObject\Username;
use App\User\Domain\ValueObject\UserRole;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use PHPUnit\Framework\TestCase;

final class UserEntityTest extends TestCase
{
    public function testFromDomainModelRoundtrip(): void
    {
        $user = User::create(
            Email::fromString('admin@example.com'),
            Username::fromString('admin_user'),
            HashedPassword::fromHash('$2y$13$abcdefghij1234567890ABCDEFGHIJklmnopqrstuvwxyz012345678'),
            [UserRole::admin()],
        );

        $entity = UserEntity::fromDomainModel($user);

        self::assertSame($user->id()->toString(), $entity->getId());
        self::assertSame('admin@example.com', $entity->getEmail());
        self::assertSame('admin_user', $entity->getUsername());
        self::assertSame('$2y$13$abcdefghij1234567890ABCDEFGHIJklmnopqrstuvwxyz012345678', $entity->getPassword());
        self::assertContains('ROLE_ADMIN', $entity->getRoles());
        self::assertContains('ROLE_USER', $entity->getRoles());
        self::assertFalse($entity->isMfaEnabled());
        self::assertNull($entity->getTotpSecret());
        self::assertSame([], $entity->getBackupCodes());
        self::assertNull($entity->getMfaEnabledAt());
        self::assertNull($entity->getPreferredLocale());
        self::assertInstanceOf(\DateTimeImmutable::class, $entity->getCreatedAt());

        // UserInterface
        self::assertSame('admin@example.com', $entity->getUserIdentifier());

        // Roundtrip
        $restored = $entity->toDomainModel();
        self::assertTrue($restored->id()->equals($user->id()));
        self::assertSame('admin@example.com', $restored->email()->toString());
    }

    public function testSettersAndGetters(): void
    {
        $entity = new UserEntity();
        $entity->setId('user-001');
        $entity->setEmail('test@example.com');
        $entity->setUsername('tester');
        $entity->setPassword('hashed_pw');
        $entity->setRoles(['ROLE_ADMIN']);
        $entity->setCreatedAt(new \DateTimeImmutable());
        $entity->setMfaEnabled(true);
        $entity->setTotpSecret('JBSWY3DPEHPK3PXP');
        $entity->setBackupCodes(['code1', 'code2']);
        $entity->setMfaEnabledAt(new \DateTimeImmutable());
        $entity->setPreferredLocale('de');
        $entity->setEmailBlindIndex('email-idx');
        $entity->setUsernameBlindIndex('username-idx');

        self::assertSame('user-001', $entity->getId());
        self::assertSame('test@example.com', $entity->getEmail());
        self::assertSame('tester', $entity->getUsername());
        self::assertSame('hashed_pw', $entity->getPassword());
        self::assertContains('ROLE_ADMIN', $entity->getRoles());
        self::assertTrue($entity->isMfaEnabled());
        self::assertSame('JBSWY3DPEHPK3PXP', $entity->getTotpSecret());
        self::assertSame(['code1', 'code2'], $entity->getBackupCodes());
        self::assertInstanceOf(\DateTimeImmutable::class, $entity->getMfaEnabledAt());
        self::assertSame('de', $entity->getPreferredLocale());
        self::assertSame('email-idx', $entity->getEmailBlindIndex());
        self::assertSame('username-idx', $entity->getUsernameBlindIndex());
    }
}
