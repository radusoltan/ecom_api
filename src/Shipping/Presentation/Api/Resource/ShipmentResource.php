<?php

declare(strict_types=1);

namespace App\Shipping\Presentation\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Shipping\Infrastructure\ApiPlatform\State\CancelShipmentProcessor;
use App\Shipping\Infrastructure\ApiPlatform\State\CreateShipmentProcessor;
use App\Shipping\Infrastructure\ApiPlatform\State\DeliverShipmentProcessor;
use App\Shipping\Infrastructure\ApiPlatform\State\DispatchShipmentProcessor;
use App\Shipping\Infrastructure\ApiPlatform\State\ShipmentCollectionProvider;
use App\Shipping\Infrastructure\ApiPlatform\State\ShipmentItemProvider;

#[ApiResource(
    shortName: 'Shipment',
    security: "is_granted('ROLE_USER')",
    operations: [
        new GetCollection(provider: ShipmentCollectionProvider::class),
        new Get(provider: ShipmentItemProvider::class),
        new Post(processor: CreateShipmentProcessor::class),
        new Patch(
            uriTemplate: '/shipments/{id}/dispatch',
            provider: ShipmentItemProvider::class,
            processor: DispatchShipmentProcessor::class,
        ),
        new Patch(
            uriTemplate: '/shipments/{id}/deliver',
            provider: ShipmentItemProvider::class,
            processor: DeliverShipmentProcessor::class,
        ),
        new Delete(processor: CancelShipmentProcessor::class),
    ],
)]
class ShipmentResource
{
    public ?string $id = null;
    public ?string $tenantId = null;
    public ?string $orderId = null;
    public ?string $status = null;
    public ?string $carrierCode = null;
    public ?string $trackingNumber = null;
    public ?string $recipientName = null;
    /** @var array<string, mixed>|null */
    public ?array $recipientAddress = null;
    public ?int $rateAmount = null;
    public ?string $rateCurrency = null;
    public ?int $rateEstimatedDays = null;
    public ?string $rateServiceName = null;
    public ?string $shippedAt = null;
    public ?string $deliveredAt = null;
    public ?string $createdAt = null;
    public ?string $updatedAt = null;
}
