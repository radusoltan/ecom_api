<?php

declare(strict_types=1);

namespace App\Shipping\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Shipping\Application\Command\UpdateTracking\UpdateTrackingCommand;
use App\Shipping\Domain\Model\ShipmentStatus;
use App\Shipping\Presentation\Api\Resource\ShipmentResource;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;

/** @implements ProcessorInterface<ShipmentResource, ShipmentResource> */
final readonly class DeliverShipmentProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ShipmentResource
    {
        $tenantId = $this->requestStack->getCurrentRequest()?->headers->get('X-Tenant-ID') ?? '';

        $this->messageBus->dispatch(new UpdateTrackingCommand(
            shipmentId: $uriVariables['id'] ?? '',
            tenantId: $tenantId,
            location: 'Delivered',
            statusNote: 'Package delivered successfully',
            newStatus: ShipmentStatus::DELIVERED,
        ));

        $data->status = 'delivered';

        return $data;
    }
}
