<?php

declare(strict_types=1);

namespace App\Returns\Application\Query;

/**
 * Query: GetAllReturnRequests.
 *
 * Retrieve all return requests for a tenant.
 * Optional filter by status.
 */
final readonly class GetAllReturnRequests
{
    public function __construct(
        public string $tenantId,
        public ?string $status = null
    ) {
    }
}
