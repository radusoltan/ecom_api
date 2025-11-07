<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\Domain\Model;

use App\Shared\Domain\ValueObject\Email;
use App\User\Domain\Event\UserAccountLocked;
use App\User\Domain\Event\UserAccountUnlocked;
use App\User\Domain\Event\UserCreated;
use App\User\Domain\Event\UserEmailVerified;
use App\User\Domain\Event\UserPasswordChanged;
use App\User\Domain\Event\UserRoleAdded;
use App\User\Domain\Event\UserRoleRemoved;
use App\User\Domain\Model\User;
use App\User\Domain\ValueObject\HashedPassword;
use App\User\Domain\ValueObject\UserId;
use App\User\Domain\ValueObject\UserRole;
use App\User\Domain\ValueObject\Username;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    #[Test]
    public function it_creates_user_with_minimum_required_fields(): void
    {
        $email = Email::fromString('john.doe@example.com');
        $username = Username::fromString('johndoe');
        $password = HashedPassword::fromPlainPassword('SecurePass123!');

        $user = User::create($email, $username, $password);

        $this->assertInstanceOf(User::class, $user);
        $this->assertInstanceOf(UserId::class, $user->id());
        $this->assertEquals($email, $user->email());
        $this->assertEquals($username, $user->username());
        $this->assertCount(1, $user->roles());
        $this->assertTrue($user->hasRole(UserRole::user()));
        $this->assertFalse($user->emailVerified());
        $this->assertNull($user->emailVerifiedAt());
        $this->assertFalse($user->isLocked());
        $this->assertNull($user->lockReason());
        $this->assertNull($user->lockedAt());
    }

    #[Test]
    public function it_creates_user_with_multiple_roles(): void
    {
        $email = Email::fromString('admin@example.com');
        $username = Username::fromString('admin');
        $password = HashedPassword::fromPlainPassword('AdminPass123!');
        $roles = [UserRole::admin(), UserRole::manager()];

        $user = User::create($email, $username, $password, $roles);

        $this->assertCount(2, $user->roles());
        $this->assertTrue($user->hasRole(UserRole::admin()));
        $this->assertTrue($user->hasRole(UserRole::manager()));
    }

    #[Test]
    public function it_records_user_created_event_on_creation(): void
    {
        $email = Email::fromString('test@example.com');
        $username = Username::fromString('testuser');
        $password = HashedPassword::fromPlainPassword('TestPass123!');

        $user = User::create($email, $username, $password);

        $events = $user->popEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(UserCreated::class, $events[0]);
        $this->assertEquals($user->id(), $events[0]->userId());
        $this->assertEquals($email->toString(), $events[0]->email());
        $this->assertEquals($username->toString(), $events[0]->username());
    }

    #[Test]
    public function it_changes_password_successfully(): void
    {
        $user = User::create(
            Email::fromString('user@example.com'),
            Username::fromString('user123'),
            HashedPassword::fromPlainPassword('OldPass123!')
        );
        $user->popEvents(); // Clear creation event

        $newPassword = HashedPassword::fromPlainPassword('NewPass456!');
        $user->changePassword($newPassword);

        $this->assertEquals($newPassword, $user->password());

        $events = $user->popEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(UserPasswordChanged::class, $events[0]);
        $this->assertEquals($user->id(), $events[0]->userId());
    }

    #[Test]
    public function it_throws_exception_when_changing_password_for_locked_account(): void
    {
        $user = User::create(
            Email::fromString('locked@example.com'),
            Username::fromString('lockeduser'),
            HashedPassword::fromPlainPassword('Pass123!')
        );

        $user->lock('Suspicious activity');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Cannot change password for locked account');

        $user->changePassword(HashedPassword::fromPlainPassword('NewPass456!'));
    }

    #[Test]
    public function it_adds_role_successfully(): void
    {
        $user = User::create(
            Email::fromString('user@example.com'),
            Username::fromString('user123'),
            HashedPassword::fromPlainPassword('Pass123!')
        );
        $user->popEvents(); // Clear creation event

        $user->addRole(UserRole::manager());

        $this->assertCount(2, $user->roles());
        $this->assertTrue($user->hasRole(UserRole::user()));
        $this->assertTrue($user->hasRole(UserRole::manager()));

        $events = $user->popEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(UserRoleAdded::class, $events[0]);
        $this->assertEquals($user->id(), $events[0]->userId());
        $this->assertTrue($events[0]->role()->equals(UserRole::manager()));
    }

    #[Test]
    public function it_throws_exception_when_adding_duplicate_role(): void
    {
        $user = User::create(
            Email::fromString('user@example.com'),
            Username::fromString('user123'),
            HashedPassword::fromPlainPassword('Pass123!')
        );

        $user->addRole(UserRole::manager());

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('User already has this role');

        $user->addRole(UserRole::manager());
    }

    #[Test]
    public function it_removes_role_successfully(): void
    {
        $user = User::create(
            Email::fromString('admin@example.com'),
            Username::fromString('admin'),
            HashedPassword::fromPlainPassword('Pass123!'),
            [UserRole::admin(), UserRole::manager()]
        );
        $user->popEvents(); // Clear creation event

        $user->removeRole(UserRole::manager());

        $this->assertCount(1, $user->roles());
        $this->assertTrue($user->hasRole(UserRole::admin()));
        $this->assertFalse($user->hasRole(UserRole::manager()));

        $events = $user->popEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(UserRoleRemoved::class, $events[0]);
        $this->assertEquals($user->id(), $events[0]->userId());
        $this->assertTrue($events[0]->role()->equals(UserRole::manager()));
    }

    #[Test]
    public function it_throws_exception_when_removing_non_existent_role(): void
    {
        $user = User::create(
            Email::fromString('user@example.com'),
            Username::fromString('user123'),
            HashedPassword::fromPlainPassword('Pass123!')
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('User does not have this role');

        $user->removeRole(UserRole::admin());
    }

    #[Test]
    public function it_throws_exception_when_removing_last_role_user(): void
    {
        $user = User::create(
            Email::fromString('user@example.com'),
            Username::fromString('user123'),
            HashedPassword::fromPlainPassword('Pass123!')
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Cannot remove ROLE_USER when it is the only role');

        $user->removeRole(UserRole::user());
    }

    #[Test]
    public function it_verifies_email_successfully(): void
    {
        $user = User::create(
            Email::fromString('user@example.com'),
            Username::fromString('user123'),
            HashedPassword::fromPlainPassword('Pass123!')
        );
        $user->popEvents(); // Clear creation event

        $this->assertFalse($user->emailVerified());
        $this->assertNull($user->emailVerifiedAt());

        $user->verifyEmail();

        $this->assertTrue($user->emailVerified());
        $this->assertInstanceOf(DateTimeImmutable::class, $user->emailVerifiedAt());

        $events = $user->popEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(UserEmailVerified::class, $events[0]);
        $this->assertEquals($user->id(), $events[0]->userId());
    }

    #[Test]
    public function it_throws_exception_when_verifying_already_verified_email(): void
    {
        $user = User::create(
            Email::fromString('user@example.com'),
            Username::fromString('user123'),
            HashedPassword::fromPlainPassword('Pass123!')
        );

        $user->verifyEmail();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Email is already verified');

        $user->verifyEmail();
    }

    #[Test]
    public function it_locks_account_successfully(): void
    {
        $user = User::create(
            Email::fromString('user@example.com'),
            Username::fromString('user123'),
            HashedPassword::fromPlainPassword('Pass123!')
        );
        $user->popEvents(); // Clear creation event

        $this->assertFalse($user->isLocked());
        $this->assertNull($user->lockReason());
        $this->assertNull($user->lockedAt());

        $reason = 'Multiple failed login attempts';
        $user->lock($reason);

        $this->assertTrue($user->isLocked());
        $this->assertEquals($reason, $user->lockReason());
        $this->assertInstanceOf(DateTimeImmutable::class, $user->lockedAt());

        $events = $user->popEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(UserAccountLocked::class, $events[0]);
        $this->assertEquals($user->id(), $events[0]->userId());
        $this->assertEquals($reason, $events[0]->reason());
    }

    #[Test]
    public function it_throws_exception_when_locking_already_locked_account(): void
    {
        $user = User::create(
            Email::fromString('user@example.com'),
            Username::fromString('user123'),
            HashedPassword::fromPlainPassword('Pass123!')
        );

        $user->lock('First lock');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Account is already locked');

        $user->lock('Second lock');
    }

    #[Test]
    public function it_throws_exception_when_locking_with_empty_reason(): void
    {
        $user = User::create(
            Email::fromString('user@example.com'),
            Username::fromString('user123'),
            HashedPassword::fromPlainPassword('Pass123!')
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Lock reason cannot be empty');

        $user->lock('');
    }

    #[Test]
    public function it_unlocks_account_successfully(): void
    {
        $user = User::create(
            Email::fromString('user@example.com'),
            Username::fromString('user123'),
            HashedPassword::fromPlainPassword('Pass123!')
        );

        $user->lock('Suspicious activity');
        $user->popEvents(); // Clear previous events

        $this->assertTrue($user->isLocked());

        $user->unlock();

        $this->assertFalse($user->isLocked());
        $this->assertNull($user->lockReason());
        $this->assertNull($user->lockedAt());

        $events = $user->popEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(UserAccountUnlocked::class, $events[0]);
        $this->assertEquals($user->id(), $events[0]->userId());
    }

    #[Test]
    public function it_throws_exception_when_unlocking_non_locked_account(): void
    {
        $user = User::create(
            Email::fromString('user@example.com'),
            Username::fromString('user123'),
            HashedPassword::fromPlainPassword('Pass123!')
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Account is not locked');

        $user->unlock();
    }

    #[Test]
    public function it_checks_if_user_is_super_admin(): void
    {
        $regularUser = User::create(
            Email::fromString('user@example.com'),
            Username::fromString('user123'),
            HashedPassword::fromPlainPassword('Pass123!')
        );

        $this->assertFalse($regularUser->isSuperAdmin());

        $superAdmin = User::create(
            Email::fromString('admin@example.com'),
            Username::fromString('superadmin'),
            HashedPassword::fromPlainPassword('AdminPass123!'),
            [UserRole::superAdmin()]
        );

        $this->assertTrue($superAdmin->isSuperAdmin());
    }

    #[Test]
    public function it_reconstitutes_user_from_persistence_with_all_fields(): void
    {
        $userId = UserId::generate();
        $email = Email::fromString('restored@example.com');
        $username = Username::fromString('restoreduser');
        $password = HashedPassword::fromPlainPassword('Pass123!');
        $roles = [UserRole::admin(), UserRole::manager()];
        $createdAt = new DateTimeImmutable('2024-01-01 10:00:00');
        $emailVerifiedAt = new DateTimeImmutable('2024-01-02 11:00:00');
        $lockedAt = new DateTimeImmutable('2024-01-03 12:00:00');

        $user = User::reconstitute(
            $userId,
            $email,
            $username,
            $password,
            $roles,
            $createdAt,
            true,
            $emailVerifiedAt,
            true,
            'Security policy violation',
            $lockedAt
        );

        $this->assertEquals($userId, $user->id());
        $this->assertEquals($email, $user->email());
        $this->assertEquals($username, $user->username());
        $this->assertCount(2, $user->roles());
        $this->assertEquals($createdAt, $user->createdAt());
        $this->assertTrue($user->emailVerified());
        $this->assertEquals($emailVerifiedAt, $user->emailVerifiedAt());
        $this->assertTrue($user->isLocked());
        $this->assertEquals('Security policy violation', $user->lockReason());
        $this->assertEquals($lockedAt, $user->lockedAt());
    }

    #[Test]
    public function it_returns_roles_as_strings(): void
    {
        $user = User::create(
            Email::fromString('admin@example.com'),
            Username::fromString('admin'),
            HashedPassword::fromPlainPassword('Pass123!'),
            [UserRole::admin(), UserRole::manager(), UserRole::viewer()]
        );

        $roleStrings = $user->rolesAsStrings();

        $this->assertCount(3, $roleStrings);
        $this->assertContains('ROLE_ADMIN', $roleStrings);
        $this->assertContains('ROLE_MANAGER', $roleStrings);
        $this->assertContains('ROLE_VIEWER', $roleStrings);
    }

    #[Test]
    public function it_handles_complex_role_management_scenario(): void
    {
        // Create user with base role
        $user = User::create(
            Email::fromString('evolving@example.com'),
            Username::fromString('evolvinguser'),
            HashedPassword::fromPlainPassword('Pass123!')
        );

        $this->assertCount(1, $user->roles());
        $this->assertTrue($user->hasRole(UserRole::user()));

        // Promote to manager
        $user->addRole(UserRole::manager());
        $this->assertCount(2, $user->roles());
        $this->assertTrue($user->hasRole(UserRole::manager()));

        // Promote to admin
        $user->addRole(UserRole::admin());
        $this->assertCount(3, $user->roles());
        $this->assertTrue($user->hasRole(UserRole::admin()));

        // Demote from manager
        $user->removeRole(UserRole::manager());
        $this->assertCount(2, $user->roles());
        $this->assertFalse($user->hasRole(UserRole::manager()));
        $this->assertTrue($user->hasRole(UserRole::admin()));
        $this->assertTrue($user->hasRole(UserRole::user()));
    }
}
