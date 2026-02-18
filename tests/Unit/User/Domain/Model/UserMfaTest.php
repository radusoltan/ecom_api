<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\Domain\Model;

use App\Shared\Domain\ValueObject\Email;
use App\User\Domain\Model\User;
use App\User\Domain\ValueObject\HashedPassword;
use App\User\Domain\ValueObject\UserId;
use App\User\Domain\ValueObject\Username;
use App\User\Domain\ValueObject\UserRole;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(User::class)]
final class UserMfaTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function createUser(): User
    {
        return User::create(
            email: Email::fromString('mfauser@example.com'),
            username: Username::fromString('mfauser'),
            password: HashedPassword::fromPlainPassword('SecurePass123!'),
        );
    }

    private function createUserWithMfaEnabled(): User
    {
        $user = $this->createUser();
        $user->enableMfa(
            totpSecret: 'JBSWY3DPEHPK3PXP',
            hashedBackupCodes: [
                password_hash('AABBCCDD', PASSWORD_BCRYPT),
                password_hash('EEFF0011', PASSWORD_BCRYPT),
            ]
        );

        return $user;
    }

    // -----------------------------------------------------------------------
    // Default state
    // -----------------------------------------------------------------------

    #[Test]
    public function itHasMfaDisabledByDefault(): void
    {
        $user = $this->createUser();

        self::assertFalse($user->mfaEnabled());
        self::assertNull($user->totpSecret());
        self::assertSame([], $user->backupCodes());
        self::assertNull($user->mfaEnabledAt());
    }

    // -----------------------------------------------------------------------
    // enableMfa()
    // -----------------------------------------------------------------------

    #[Test]
    public function itEnablesMfaWithSecretAndBackupCodes(): void
    {
        $user = $this->createUser();
        $secret = 'JBSWY3DPEHPK3PXP';
        $hashedCodes = [
            password_hash('AABBCCDD', PASSWORD_BCRYPT),
            password_hash('EEFF0011', PASSWORD_BCRYPT),
        ];

        $before = new \DateTimeImmutable();
        $user->enableMfa($secret, $hashedCodes);
        $after = new \DateTimeImmutable();

        self::assertTrue($user->mfaEnabled());
        self::assertSame($secret, $user->totpSecret());
        self::assertSame($hashedCodes, $user->backupCodes());
        self::assertNotNull($user->mfaEnabledAt());
        self::assertGreaterThanOrEqual($before->getTimestamp(), $user->mfaEnabledAt()->getTimestamp());
        self::assertLessThanOrEqual($after->getTimestamp(), $user->mfaEnabledAt()->getTimestamp());
    }

    #[Test]
    public function itThrowsWhenEnablingMfaThatIsAlreadyEnabled(): void
    {
        $user = $this->createUserWithMfaEnabled();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('MFA is already enabled');

        $user->enableMfa('ANOTHERSECRET', []);
    }

    // -----------------------------------------------------------------------
    // disableMfa()
    // -----------------------------------------------------------------------

    #[Test]
    public function itDisablesMfaAndClearsAllMfaFields(): void
    {
        $user = $this->createUserWithMfaEnabled();

        $user->disableMfa();

        self::assertFalse($user->mfaEnabled());
        self::assertNull($user->totpSecret());
        self::assertSame([], $user->backupCodes());
        self::assertNull($user->mfaEnabledAt());
    }

    #[Test]
    public function itThrowsWhenDisablingMfaThatIsNotEnabled(): void
    {
        $user = $this->createUser();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('MFA is not enabled');

        $user->disableMfa();
    }

    // -----------------------------------------------------------------------
    // consumeBackupCode()
    // -----------------------------------------------------------------------

    #[Test]
    public function itConsumesValidBackupCodeAndRemovesIt(): void
    {
        $codeToConsume = password_hash('AABBCCDD', PASSWORD_BCRYPT);
        $user = User::reconstitute(
            id: UserId::generate(),
            email: Email::fromString('mfa@example.com'),
            username: Username::fromString('mfauser2'),
            password: HashedPassword::fromPlainPassword('SecurePass123!'),
            roles: [UserRole::user()],
            createdAt: new \DateTimeImmutable(),
            mfaEnabled: true,
            totpSecret: 'JBSWY3DPEHPK3PXP',
            backupCodes: [
                $codeToConsume,
                password_hash('EEFF0011', PASSWORD_BCRYPT),
            ],
            mfaEnabledAt: new \DateTimeImmutable(),
        );

        $result = $user->consumeBackupCode($codeToConsume);

        self::assertTrue($result);
        self::assertCount(1, $user->backupCodes());
        self::assertNotContains($codeToConsume, $user->backupCodes());
    }

    #[Test]
    public function itReturnsFalseWhenBackupCodeDoesNotMatch(): void
    {
        $user = User::reconstitute(
            id: UserId::generate(),
            email: Email::fromString('mfa@example.com'),
            username: Username::fromString('mfauser3'),
            password: HashedPassword::fromPlainPassword('SecurePass123!'),
            roles: [UserRole::user()],
            createdAt: new \DateTimeImmutable(),
            mfaEnabled: true,
            totpSecret: 'JBSWY3DPEHPK3PXP',
            backupCodes: [
                password_hash('AABBCCDD', PASSWORD_BCRYPT),
            ],
            mfaEnabledAt: new \DateTimeImmutable(),
        );

        $result = $user->consumeBackupCode('TOTALLY_WRONG_HASH');

        self::assertFalse($result);
        self::assertCount(1, $user->backupCodes());
    }

    #[Test]
    public function itOnlyConsumesOneCodeWhenMultipleExist(): void
    {
        $hash1 = password_hash('AABBCCDD', PASSWORD_BCRYPT);
        $hash2 = password_hash('EEFF0011', PASSWORD_BCRYPT);
        $user = User::reconstitute(
            id: UserId::generate(),
            email: Email::fromString('mfa@example.com'),
            username: Username::fromString('mfauser4'),
            password: HashedPassword::fromPlainPassword('SecurePass123!'),
            roles: [UserRole::user()],
            createdAt: new \DateTimeImmutable(),
            mfaEnabled: true,
            totpSecret: 'JBSWY3DPEHPK3PXP',
            backupCodes: [$hash1, $hash2],
            mfaEnabledAt: new \DateTimeImmutable(),
        );

        $user->consumeBackupCode($hash1);

        self::assertCount(1, $user->backupCodes());
        self::assertContains($hash2, $user->backupCodes());
    }

    // -----------------------------------------------------------------------
    // regenerateBackupCodes()
    // -----------------------------------------------------------------------

    #[Test]
    public function itReplacesBackupCodesWithNewOnes(): void
    {
        $user = $this->createUserWithMfaEnabled();
        $newHashedCodes = [
            password_hash('NEWCODE1', PASSWORD_BCRYPT),
            password_hash('NEWCODE2', PASSWORD_BCRYPT),
            password_hash('NEWCODE3', PASSWORD_BCRYPT),
        ];

        $user->regenerateBackupCodes($newHashedCodes);

        self::assertSame($newHashedCodes, $user->backupCodes());
        self::assertCount(3, $user->backupCodes());
    }

    #[Test]
    public function itThrowsWhenRegeneratingBackupCodesWithMfaDisabled(): void
    {
        $user = $this->createUser();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot regenerate backup codes when MFA is disabled');

        $user->regenerateBackupCodes([password_hash('SOMECODE', PASSWORD_BCRYPT)]);
    }

    // -----------------------------------------------------------------------
    // reconstitute() with MFA fields
    // -----------------------------------------------------------------------

    #[Test]
    public function itReconstitutesMfaEnabledUserFromPersistence(): void
    {
        $userId = UserId::generate();
        $secret = 'JBSWY3DPEHPK3PXP';
        $codes = [password_hash('AABBCCDD', PASSWORD_BCRYPT)];
        $enabledAt = new \DateTimeImmutable('2024-06-01 12:00:00');

        $user = User::reconstitute(
            id: $userId,
            email: Email::fromString('mfa@example.com'),
            username: Username::fromString('mfauser5'),
            password: HashedPassword::fromPlainPassword('SecurePass123!'),
            roles: [UserRole::user()],
            createdAt: new \DateTimeImmutable(),
            mfaEnabled: true,
            totpSecret: $secret,
            backupCodes: $codes,
            mfaEnabledAt: $enabledAt,
        );

        self::assertTrue($user->mfaEnabled());
        self::assertSame($secret, $user->totpSecret());
        self::assertSame($codes, $user->backupCodes());
        self::assertEquals($enabledAt, $user->mfaEnabledAt());
    }
}
