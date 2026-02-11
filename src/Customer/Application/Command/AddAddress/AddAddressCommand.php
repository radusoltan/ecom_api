<?php

declare(strict_types=1);

namespace App\Customer\Application\Command\AddAddress;

/**
 * Command to add a new address to a customer.
 */
final readonly class AddAddressCommand
{
    public function __construct(
        public string $customerId,
        public string $tenantId,
        public string $street,
        public ?string $street2,
        public string $city,
        public ?string $state,
        public string $postalCode,
        public string $country,
        public string $type, // 'shipping', 'billing', 'both'
        public bool $isDefaultShipping = false,
        public bool $isDefaultBilling = false,
    ) {
    }
}
