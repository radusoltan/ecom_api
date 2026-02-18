<?php

declare(strict_types=1);

namespace App\Customer\Application\Command;

final readonly class DeactivateCustomerCommand
{
    public function __construct(
        public string $customerId,
        public string $tenantId,
    ) {
    }
}
