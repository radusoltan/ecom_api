<?php

declare(strict_types=1);

namespace App\Shipping\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Shipping\Application\Command\DispatchShipment\DispatchShipmentCommand;
use App\Shipping\Presentation\Api\Resource\ShipmentResource;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;

/** @implements ProcessorInterface<ShipmentResource, ShipmentResource> */
final readonly class DispatchShipmentProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ShipmentResource
    {
        $tenantId = $this->requestStack->getCurrentRequest()?->headers->get('X-Tenant-ID') ?? '';

        $this->messageBus->dispatch(new DispatchShipmentCommand(
            shipmentId: $uriVariables['id'] ?? '',
            tenantId: $tenantId,
            carrierCode: $data->carrierCode ?? '',
            trackingNumber: $data->trackingNumber ?? '',
        ));

        $data->status = 'dispatched';

        return $data;
    }
}
