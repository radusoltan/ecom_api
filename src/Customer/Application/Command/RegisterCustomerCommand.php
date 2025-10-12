<?php

declare(strict_types=1);

namespace App\Customer\Application\Command;

final readonly class RegisterCustomerCommand
{
    public function __construct(
        public string $customerId,
        public string $tenantId,
        public string $email,
        public string $firstName,
        public string $lastName,
        public ?string $phoneNumber = null
    ) {
    }
}
