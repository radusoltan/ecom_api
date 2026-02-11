<?php

declare(strict_types=1);

namespace App\Customer\Application\Command\SetDefaultAddress;

/**
 * Command to set an address as default for shipping or billing.
 */
final readonly class SetDefaultAddressCommand
{
    public function __construct(
        public string $customerId,
        public string $tenantId,
        public string $addressId,
        public string $type, // 'shipping' or 'billing'
    ) {
    }
}
