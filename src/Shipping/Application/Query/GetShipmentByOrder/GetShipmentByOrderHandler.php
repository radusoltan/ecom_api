<?php

declare(strict_types=1);

namespace App\Shipping\Application\Query\GetShipmentByOrder;

use App\Shared\Domain\ValueObject\TenantId;
use App\Shipping\Domain\Model\Shipment;
use App\Shipping\Domain\Repository\ShipmentRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetShipmentByOrderHandler
{
    public function __construct(
        private ShipmentRepositoryInterface $shipmentRepository,
    ) {
    }

    public function __invoke(GetShipmentByOrderQuery $query): ?Shipment
    {
        return $this->shipmentRepository->findByOrderId(
            $query->orderId,
            TenantId::fromString($query->tenantId),
        );
    }
}
