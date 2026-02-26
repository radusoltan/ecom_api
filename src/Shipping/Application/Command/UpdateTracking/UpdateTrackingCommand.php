<?php

declare(strict_types=1);

namespace App\Shipping\Application\Command\UpdateTracking;

final readonly class UpdateTrackingCommand
{
    public function __construct(
        public string $shipmentId,
        public string $tenantId,
        public string $location,
        public string $statusNote,
        public ?string $newStatus = null,
    ) {
    }
}
