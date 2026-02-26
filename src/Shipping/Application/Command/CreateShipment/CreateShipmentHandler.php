<?php

declare(strict_types=1);

namespace App\Shipping\Application\Command\CreateShipment;

use App\Shared\Domain\ValueObject\TenantId;
use App\Shipping\Domain\Model\Shipment;
use App\Shipping\Domain\Model\ShipmentId;
use App\Shipping\Domain\Repository\ShipmentRepositoryInterface;
use App\Shipping\Domain\ValueObject\CarrierCode;
use App\Shipping\Domain\ValueObject\ShippingRate;
use Brick\Money\Money;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateShipmentHandler
{
    public function __construct(
        private ShipmentRepositoryInterface $shipmentRepository,
    ) {
    }

    public function __invoke(CreateShipmentCommand $command): Shipment
    {
        $rate = null;
        if (null !== $command->rateAmount && null !== $command->rateCurrency && null !== $command->rateCarrier) {
            $rate = ShippingRate::create(
                Money::ofMinor($command->rateAmount, $command->rateCurrency),
                CarrierCode::fromString($command->rateCarrier),
                $command->rateEstimatedDays ?? 0,
                $command->rateServiceName ?? 'Standard',
            );
        }

        $shipment = Shipment::create(
            ShipmentId::fromString($command->shipmentId),
            TenantId::fromString($command->tenantId),
            $command->orderId,
            $command->recipientName,
            $command->recipientAddress,
            $rate,
        );

        $this->shipmentRepository->save($shipment);

        return $shipment;
    }
}
