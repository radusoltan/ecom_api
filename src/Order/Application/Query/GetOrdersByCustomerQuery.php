<?php

declare(strict_types=1);

namespace App\Order\Application\Query;

final readonly class GetOrdersByCustomerQuery
{
    public function __construct(
        public string $customerEmail,
        public string $tenantId,
    ) {
    }
}
