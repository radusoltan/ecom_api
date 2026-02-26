<?php

declare(strict_types=1);

namespace App\Shipping\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Shipping\Application\Command\CancelShipment\CancelShipmentCommand;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;

/** @implements ProcessorInterface<mixed, void> */
final readonly class CancelShipmentProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $tenantId = $this->requestStack->getCurrentRequest()?->headers->get('X-Tenant-ID') ?? '';

        $this->messageBus->dispatch(new CancelShipmentCommand(
            shipmentId: $uriVariables['id'] ?? '',
            tenantId: $tenantId,
        ));
    }
}
