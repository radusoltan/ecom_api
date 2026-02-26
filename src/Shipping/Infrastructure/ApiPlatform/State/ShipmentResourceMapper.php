<?php

declare(strict_types=1);

namespace App\Shipping\Infrastructure\ApiPlatform\State;

use App\Shipping\Domain\Model\Shipment;
use App\Shipping\Presentation\Api\Resource\ShipmentResource;

final class ShipmentResourceMapper
{
    public static function fromDomain(Shipment $shipment): ShipmentResource
    {
        $resource = new ShipmentResource();
        $resource->id = $shipment->id()->toString();
        $resource->tenantId = $shipment->tenantId()->toString();
        $resource->orderId = $shipment->orderId();
        $resource->status = $shipment->status()->value();
        $resource->carrierCode = $shipment->carrier()?->toString();
        $resource->trackingNumber = $shipment->trackingNumber()?->toString();
        $resource->recipientName = $shipment->recipientName();
        $resource->recipientAddress = $shipment->recipientAddress();
        $resource->shippedAt = $shipment->shippedAt()?->format(\DateTimeInterface::ATOM);
        $resource->deliveredAt = $shipment->deliveredAt()?->format(\DateTimeInterface::ATOM);
        $resource->createdAt = $shipment->createdAt()->format(\DateTimeInterface::ATOM);
        $resource->updatedAt = $shipment->updatedAt()?->format(\DateTimeInterface::ATOM);

        if (null !== $shipment->rate()) {
            $resource->rateAmount = $shipment->rate()->amount()->getMinorAmount()->toInt();
            $resource->rateCurrency = $shipment->rate()->amount()->getCurrency()->getCurrencyCode();
            $resource->rateEstimatedDays = $shipment->rate()->estimatedDays();
            $resource->rateServiceName = $shipment->rate()->serviceName();
        }

        return $resource;
    }
}
