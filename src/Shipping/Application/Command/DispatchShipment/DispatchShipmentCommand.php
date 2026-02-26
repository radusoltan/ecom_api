<?php

declare(strict_types=1);

namespace App\Shipping\Application\Command\DispatchShipment;

final readonly class DispatchShipmentCommand
{
    public function __construct(
        public string $shipmentId,
        public string $tenantId,
        public string $carrierCode,
        public string $trackingNumber,
    ) {
    }
}
