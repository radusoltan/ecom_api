<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Order\Application\Command\UpdateFulfillmentStatus;
use App\Order\Domain\ValueObject\FulfillmentId;
use App\Order\Domain\ValueObject\FulfillmentStatus;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Processor to mark fulfillment as delivered (PATCH /api/fulfillments/{id}/deliver).
 */
final readonly class DeliverFulfillmentProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $id = $uriVariables['id'] ?? null;

        if (null === $id) {
            throw new BadRequestHttpException('Fulfillment ID is required');
        }

        $tenantIdString = $context['tenant_id'] ?? null;
        if (null === $tenantIdString) {
            throw new \RuntimeException('Tenant ID not found in context');
        }
        $tenantId = TenantId::fromString($tenantIdString);
        $fulfillmentId = FulfillmentId::fromString($id);

        $command = new UpdateFulfillmentStatus(
            fulfillmentId: $fulfillmentId,
            newStatus: FulfillmentStatus::delivered(),
            tenantId: $tenantId,
        );

        $this->commandBus->dispatch($command);

        return $data;
    }
}
