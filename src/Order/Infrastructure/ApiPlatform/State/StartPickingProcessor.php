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
 * Processor to start picking process (PATCH /api/fulfillments/{id}/start-picking)
 */
final readonly class StartPickingProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $id = $uriVariables['id'] ?? null;

        if ($id === null) {
            throw new BadRequestHttpException('Fulfillment ID is required');
        }

        // Get tenant ID from context (set by TenantContextProvider)
        $tenantIdString = $context['tenant_id'] ?? null;
        if ($tenantIdString === null) {
            throw new \RuntimeException('Tenant ID not found in context');
        }
        $tenantId = TenantId::fromString($tenantIdString);
        $fulfillmentId = FulfillmentId::fromString($id);

        $command = new UpdateFulfillmentStatus(
            fulfillmentId: $fulfillmentId,
            newStatus: FulfillmentStatus::picking(),
            tenantId: $tenantId,
        );

        $this->commandBus->dispatch($command);

        return $data;
    }
}
