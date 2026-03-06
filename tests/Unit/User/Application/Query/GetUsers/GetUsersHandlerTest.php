<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\Application\Query\GetUsers;

use App\Shared\Domain\ValueObject\Email;
use App\User\Application\Query\GetUsers\GetUsers;
use App\User\Application\Query\GetUsers\GetUsersHandler;
use App\User\Domain\Model\User;
use App\User\Domain\Repository\UserRepositoryInterface;
use App\User\Domain\ValueObject\HashedPassword;
use App\User\Domain\ValueObject\Username;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetUsersHandler::class)]
final class GetUsersHandlerTest extends TestCase
{
    private UserRepositoryInterface&MockObject $userRepository;
    private GetUsersHandler $handler;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->handler = new GetUsersHandler($this->userRepository);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeUser(string $email, string $username): User
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
    // Returns users
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsAllUsersFromRepository(): void
    {
        $user1 = $this->makeUser('alice@example.com', 'alice');
        $user2 = $this->makeUser('bob@example.com', 'bob');

        $this->userRepository
            ->expects(self::once())
            ->method('findAll')
            ->willReturn([$user1, $user2]);

        $result = ($this->handler)(new GetUsers());

        self::assertCount(2, $result);
        self::assertContainsOnlyInstancesOf(User::class, $result);
    }

    #[Test]
    public function itReturnsEmptyArrayWhenNoUsersExist(): void
    {
        $this->userRepository
            ->method('findAll')
            ->willReturn([]);

        $result = ($this->handler)(new GetUsers());

        self::assertSame([], $result);
    }

    #[Test]
    public function itReturnsSingleUserList(): void
    {
        $user = $this->makeUser('carol@example.com', 'carol');

        $this->userRepository->method('findAll')->willReturn([$user]);

        $result = ($this->handler)(new GetUsers());

        self::assertCount(1, $result);
        self::assertSame('carol@example.com', $result[0]->email()->toString());
    }

    // -----------------------------------------------------------------------
    // Repository interaction
    // -----------------------------------------------------------------------

    #[Test]
    public function itCallsFindAllExactlyOnce(): void
    {
        $this->userRepository
            ->expects(self::once())
            ->method('findAll')
            ->willReturn([]);

        ($this->handler)(new GetUsers());
    }

    #[Test]
    public function itReturnsUsersInRepositoryOrder(): void
    {
        $user1 = $this->makeUser('first@example.com', 'first');
        $user2 = $this->makeUser('second@example.com', 'second');
        $user3 = $this->makeUser('third@example.com', 'third');

        $this->userRepository->method('findAll')->willReturn([$user1, $user2, $user3]);

        $result = ($this->handler)(new GetUsers());

        self::assertSame('first@example.com', $result[0]->email()->toString());
        self::assertSame('second@example.com', $result[1]->email()->toString());
        self::assertSame('third@example.com', $result[2]->email()->toString());
    }
}
