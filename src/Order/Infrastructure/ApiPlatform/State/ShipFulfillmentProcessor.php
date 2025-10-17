<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Order\Application\Command\UpdateFulfillmentStatus;
use App\Order\Domain\ValueObject\FulfillmentId;
use App\Order\Domain\ValueObject\FulfillmentStatus;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Processor to ship fulfillment with tracking info (PATCH /api/fulfillments/{id}/ship)
 *
 * Expected JSON body:
 * {
 *   "carrier": "UPS",
 *   "trackingNumber": "1Z999AA10123456784"
 * }
 */
final readonly class ShipFulfillmentProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $id = $uriVariables['id'] ?? null;

        if ($id === null) {
            throw new BadRequestHttpException('Fulfillment ID is required');
        }

        $request = $this->requestStack->getCurrentRequest();
        $payload = json_decode($request?->getContent() ?? '{}', true);

        $carrier = $payload['carrier'] ?? null;
        $trackingNumber = $payload['trackingNumber'] ?? null;

        if ($carrier === null || trim($carrier) === '') {
            throw new BadRequestHttpException('Carrier is required');
        }

        if ($trackingNumber === null || trim($trackingNumber) === '') {
            throw new BadRequestHttpException('Tracking number is required');
        }

        $tenantIdString = $context['tenant_id'] ?? null;
        if ($tenantIdString === null) {
            throw new \RuntimeException('Tenant ID not found in context');
        }
        $tenantId = TenantId::fromString($tenantIdString);
        $fulfillmentId = FulfillmentId::fromString($id);

        $command = new UpdateFulfillmentStatus(
            fulfillmentId: $fulfillmentId,
            newStatus: FulfillmentStatus::shipping(),
            tenantId: $tenantId,
            carrier: $carrier,
            trackingNumber: $trackingNumber,
        );

        $this->commandBus->dispatch($command);

        return $data;
    }
}
