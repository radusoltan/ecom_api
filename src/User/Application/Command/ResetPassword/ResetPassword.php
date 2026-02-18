<?php

declare(strict_types=1);

namespace App\User\Application\Command\ResetPassword;

/**
 * Command to reset user password using a token.
 *
 * Validates token and updates user password.
 * Invalidates all refresh tokens for security.
 */
final readonly class ResetPassword
{
    public function __construct(
        public string $token,
        public string $newPassword,
    ) {
    }
}
