<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\Infrastructure\ACL;

use App\Shared\Domain\ValueObject\Email;
use App\User\Domain\Model\User;
use App\User\Domain\Repository\UserRepositoryInterface;
use App\User\Domain\ValueObject\HashedPassword;
use App\User\Domain\ValueObject\UserId;
use App\User\Domain\ValueObject\Username;
use App\User\Domain\ValueObject\UserRole;
use App\User\Infrastructure\ACL\UserPersonalDataContributor;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Group('user')]
final class UserPersonalDataContributorTest extends TestCase
{
    private UserRepositoryInterface&MockObject $userRepository;
    private UserPersonalDataContributor $contributor;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->contributor = new UserPersonalDataContributor($this->userRepository);
    }

    public function testContextNameReturnsUser(): void
    {
        self::assertSame('user', $this->contributor->contextName());
    }

    public function testCollectsUserData(): void
    {
        $email = Email::fromString('jane.doe@example.com');
        $user = User::reconstitute(
            id: UserId::generate(),
            email: $email,
            username: Username::fromString('jane_doe'),
            password: HashedPassword::fromHash('$2y$10$examplehashedpasswordvalue'),
            roles: [UserRole::user()],
            createdAt: new \DateTimeImmutable('2025-03-15T09:00:00+00:00'),
        );

        $this->userRepository
            ->expects(self::once())
            ->method('findByEmail')
            ->with(self::callback(fn (Email $e) => 'jane.doe@example.com' === $e->value()))
            ->willReturn($user);

        $result = $this->contributor->collectPersonalData([
            'email' => 'jane.doe@example.com',
            'tenantId' => '00000000-0000-4000-8000-000000000001',
        ]);

        self::assertSame($user->id()->toString(), $result['id']);
        self::assertSame('jane.doe@example.com', $result['email']);
        self::assertSame('jane_doe', $result['username']);
        self::assertIsArray($result['roles']);
        self::assertContains('ROLE_USER', $result['roles']);
        self::assertArrayHasKey('createdAt', $result);
    }

    public function testReturnsEmptyArrayWhenMissingEmail(): void
    {
        $this->userRepository
            ->expects(self::never())
            ->method('findByEmail');

        $result = $this->contributor->collectPersonalData([
            'customerId' => 'f47ac10b-58cc-4372-a567-0e02b2c3d479',
            'tenantId' => '00000000-0000-4000-8000-000000000001',
        ]);

        self::assertSame([], $result);
    }

    public function testReturnsEmptyArrayWhenUserNotFound(): void
    {
        $this->userRepository
            ->expects(self::once())
            ->method('findByEmail')
            ->willReturn(null);

        $result = $this->contributor->collectPersonalData([
            'email' => 'unknown@example.com',
        ]);

        self::assertSame([], $result);
    }

    public function testCanAlwaysDeleteData(): void
    {
        $result = $this->contributor->canDeleteData([
            'email' => 'jane.doe@example.com',
        ]);

        self::assertTrue($result);
    }

    public function testCanDeleteDataWithEmptyContext(): void
    {
        self::assertTrue($this->contributor->canDeleteData([]));
    }
}
