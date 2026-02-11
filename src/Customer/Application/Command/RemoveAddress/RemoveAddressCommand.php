<?php

declare(strict_types=1);

namespace App\Customer\Application\Command\RemoveAddress;

/**
 * Command to remove an address from a customer.
 */
final readonly class RemoveAddressCommand
{
    public function __construct(
        public string $customerId,
        public string $tenantId,
        public string $addressId,
    ) {
    }
}
