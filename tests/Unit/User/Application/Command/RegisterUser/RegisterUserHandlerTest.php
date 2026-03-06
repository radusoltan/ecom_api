<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\Application\Command\RegisterUser;

use App\Shared\Domain\ValueObject\Email;
use App\User\Application\Command\RegisterUser\RegisterUser;
use App\User\Application\Command\RegisterUser\RegisterUserHandler;
use App\User\Domain\Exception\EmailAlreadyExistsException;
use App\User\Domain\Exception\UsernameAlreadyExistsException;
use App\User\Domain\Model\User;
use App\User\Domain\Repository\UserRepositoryInterface;
use App\User\Domain\ValueObject\HashedPassword;
use App\User\Domain\ValueObject\UserId;
use App\User\Domain\ValueObject\Username;
use App\User\Domain\ValueObject\UserRole;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RegisterUserHandler::class)]
final class RegisterUserHandlerTest extends TestCase
{
    private UserRepositoryInterface $userRepository;
    private RegisterUserHandler $handler;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->handler = new RegisterUserHandler($this->userRepository);
    }

    // -----------------------------------------------------------------------
    // Happy path
    // -----------------------------------------------------------------------

    #[Test]
    public function itRegistersUserAndReturnsUserId(): void
    {
        $command = new RegisterUser(
            email: 'new@example.com',
            username: 'newuser',
            plainPassword: 'StrongPass99!',
            tenantId: '00000000-0000-4000-8000-000000000001',
        );

        $this->userRepository->method('findByEmail')->willReturn(null);
        $this->userRepository->method('findByUsername')->willReturn(null);
        $this->userRepository->expects(self::once())->method('save');

        $result = ($this->handler)($command);

        self::assertInstanceOf(UserId::class, $result);
    }

    #[Test]
    public function itAssignsRoleCustomerAutomatically(): void
    {
        $command = new RegisterUser(
            email: 'customer@example.com',
            username: 'shopuser',
            plainPassword: 'MyPassword1!',
            tenantId: '00000000-0000-4000-8000-000000000001',
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
        self::assertTrue($savedUser->hasRole(UserRole::customer()));
    }

    #[Test]
    public function itReturnsUserIdMatchingTheSavedUser(): void
    {
        $command = new RegisterUser(
            email: 'match@example.com',
            username: 'matchuser',
            plainPassword: 'MatchPass123',
            tenantId: '00000000-0000-4000-8000-000000000001',
        );

        $this->userRepository->method('findByEmail')->willReturn(null);
        $this->userRepository->method('findByUsername')->willReturn(null);

        $savedUser = null;
        $this->userRepository->expects(self::once())
            ->method('save')
            ->willReturnCallback(function (User $user) use (&$savedUser): void {
                $savedUser = $user;
            });

        $returnedId = ($this->handler)($command);

        self::assertNotNull($savedUser);
        self::assertTrue($returnedId->equals($savedUser->id()));
    }

    #[Test]
    public function itAcceptsOptionalFirstAndLastName(): void
    {
        $command = new RegisterUser(
            email: 'full@example.com',
            username: 'fullname',
            plainPassword: 'FullPass999!',
            tenantId: '00000000-0000-4000-8000-000000000001',
            firstName: 'John',
            lastName: 'Doe',
        );

        $this->userRepository->method('findByEmail')->willReturn(null);
        $this->userRepository->method('findByUsername')->willReturn(null);
        $this->userRepository->expects(self::once())->method('save');

        $result = ($this->handler)($command);

        self::assertInstanceOf(UserId::class, $result);
    }

    // -----------------------------------------------------------------------
    // Email already exists
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsEmailAlreadyExistsExceptionWhenEmailTaken(): void
    {
        $command = new RegisterUser(
            email: 'taken@example.com',
            username: 'freshuser',
            plainPassword: 'AnyPass123!',
            tenantId: '00000000-0000-4000-8000-000000000001',
        );

        $existingUser = $this->buildUser('taken@example.com', 'existingname');
        $this->userRepository->expects(self::once())
            ->method('findByEmail')
            ->willReturn($existingUser);

        $this->userRepository->expects(self::never())->method('save');

        $this->expectException(EmailAlreadyExistsException::class);
        $this->expectExceptionMessage('taken@example.com');

        ($this->handler)($command);
    }

    // -----------------------------------------------------------------------
    // Username already exists
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsUsernameAlreadyExistsExceptionWhenUsernameTaken(): void
    {
        $command = new RegisterUser(
            email: 'fresh@example.com',
            username: 'takenusername',
            plainPassword: 'AnyPass123!',
            tenantId: '00000000-0000-4000-8000-000000000001',
        );

        $existingUser = $this->buildUser('other@example.com', 'takenusername');
        $this->userRepository->method('findByEmail')->willReturn(null);
        $this->userRepository->expects(self::once())
            ->method('findByUsername')
            ->willReturn($existingUser);

        $this->userRepository->expects(self::never())->method('save');

        $this->expectException(UsernameAlreadyExistsException::class);
        $this->expectExceptionMessage('takenusername');

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
            password: HashedPassword::fromPlainPassword('TempPass123'),
        );
    }
}
