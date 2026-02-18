<?php

declare(strict_types=1);

namespace App\Customer\Application\Command;

final readonly class UpdateCustomerCommand
{
    public function __construct(
        public string $customerId,
        public string $tenantId,
        public string $firstName,
        public string $lastName,
        public ?string $phoneNumber = null,
    ) {
    }
}
