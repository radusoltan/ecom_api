<?php

declare(strict_types=1);

namespace App\Shipping\Domain\Event;

use App\Shipping\Domain\Model\ShipmentId;
use App\Shipping\Domain\ValueObject\CarrierCode;
use App\Shipping\Domain\ValueObject\TrackingNumber;
use App\Shared\Domain\ValueObject\TenantId;

final readonly class ShipmentDispatched
{
    public function __construct(
        public ShipmentId $shipmentId,
        public TenantId $tenantId,
        public CarrierCode $carrierCode,
        public TrackingNumber $trackingNumber,
        public \DateTimeImmutable $shippedAt,
        public \DateTimeImmutable $occurredOn,
    ) {
    }
}
