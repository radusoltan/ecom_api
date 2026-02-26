<?php

declare(strict_types=1);

namespace App\Shipping\Application\Query\GetShipmentByOrder;

final readonly class GetShipmentByOrderQuery
{
    public function __construct(
        public string $orderId,
        public string $tenantId,
    ) {
    }
}
