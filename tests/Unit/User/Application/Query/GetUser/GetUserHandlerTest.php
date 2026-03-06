<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\Application\Query\GetUser;

use App\Shared\Domain\ValueObject\Email;
use App\User\Application\Query\GetUser\GetUser;
use App\User\Application\Query\GetUser\GetUserHandler;
use App\User\Domain\Model\User;
use App\User\Domain\Repository\UserRepositoryInterface;
use App\User\Domain\ValueObject\HashedPassword;
use App\User\Domain\ValueObject\UserId;
use App\User\Domain\ValueObject\Username;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetUserHandler::class)]
final class GetUserHandlerTest extends TestCase
{
    private UserRepositoryInterface&MockObject $userRepository;
    private GetUserHandler $handler;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->handler = new GetUserHandler($this->userRepository);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeUser(string $email = 'user@example.com', string $username = 'testuser'): User
    {
        $user = User::create(
            email: Email::fromString($email),
            username: Username::fromString($username),
            password: HashedPassword::fromPlainPassword('SecurePass123!'),
        );
        $user->popEvents();

        return $user;
    }

    // -----------------------------------------------------------------------
    // Happy path
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsUserWhenFoundById(): void
    {
        $user = $this->makeUser();

        $this->userRepository
            ->expects(self::once())
            ->method('findById')
            ->with(self::isInstanceOf(UserId::class))
            ->willReturn($user);

        $result = ($this->handler)(new GetUser(userId: $user->id()->toString()));

        self::assertInstanceOf(User::class, $result);
        self::assertSame($user->id()->toString(), $result->id()->toString());
    }

    #[Test]
    public function itReturnsTheExactUserObject(): void
    {
        $user = $this->makeUser('alice@example.com', 'alice');

        $this->userRepository->method('findById')->willReturn($user);

        $result = ($this->handler)(new GetUser(userId: $user->id()->toString()));

        self::assertSame('alice@example.com', $result->email()->toString());
        self::assertSame('alice', $result->username()->toString());
    }

    // -----------------------------------------------------------------------
    // Not found
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsDomainExceptionWhenUserNotFound(): void
    {
        $userId = UserId::generate();

        $this->userRepository->method('findById')->willReturn(null);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage($userId->toString());

        ($this->handler)(new GetUser(userId: $userId->toString()));
    }

    #[Test]
    public function itThrowsDomainExceptionWithCorrectMessage(): void
    {
        $userId = UserId::generate();

        $this->userRepository->method('findById')->willReturn(null);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(sprintf('User with ID "%s" not found', $userId->toString()));

        ($this->handler)(new GetUser(userId: $userId->toString()));
    }

    // -----------------------------------------------------------------------
    // Repository interaction
    // -----------------------------------------------------------------------

    #[Test]
    public function itCallsRepositoryFindByIdExactlyOnce(): void
    {
        $user = $this->makeUser();

        $this->userRepository
            ->expects(self::once())
            ->method('findById')
            ->willReturn($user);

        ($this->handler)(new GetUser(userId: $user->id()->toString()));
    }
}
