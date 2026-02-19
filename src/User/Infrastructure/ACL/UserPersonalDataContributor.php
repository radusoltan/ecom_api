<?php

declare(strict_types=1);

namespace App\User\Infrastructure\ACL;

use App\Privacy\Domain\Port\PersonalDataContributorInterface;
use App\Shared\Domain\ValueObject\Email;
use App\User\Domain\Repository\UserRepositoryInterface;

/**
 * User Personal Data Contributor.
 *
 * Provides user authentication data to the Privacy context's PersonalDataInventory
 * without requiring Privacy to directly import User repositories.
 */
final readonly class UserPersonalDataContributor implements PersonalDataContributorInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {
    }

    public function contextName(): string
    {
        return 'user';
    }

    public function collectPersonalData(array $subjectContext): array
    {
        $email = $subjectContext['email'] ?? null;

        if (null === $email) {
            return [];
        }

        $user = $this->userRepository->findByEmail(Email::fromString($email));

        if (null === $user) {
            return [];
        }

        return [
            'id' => $user->id()->toString(),
            'email' => $user->email()->value(),
            'username' => $user->username()->toString(),
            'roles' => $user->rolesAsStrings(),
            'createdAt' => $user->createdAt()->format('c'),
        ];
    }

    public function canDeleteData(array $subjectContext): bool
    {
        // User authentication data can always be deleted
        return true;
    }
}
