<?php

declare(strict_types=1);

namespace App\User\Application\Command\RegisterUser;

use App\Shared\Domain\ValueObject\Email;
use App\User\Domain\Exception\EmailAlreadyExistsException;
use App\User\Domain\Exception\UsernameAlreadyExistsException;
use App\User\Domain\Model\User;
use App\User\Domain\Repository\UserRepositoryInterface;
use App\User\Domain\ValueObject\HashedPassword;
use App\User\Domain\ValueObject\UserId;
use App\User\Domain\ValueObject\Username;
use App\User\Domain\ValueObject\UserRole;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler for registering a new user account.
 *
 * Business Rules:
 * - Email must be unique
 * - Username must be unique
 * - Password minimum 8 characters (enforced by HashedPassword value object)
 * - Automatically assigns ROLE_CUSTOMER
 * - Creates User aggregate that can be linked to Customer aggregate
 */
#[AsMessageHandler]
final readonly class RegisterUserHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {
    }

    public function __invoke(RegisterUser $command): UserId
    {
        // Validate email uniqueness
        $email = Email::fromString($command->email);
        $existingUser = $this->userRepository->findByEmail($email);
        if (null !== $existingUser) {
            throw EmailAlreadyExistsException::forEmail($command->email);
        }

        // Validate username uniqueness
        $username = Username::fromString($command->username);
        $existingUser = $this->userRepository->findByUsername($username);
        if (null !== $existingUser) {
            throw UsernameAlreadyExistsException::forUsername($command->username);
        }

        // Validate password and hash it (validation happens in HashedPassword::fromPlainPassword)
        $hashedPassword = HashedPassword::fromPlainPassword($command->plainPassword);

        // Create user with ROLE_CUSTOMER
        $user = User::create(
            email: $email,
            username: $username,
            password: $hashedPassword,
            roles: [UserRole::customer()]
        );

        // Save user (will dispatch UserCreated event)
        $this->userRepository->save($user);

        // Return user ID for linking with Customer aggregate
        return $user->id();
    }
}
