<?php

declare(strict_types=1);

namespace App\Customer\Application\Command;

final readonly class ActivateCustomerCommand
{
    public function __construct(
        public string $customerId,
        public string $tenantId
    ) {
    }
}
