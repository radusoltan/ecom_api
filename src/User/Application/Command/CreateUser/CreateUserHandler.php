<?php

declare(strict_types=1);

namespace App\User\Application\Command\CreateUser;

use App\Shared\Domain\ValueObject\Email;
use App\User\Domain\Model\User;
use App\User\Domain\Repository\UserRepositoryInterface;
use App\User\Domain\ValueObject\HashedPassword;
use App\User\Domain\ValueObject\UserRole;
use App\User\Domain\ValueObject\Username;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateUserHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {
    }

    public function __invoke(CreateUser $command): void
    {
        // Check if email already exists
        $existingUser = $this->userRepository->findByEmail(Email::fromString($command->email));
        if ($existingUser !== null) {
            throw new \DomainException(sprintf('User with email "%s" already exists', $command->email));
        }

        // Check if username already exists
        $existingUser = $this->userRepository->findByUsername(Username::fromString($command->username));
        if ($existingUser !== null) {
            throw new \DomainException(sprintf('User with username "%s" already exists', $command->username));
        }

        // Convert string roles to UserRole value objects
        $roles = array_map(
            fn(string $role) => UserRole::fromString($role),
            $command->roles
        );

        // Create user
        $user = User::create(
            email: Email::fromString($command->email),
            username: Username::fromString($command->username),
            password: HashedPassword::fromPlainPassword($command->plainPassword),
            roles: $roles
        );

        // Save user
        $this->userRepository->save($user);
    }
}
