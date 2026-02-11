<?php

declare(strict_types=1);

namespace App\Customer\Application\Query\GetCustomerPreferences;

/**
 * Get Customer Preferences Query.
 *
 * Query to retrieve customer preferences.
 */
final readonly class GetCustomerPreferencesQuery
{
    public function __construct(
        public string $customerId,
        public string $tenantId
    ) {
    }
}
