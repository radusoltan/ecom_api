<?php

declare(strict_types=1);

namespace App\Shipping\Application\Command\CancelShipment;

use App\Shared\Domain\ValueObject\TenantId;
use App\Shipping\Domain\Model\ShipmentId;
use App\Shipping\Domain\Repository\ShipmentRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CancelShipmentHandler
{
    public function __construct(
        private ShipmentRepositoryInterface $shipmentRepository,
    ) {
    }

    public function __invoke(CancelShipmentCommand $command): void
    {
        $shipment = $this->shipmentRepository->findByIdAndTenant(
            ShipmentId::fromString($command->shipmentId),
            TenantId::fromString($command->tenantId),
        );

        if (null === $shipment) {
            throw new \RuntimeException(sprintf('Shipment with ID "%s" not found', $command->shipmentId));
        }

        $shipment->cancel();
        $this->shipmentRepository->save($shipment);
    }
}
