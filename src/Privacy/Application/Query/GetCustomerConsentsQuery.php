<?php

declare(strict_types=1);

namespace App\Privacy\Application\Query;

use App\Customer\Domain\ValueObject\CustomerId;

final readonly class GetCustomerConsentsQuery
{
    public function __construct(
        public CustomerId $customerId,
        public bool $activeOnly = false,
    ) {
    }
}
