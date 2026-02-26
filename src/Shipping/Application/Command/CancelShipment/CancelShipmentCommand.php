<?php

declare(strict_types=1);

namespace App\Shipping\Application\Command\CancelShipment;

final readonly class CancelShipmentCommand
{
    public function __construct(
        public string $shipmentId,
        public string $tenantId,
    ) {
    }
}
