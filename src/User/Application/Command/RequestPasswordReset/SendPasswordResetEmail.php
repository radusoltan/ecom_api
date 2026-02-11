<?php

declare(strict_types=1);

namespace App\User\Application\Command\RequestPasswordReset;

/**
 * Async message to send password reset email.
 *
 * Dispatched to Symfony Messenger queue for asynchronous processing.
 */
final readonly class SendPasswordResetEmail
{
    public function __construct(
        public string $email,
        public string $token,
        public \DateTimeImmutable $expiresAt
    ) {
    }
}
