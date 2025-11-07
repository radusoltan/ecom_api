<?php

declare(strict_types=1);

namespace App\Tests\Integration\User\Infrastructure\Persistence;

use App\Shared\Domain\ValueObject\Email;
use App\User\Domain\Model\User;
use App\User\Domain\Repository\UserRepositoryInterface;
use App\User\Domain\ValueObject\HashedPassword;
use App\User\Domain\ValueObject\UserId;
use App\User\Domain\ValueObject\Username;
use App\User\Domain\ValueObject\UserRole;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class UserRepositoryTest extends KernelTestCase
{
    private UserRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();
        $this->repository = self::getContainer()->get(UserRepositoryInterface::class);

        // Clean up any existing test data
        $this->cleanupTestData();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestData();
        parent::tearDown();
    }

    public function testItSavesAndRetrievesUser(): void
    {
        $user = User::create(
            Email::fromString('john.doe@example.com'),
            Username::fromString('johndoe'),
            HashedPassword::fromPlainPassword('SecurePass123!')
        );

        $this->repository->save($user);

        $retrievedUser = $this->repository->findById($user->id());

        $this->assertNotNull($retrievedUser);
        $this->assertEquals($user->id(), $retrievedUser->id());
        $this->assertEquals($user->email(), $retrievedUser->email());
        $this->assertEquals($user->username(), $retrievedUser->username());
    }

    public function testItFindsUserByEmail(): void
    {
        $email = Email::fromString('alice@example.com');
        $user = User::create(
            $email,
            Username::fromString('alice'),
            HashedPassword::fromPlainPassword('Password123!')
        );

        $this->repository->save($user);

        $foundUser = $this->repository->findByEmail($email);

        $this->assertNotNull($foundUser);
        $this->assertEquals($user->id(), $foundUser->id());
        $this->assertEquals($email, $foundUser->email());
    }

    public function testItFindsUserByUsername(): void
    {
        $username = Username::fromString('bobsmith');
        $user = User::create(
            Email::fromString('bob@example.com'),
            $username,
            HashedPassword::fromPlainPassword('BobPass123!')
        );

        $this->repository->save($user);

        $foundUser = $this->repository->findByUsername($username);

        $this->assertNotNull($foundUser);
        $this->assertEquals($user->id(), $foundUser->id());
        $this->assertEquals($username->toString(), $foundUser->username()->toString());
    }

    public function testItReturnsNullWhenUserNotFound(): void
    {
        $nonExistentId = UserId::generate();
        $foundUser = $this->repository->findById($nonExistentId);

        $this->assertNull($foundUser);
    }

    public function testItUpdatesExistingUser(): void
    {
        $user = User::create(
            Email::fromString('update@example.com'),
            Username::fromString('updateuser'),
            HashedPassword::fromPlainPassword('OldPass123!')
        );

        $this->repository->save($user);

        // Retrieve user and change password
        $retrievedUser = $this->repository->findById($user->id());
        $this->assertNotNull($retrievedUser);

        $newPassword = HashedPassword::fromPlainPassword('NewPass456!');
        $retrievedUser->changePassword($newPassword);

        $this->repository->save($retrievedUser);

        // Verify update
        $updatedUser = $this->repository->findById($user->id());
        $this->assertNotNull($updatedUser);
        $this->assertEquals($newPassword->toString(), $updatedUser->password()->toString());
    }

    public function testItDeletesUser(): void
    {
        $user = User::create(
            Email::fromString('delete@example.com'),
            Username::fromString('deleteuser'),
            HashedPassword::fromPlainPassword('DeleteMe123!')
        );

        $this->repository->save($user);

        // Verify user exists
        $this->assertNotNull($this->repository->findById($user->id()));

        // Delete user
        $this->repository->delete($user);

        // Verify user no longer exists
        $this->assertNull($this->repository->findById($user->id()));
    }

    public function testItFindAllUsers(): void
    {
        // Extra cleanup to ensure clean state
        $this->cleanupTestData();

        // Create multiple users
        $user1 = User::create(
            Email::fromString('user1@example.com'),
            Username::fromString('user1'),
            HashedPassword::fromPlainPassword('Pass123!')
        );

        $user2 = User::create(
            Email::fromString('user2@example.com'),
            Username::fromString('user2'),
            HashedPassword::fromPlainPassword('Pass456!')
        );

        $user3 = User::create(
            Email::fromString('user3@example.com'),
            Username::fromString('user3'),
            HashedPassword::fromPlainPassword('Pass789!')
        );

        $this->repository->save($user1);
        $this->repository->save($user2);
        $this->repository->save($user3);

        // Find only test users with specific pattern
        $allUsers = $this->repository->findAll();
        $testUsers = array_filter($allUsers, function (User $user) {
            return str_contains($user->email()->toString(), '@example.com');
        });

        $this->assertCount(3, $testUsers);
        $this->assertContainsOnlyInstancesOf(User::class, $testUsers);
    }

    public function testItPersistsUserWithMultipleRoles(): void
    {
        $user = User::create(
            Email::fromString('admin@example.com'),
            Username::fromString('adminuser'),
            HashedPassword::fromPlainPassword('AdminPass123!'),
            [UserRole::admin(), UserRole::manager()]
        );

        $this->repository->save($user);

        $retrievedUser = $this->repository->findById($user->id());

        $this->assertNotNull($retrievedUser);
        $this->assertCount(2, $retrievedUser->roles());
        $this->assertTrue($retrievedUser->hasRole(UserRole::admin()));
        $this->assertTrue($retrievedUser->hasRole(UserRole::manager()));
    }

    private function cleanupTestData(): void
    {
        $entityManager = self::getContainer()->get('doctrine')->getManager();

        try {
            // Delete all users for clean slate in tests
            $connection = $entityManager->getConnection();
            $connection->executeStatement('DELETE FROM users WHERE email LIKE \'%@example.com\'');
        } catch (\Exception $e) {
            // Ignore errors during cleanup
        }
    }
}
