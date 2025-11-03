<?php

declare(strict_types=1);

namespace App\User\Application\Query\GetUsers;

/**
 * Query to get all users.
 *
 * Note: Pagination and filtering will be handled by API Platform.
 * This query returns all users for now.
 */
final readonly class GetUsers
{
    public function __construct()
    {
    }
}
