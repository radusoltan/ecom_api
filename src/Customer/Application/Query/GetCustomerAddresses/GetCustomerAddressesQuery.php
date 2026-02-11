<?php

declare(strict_types=1);

namespace App\Customer\Application\Query\GetCustomerAddresses;

/**
 * Query to get all addresses for a customer.
 */
final readonly class GetCustomerAddressesQuery
{
    public function __construct(
        public string $customerId,
        public string $tenantId,
        public ?string $type = null, // Optional filter: 'shipping', 'billing', 'both'
    ) {
    }
}
