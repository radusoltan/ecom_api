<?php

declare(strict_types=1);

namespace App\User\Application\Command\CreateUser;

/**
 * Command to create a new user.
 *
 * Used by admin users to create new users in the system.
 */
final readonly class CreateUser
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        public string $email,
        public string $username,
        public string $plainPassword,
        public array $roles = [],
    ) {
    }
}
