<?php

declare(strict_types=1);

namespace App\Customer\Application\Query;

use App\Customer\Domain\ValueObject\CustomerId;

final readonly class GetCustomerOrdersQuery
{
    public function __construct(
        private CustomerId $customerId
    ) {
    }

    public function customerId(): CustomerId
    {
        return $this->customerId;
    }
}
