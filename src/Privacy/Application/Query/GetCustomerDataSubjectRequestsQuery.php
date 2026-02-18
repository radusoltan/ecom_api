<?php

declare(strict_types=1);

namespace App\Privacy\Application\Query;

use App\Customer\Domain\ValueObject\CustomerId;

final readonly class GetCustomerDataSubjectRequestsQuery
{
    public function __construct(
        public CustomerId $customerId,
    ) {
    }
}
