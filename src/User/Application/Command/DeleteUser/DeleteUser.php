<?php

declare(strict_types=1);

namespace App\User\Application\Command\DeleteUser;

/**
 * Command to delete (deactivate) a user.
 *
 * Used by admin users to remove users from the system.
 */
final readonly class DeleteUser
{
    public function __construct(
        public string $userId
    ) {
    }
}
