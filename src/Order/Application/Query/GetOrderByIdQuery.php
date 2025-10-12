<?php

declare(strict_types=1);

namespace App\Order\Application\Query;

final readonly class GetOrderByIdQuery
{
    public function __construct(
        public string $orderId,
        public string $tenantId
    ) {
    }
}
