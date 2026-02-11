<?php

declare(strict_types=1);

namespace App\User\Application\Command\RegisterUser;

/**
 * Command to register a new user account.
 *
 * Business Rules:
 * - Email must be unique
 * - Username must be unique
 * - Password minimum 8 characters
 * - Creates Customer record linked to User
 * - Automatically assigns ROLE_CUSTOMER
 */
final readonly class RegisterUser
{
    public function __construct(
        public string $email,
        public string $username,
        public string $plainPassword,
        public string $tenantId,
        public ?string $firstName = null,
        public ?string $lastName = null,
    ) {
    }
}
