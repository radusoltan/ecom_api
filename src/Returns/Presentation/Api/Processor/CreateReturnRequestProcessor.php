<?php

declare(strict_types=1);

namespace App\Returns\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Returns\Application\Command\CreateReturnRequest;
use App\Returns\Application\Query\GetReturnRequestById;
use App\Returns\Presentation\Api\Resource\ReturnRequestResource;
use App\Returns\Presentation\Api\Transformer\ReturnRequestResourceTransformer;
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
        private readonly MessageBusInterface $queryBus
    ) {
        $this->messageBus = $commandBus;
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ReturnRequestResource
    {
        assert($data instanceof ReturnRequestResource);

        $returnRequestId = (string) new Ulid();

        $command = new CreateReturnRequest(
            returnRequestId: $returnRequestId,
            tenantId: $data->tenantId,
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
