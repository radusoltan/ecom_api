<?php

declare(strict_types=1);

namespace App\Shipping\Application\Command\DispatchShipment;

use App\Shared\Domain\ValueObject\TenantId;
use App\Shipping\Domain\Model\ShipmentId;
use App\Shipping\Domain\Repository\ShipmentRepositoryInterface;
use App\Shipping\Domain\ValueObject\CarrierCode;
use App\Shipping\Domain\ValueObject\TrackingNumber;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DispatchShipmentHandler
{
    public function __construct(
        private ShipmentRepositoryInterface $shipmentRepository,
    ) {
    }

    public function __invoke(DispatchShipmentCommand $command): void
    {
        $shipment = $this->shipmentRepository->findByIdAndTenant(
            ShipmentId::fromString($command->shipmentId),
            TenantId::fromString($command->tenantId),
        );

        if (null === $shipment) {
            throw new \RuntimeException(sprintf('Shipment with ID "%s" not found', $command->shipmentId));
        }

        $shipment->dispatch(
            CarrierCode::fromString($command->carrierCode),
            TrackingNumber::fromString($command->trackingNumber),
        );

        $this->shipmentRepository->save($shipment);
    }
}
