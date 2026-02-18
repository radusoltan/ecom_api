<?php

declare(strict_types=1);

namespace App\User\Application\Command\UpdateUser;

/**
 * Command to update an existing user.
 *
 * Used by admin users to update user details (username, roles).
 * Note: Email cannot be changed, password is changed via separate command.
 */
final readonly class UpdateUser
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        public string $userId,
        public string $username,
        public array $roles,
    ) {
    }
}
