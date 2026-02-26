<?php

declare(strict_types=1);

namespace App\Shipping\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Shipping\Application\Command\CreateShipment\CreateShipmentCommand;
use App\Shipping\Domain\Model\ShipmentId;
use App\Shipping\Presentation\Api\Resource\ShipmentResource;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/** @implements ProcessorInterface<ShipmentResource, ShipmentResource> */
final readonly class CreateShipmentProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ShipmentResource
    {
        $tenantId = $this->requestStack->getCurrentRequest()?->headers->get('X-Tenant-ID') ?? '';

        $command = new CreateShipmentCommand(
            shipmentId: ShipmentId::generate()->toString(),
            tenantId: $tenantId,
            orderId: $data->orderId ?? '',
            recipientName: $data->recipientName ?? '',
            recipientAddress: $data->recipientAddress ?? [],
            rateAmount: $data->rateAmount,
            rateCurrency: $data->rateCurrency,
            rateCarrier: $data->carrierCode,
            rateEstimatedDays: $data->rateEstimatedDays,
            rateServiceName: $data->rateServiceName,
        );

        $envelope = $this->messageBus->dispatch($command);
        $handledStamp = $envelope->last(HandledStamp::class);
        $shipment = $handledStamp?->getResult();

        return ShipmentResourceMapper::fromDomain($shipment);
    }
}
