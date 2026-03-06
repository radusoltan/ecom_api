<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\Application\Command\UpdateUser;

use App\Shared\Domain\ValueObject\Email;
use App\User\Application\Command\UpdateUser\UpdateUser;
use App\User\Application\Command\UpdateUser\UpdateUserHandler;
use App\User\Domain\Model\User;
use App\User\Domain\Repository\UserRepositoryInterface;
use App\User\Domain\ValueObject\HashedPassword;
use App\User\Domain\ValueObject\UserId;
use App\User\Domain\ValueObject\Username;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(UpdateUserHandler::class)]
final class UpdateUserHandlerTest extends TestCase
{
    private UserRepositoryInterface $userRepository;
    private UpdateUserHandler $handler;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->handler = new UpdateUserHandler($this->userRepository);
    }

    // -----------------------------------------------------------------------
    // Happy path
    // -----------------------------------------------------------------------

    #[Test]
    public function itUpdatesUserWhenUsernameIsAvailable(): void
    {
        $userId = UserId::generate();
        $existingUser = $this->buildUser($userId, 'jane@example.com', 'olduname');

        $command = new UpdateUser(
            userId: $userId->toString(),
            username: 'newuname',
            roles: ['ROLE_ADMIN'],
        );

        $this->userRepository->expects(self::once())
            ->method('findById')
            ->with(self::callback(fn (UserId $id) => $id->equals($userId)))
            ->willReturn($existingUser);

        $this->userRepository->expects(self::once())
            ->method('findByUsername')
            ->with(Username::fromString('newuname'))
            ->willReturn(null);

        $this->userRepository->expects(self::once())
            ->method('save')
            ->with(self::isInstanceOf(User::class));

        ($this->handler)($command);
    }

    #[Test]
    public function itAllowsUserToKeepTheirOwnUsername(): void
    {
        $userId = UserId::generate();
        $existingUser = $this->buildUser($userId, 'jane@example.com', 'sameuname');

        $command = new UpdateUser(
            userId: $userId->toString(),
            username: 'sameuname',
            roles: ['ROLE_USER'],
        );

        $this->userRepository->method('findById')->willReturn($existingUser);

        // findByUsername returns the same user — no conflict
        $this->userRepository->expects(self::once())
            ->method('findByUsername')
            ->with(Username::fromString('sameuname'))
            ->willReturn($existingUser);

        $this->userRepository->expects(self::once())
            ->method('save');

        ($this->handler)($command);
    }

    #[Test]
    public function itSavesUpdatedUserWithNewUsernameAndRoles(): void
    {
        $userId = UserId::generate();
        $existingUser = $this->buildUser($userId, 'bob@example.com', 'bobold');

        $command = new UpdateUser(
            userId: $userId->toString(),
            username: 'bobnew',
            roles: ['ROLE_MANAGER'],
        );

        $this->userRepository->method('findById')->willReturn($existingUser);
        $this->userRepository->method('findByUsername')->willReturn(null);

        $savedUser = null;
        $this->userRepository->expects(self::once())
            ->method('save')
            ->willReturnCallback(function (User $user) use (&$savedUser): void {
                $savedUser = $user;
            });

        ($this->handler)($command);

        self::assertNotNull($savedUser);
        self::assertSame('bobnew', $savedUser->username()->toString());
        self::assertCount(1, $savedUser->roles());
    }

    // -----------------------------------------------------------------------
    // User not found
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsDomainExceptionWhenUserNotFound(): void
    {
        $missingId = UserId::generate();
        $command = new UpdateUser(
            userId: $missingId->toString(),
            username: 'anyname',
            roles: [],
        );

        $this->userRepository->expects(self::once())
            ->method('findById')
            ->willReturn(null);

        $this->userRepository->expects(self::never())
            ->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(sprintf('User with ID "%s" not found', $missingId->toString()));

        ($this->handler)($command);
    }

    // -----------------------------------------------------------------------
    // Username taken by another user
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsDomainExceptionWhenUsernameIsTakenByAnotherUser(): void
    {
        $userId = UserId::generate();
        $existingUser = $this->buildUser($userId, 'alice@example.com', 'alice');

        $anotherUser = $this->buildUser(UserId::generate(), 'charlie@example.com', 'takenname');

        $command = new UpdateUser(
            userId: $userId->toString(),
            username: 'takenname',
            roles: ['ROLE_USER'],
        );

        $this->userRepository->method('findById')->willReturn($existingUser);
        $this->userRepository->method('findByUsername')->willReturn($anotherUser);

        $this->userRepository->expects(self::never())
            ->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Username "takenname" is already taken');

        ($this->handler)($command);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function buildUser(UserId $id, string $email, string $username): User
    {
        return User::reconstitute(
            id: $id,
            email: Email::fromString($email),
            username: Username::fromString($username),
            password: HashedPassword::fromPlainPassword('TempPass123'),
            roles: [\App\User\Domain\ValueObject\UserRole::user()],
            createdAt: new \DateTimeImmutable('2025-01-01'),
        );
    }
}
