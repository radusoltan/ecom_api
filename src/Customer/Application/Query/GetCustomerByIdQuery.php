<?php

declare(strict_types=1);

namespace App\Customer\Application\Query;

final readonly class GetCustomerByIdQuery
{
    public function __construct(
        public string $customerId,
        public string $tenantId
    ) {
    }
}
