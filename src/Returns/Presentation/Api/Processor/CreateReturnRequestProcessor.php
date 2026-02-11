<?php

declare(strict_types=1);

namespace App\Returns\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Returns\Application\Command\CreateReturnRequest;
use App\Returns\Application\Query\GetReturnRequestById;
use App\Returns\Presentation\Api\Resource\ReturnRequestResource;
use App\Returns\Presentation\Api\Transformer\ReturnRequestResourceTransformer;
use App\Shared\Infrastructure\Tenant\TenantContext;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Ulid;

/**
 * Processor for creating a return request (RMA).
 */
final class CreateReturnRequestProcessor implements ProcessorInterface
{
    use HandleTrait;

    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly MessageBusInterface $queryBus,
        private readonly TenantContext $tenantContext
    ) {
        $this->messageBus = $commandBus;
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ReturnRequestResource
    {
        assert($data instanceof ReturnRequestResource);

        $returnRequestId = (string) new Ulid();

        // Get tenant ID from context (set by middleware from X-Tenant-ID header)
        $tenantId = $this->tenantContext->getTenantId();
        if (null === $tenantId) {
            throw new \RuntimeException('Tenant context not set. X-Tenant-ID header required.');
        }

        // Validate required fields
        if (null === $data->orderId || '' === trim($data->orderId)) {
            throw new \InvalidArgumentException('Order ID is required.');
        }
        if (null === $data->reason || '' === trim($data->reason)) {
            throw new \InvalidArgumentException('Return reason is required.');
        }

        $command = new CreateReturnRequest(
            returnRequestId: $returnRequestId,
            tenantId: $tenantId,
            orderId: $data->orderId,
            reason: $data->reason
        );

        $this->handle($command);

        // Query back the created return request
        $this->messageBus = $this->queryBus;
        $dto = $this->handle(new GetReturnRequestById($returnRequestId));

        return ReturnRequestResourceTransformer::fromDTO($dto);
    }
}
