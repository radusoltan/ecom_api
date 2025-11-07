<?php

declare(strict_types=1);

namespace App\User\Application\Command\UpdateUser;

use App\User\Domain\Model\User;
use App\User\Domain\Repository\UserRepositoryInterface;
use App\User\Domain\ValueObject\UserId;
use App\User\Domain\ValueObject\Username;
use App\User\Domain\ValueObject\UserRole;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateUserHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {
    }

    public function __invoke(UpdateUser $command): void
    {
        // Find user
        $user = $this->userRepository->findById(UserId::fromString($command->userId));
        if (null === $user) {
            throw new \DomainException(sprintf('User with ID "%s" not found', $command->userId));
        }

        // Check if username is taken by another user
        $existingUser = $this->userRepository->findByUsername(Username::fromString($command->username));
        if (null !== $existingUser && !$existingUser->id()->equals($user->id())) {
            throw new \DomainException(sprintf('Username "%s" is already taken', $command->username));
        }

        // Convert string roles to UserRole value objects
        $roles = array_map(
            fn (string $role) => UserRole::fromString($role),
            $command->roles
        );

        // Update user (recreate with new data)
        $updatedUser = User::reconstitute(
            id: $user->id(),
            email: $user->email(),
            username: Username::fromString($command->username),
            password: $user->password(),
            roles: $roles,
            createdAt: $user->createdAt()
        );

        // Save updated user
        $this->userRepository->save($updatedUser);
    }
}
