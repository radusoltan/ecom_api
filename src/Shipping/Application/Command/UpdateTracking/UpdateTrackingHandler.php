<?php

declare(strict_types=1);

namespace App\Shipping\Application\Command\UpdateTracking;

use App\Shared\Domain\ValueObject\TenantId;
use App\Shipping\Domain\Model\ShipmentId;
use App\Shipping\Domain\Model\ShipmentStatus;
use App\Shipping\Domain\Repository\ShipmentRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateTrackingHandler
{
    public function __construct(
        private ShipmentRepositoryInterface $shipmentRepository,
    ) {
    }

    public function __invoke(UpdateTrackingCommand $command): void
    {
        $shipment = $this->shipmentRepository->findByIdAndTenant(
            ShipmentId::fromString($command->shipmentId),
            TenantId::fromString($command->tenantId),
        );

        if (null === $shipment) {
            throw new \RuntimeException(sprintf('Shipment with ID "%s" not found', $command->shipmentId));
        }

        $shipment->updateTracking($command->location, $command->statusNote);

        if (null !== $command->newStatus) {
            match ($command->newStatus) {
                ShipmentStatus::IN_TRANSIT => $shipment->markInTransit(),
                ShipmentStatus::DELIVERED => $shipment->markDelivered(),
                ShipmentStatus::FAILED => $shipment->markFailed($command->statusNote),
                default => null,
            };
        }

        $this->shipmentRepository->save($shipment);
    }
}
