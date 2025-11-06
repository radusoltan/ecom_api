<?php

declare(strict_types=1);

namespace App\Customer\Application\Query;

final readonly class GetCustomerByEmailQuery
{
    public function __construct(
        public string $email,
        public string $tenantId
    ) {
    }
}
