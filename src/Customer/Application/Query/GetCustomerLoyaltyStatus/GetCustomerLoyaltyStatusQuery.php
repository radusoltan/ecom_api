<?php

declare(strict_types=1);

namespace App\Customer\Application\Query\GetCustomerLoyaltyStatus;

/**
 * Query to retrieve comprehensive loyalty status for a customer.
 *
 * Returns current points, tier, progress to next tier, and program details.
 */
final readonly class GetCustomerLoyaltyStatusQuery
{
    public function __construct(
        public string $customerId,
        public string $tenantId,
    ) {
    }
}
