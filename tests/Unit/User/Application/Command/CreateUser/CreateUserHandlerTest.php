<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\Application\Command\CreateUser;

use App\Shared\Domain\ValueObject\Email;
use App\User\Application\Command\CreateUser\CreateUser;
use App\User\Application\Command\CreateUser\CreateUserHandler;
use App\User\Domain\Model\User;
use App\User\Domain\Repository\UserRepositoryInterface;
use App\User\Domain\ValueObject\Username;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreateUserHandler::class)]
final class CreateUserHandlerTest extends TestCase
{
    private UserRepositoryInterface $userRepository;
    private CreateUserHandler $handler;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->handler = new CreateUserHandler($this->userRepository);
    }

    // -----------------------------------------------------------------------
    // Happy path
    // -----------------------------------------------------------------------

    #[Test]
    public function itCreatesUserWhenEmailAndUsernameAreAvailable(): void
    {
        $command = new CreateUser(
            email: 'jane@example.com',
            username: 'janesmith',
            plainPassword: 'SecurePass123!',
            roles: ['ROLE_USER'],
        );

        $this->userRepository->expects(self::once())
            ->method('findByEmail')
            ->with(Email::fromString('jane@example.com'))
            ->willReturn(null);

        $this->userRepository->expects(self::once())
            ->method('findByUsername')
            ->with(Username::fromString('janesmith'))
            ->willReturn(null);

        $this->userRepository->expects(self::once())
            ->method('save')
            ->with(self::isInstanceOf(User::class));

        ($this->handler)($command);
    }

    #[Test]
    public function itCreatesUserWithDefaultEmptyRoles(): void
    {
        $command = new CreateUser(
            email: 'user@example.com',
            username: 'newuser',
            plainPassword: 'Password1234',
        );

        $this->userRepository->method('findByEmail')->willReturn(null);
        $this->userRepository->method('findByUsername')->willReturn(null);

        $savedUser = null;
        $this->userRepository->expects(self::once())
            ->method('save')
            ->willReturnCallback(function (User $user) use (&$savedUser): void {
                $savedUser = $user;
            });

        ($this->handler)($command);

        self::assertNotNull($savedUser);
        // User::create() assigns ROLE_USER when roles is empty
        self::assertNotEmpty($savedUser->roles());
    }

    #[Test]
    public function itCreatesUserWithMultipleRoles(): void
    {
        $command = new CreateUser(
            email: 'admin@example.com',
            username: 'adminuser',
            plainPassword: 'AdminPass99!',
            roles: ['ROLE_ADMIN', 'ROLE_MANAGER'],
        );

        $this->userRepository->method('findByEmail')->willReturn(null);
        $this->userRepository->method('findByUsername')->willReturn(null);

        $savedUser = null;
        $this->userRepository->expects(self::once())
            ->method('save')
            ->willReturnCallback(function (User $user) use (&$savedUser): void {
                $savedUser = $user;
            });

        ($this->handler)($command);

        self::assertNotNull($savedUser);
        self::assertCount(2, $savedUser->roles());
    }

    // -----------------------------------------------------------------------
    // Email already exists
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsDomainExceptionWhenEmailAlreadyExists(): void
    {
        $command = new CreateUser(
            email: 'existing@example.com',
            username: 'someuser',
            plainPassword: 'AnyPassword1',
        );

        $existingUser = $this->buildUser('existing@example.com', 'existinguser');

        $this->userRepository->expects(self::once())
            ->method('findByEmail')
            ->willReturn($existingUser);

        $this->userRepository->expects(self::never())
            ->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('User with email "existing@example.com" already exists');

        ($this->handler)($command);
    }

    // -----------------------------------------------------------------------
    // Username already exists
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsDomainExceptionWhenUsernameAlreadyExists(): void
    {
        $command = new CreateUser(
            email: 'new@example.com',
            username: 'takenuser',
            plainPassword: 'AnyPassword1',
        );

        $existingUser = $this->buildUser('other@example.com', 'takenuser');

        $this->userRepository->expects(self::once())
            ->method('findByEmail')
            ->willReturn(null);

        $this->userRepository->expects(self::once())
            ->method('findByUsername')
            ->willReturn($existingUser);

        $this->userRepository->expects(self::never())
            ->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('User with username "takenuser" already exists');

        ($this->handler)($command);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function buildUser(string $email, string $username): User
    {
        return User::create(
            email: Email::fromString($email),
            username: Username::fromString($username),
            password: \App\User\Domain\ValueObject\HashedPassword::fromPlainPassword('TempPass123'),
        );
    }
}
