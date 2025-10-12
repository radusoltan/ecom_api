<?php

declare(strict_types=1);

namespace App\Order\Application\Query;

final readonly class GetAllOrdersQuery
{
    public function __construct(
        public string $tenantId
    ) {
    }
}
