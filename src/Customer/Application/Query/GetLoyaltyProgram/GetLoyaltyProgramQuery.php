<?php

declare(strict_types=1);

namespace App\Customer\Application\Query\GetLoyaltyProgram;

/**
 * Get Loyalty Program Query.
 *
 * Retrieves the loyalty program for a tenant.
 * Since there's only one program per tenant, tenantId is the only identifier needed.
 */
final readonly class GetLoyaltyProgramQuery
{
    public function __construct(
        public string $tenantId
    ) {
    }
}
