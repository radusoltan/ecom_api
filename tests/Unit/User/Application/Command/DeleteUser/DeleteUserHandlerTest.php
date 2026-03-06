<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\Application\Command\DeleteUser;

use App\Shared\Domain\ValueObject\Email;
use App\User\Application\Command\DeleteUser\DeleteUser;
use App\User\Application\Command\DeleteUser\DeleteUserHandler;
use App\User\Domain\Model\User;
use App\User\Domain\Repository\UserRepositoryInterface;
use App\User\Domain\ValueObject\HashedPassword;
use App\User\Domain\ValueObject\UserId;
use App\User\Domain\ValueObject\Username;
use App\User\Domain\ValueObject\UserRole;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeleteUserHandler::class)]
final class DeleteUserHandlerTest extends TestCase
{
    private UserRepositoryInterface $userRepository;
    private DeleteUserHandler $handler;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->handler = new DeleteUserHandler($this->userRepository);
    }

    // -----------------------------------------------------------------------
    // Happy path
    // -----------------------------------------------------------------------

    #[Test]
    public function itDeletesUserWhenFound(): void
    {
        $userId = UserId::generate();
        $user = $this->buildUser($userId);

        $command = new DeleteUser(userId: $userId->toString());

        $this->userRepository->expects(self::once())
            ->method('findById')
            ->with(self::callback(fn (UserId $id) => $id->equals($userId)))
            ->willReturn($user);

        $this->userRepository->expects(self::once())
            ->method('delete')
            ->with(self::identicalTo($user));

        ($this->handler)($command);
    }

    // -----------------------------------------------------------------------
    // User not found
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsDomainExceptionWhenUserNotFound(): void
    {
        $missingId = UserId::generate();
        $command = new DeleteUser(userId: $missingId->toString());

        $this->userRepository->expects(self::once())
            ->method('findById')
            ->willReturn(null);

        $this->userRepository->expects(self::never())
            ->method('delete');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(sprintf('User with ID "%s" not found', $missingId->toString()));

        ($this->handler)($command);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function buildUser(UserId $id): User
    {
        return User::reconstitute(
            id: $id,
            email: Email::fromString('user@example.com'),
            username: Username::fromString('testuser'),
            password: HashedPassword::fromPlainPassword('TempPass123'),
            roles: [UserRole::user()],
            createdAt: new \DateTimeImmutable('2025-01-01'),
        );
    }
}
