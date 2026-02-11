<?php

declare(strict_types=1);

namespace App\User\Application\Command\RequestPasswordReset;

/**
 * Command to request a password reset.
 *
 * Initiates password reset flow by generating a secure token
 * and sending a reset email to the user.
 *
 * Security note: Always returns success, even if email not found,
 * to prevent email enumeration attacks.
 */
final readonly class RequestPasswordReset
{
    public function __construct(
        public string $email
    ) {
    }
}
