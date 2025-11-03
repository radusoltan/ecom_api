<?php

declare(strict_types=1);

namespace App\User\Application\Query\GetUser;

/**
 * Query to get a single user by ID.
 */
final readonly class GetUser
{
    public function __construct(
        public string $userId
    ) {
    }
}
