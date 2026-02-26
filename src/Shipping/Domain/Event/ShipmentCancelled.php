<?php

declare(strict_types=1);

namespace App\Shipping\Domain\Event;

use App\Shipping\Domain\Model\ShipmentId;
use App\Shared\Domain\ValueObject\TenantId;

final readonly class ShipmentCancelled
{
    public function __construct(
        public ShipmentId $shipmentId,
        public TenantId $tenantId,
        public \DateTimeImmutable $cancelledAt,
        public \DateTimeImmutable $occurredOn,
    ) {
    }
}
