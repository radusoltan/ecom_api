<?php

declare(strict_types=1);

namespace App\Returns\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Returns\Application\Command\RejectReturnRequest;
use App\Returns\Application\Query\GetReturnRequestById;
use App\Returns\Presentation\Api\Resource\ReturnRequestResource;
use App\Returns\Presentation\Api\Transformer\ReturnRequestResourceTransformer;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Processor for rejecting a return request.
 */
final class RejectReturnRequestProcessor implements ProcessorInterface
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

        $returnRequestId = $uriVariables['id'];

        $command = new RejectReturnRequest(
            returnRequestId: $returnRequestId,
            rejectionReason: $data->rejectionReason
        );

        $this->handle($command);

        // Query back the updated return request
        $this->messageBus = $this->queryBus;
        $dto = $this->handle(new GetReturnRequestById($returnRequestId));

        return ReturnRequestResourceTransformer::fromDTO($dto);
    }
}
