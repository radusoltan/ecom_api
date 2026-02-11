<?php

declare(strict_types=1);

namespace App\Customer\Application\Query\GetLoyaltyProgramById;

/**
 * Get Loyalty Program By ID Query.
 *
 * Retrieves a specific loyalty program by its ID and tenant.
 */
final readonly class GetLoyaltyProgramByIdQuery
{
    public function __construct(
        public string $programId,
        public string $tenantId
    ) {
    }
}
