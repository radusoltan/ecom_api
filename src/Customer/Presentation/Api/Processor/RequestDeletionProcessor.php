<?php

declare(strict_types=1);

namespace App\Customer\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Customer\Application\Command\RequestAccountDeletion\RequestAccountDeletionCommand;
use App\Customer\Application\DTO\DeletionRequestStatusDTO;
use App\Customer\Application\Query\GetDeletionRequestStatus\GetDeletionRequestStatusQuery;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Presentation\Api\Resource\DeletionRequestResource;
use App\Shared\Application\Service\TenantContextInterface;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Request Deletion Processor.
 *
 * Creates a new account deletion request.
 *
 * @implements ProcessorInterface<DeletionRequestResource, DeletionRequestResource>
 */
final readonly class RequestDeletionProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private MessageBusInterface $queryBus,
        private TenantContextInterface $tenantContext
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): DeletionRequestResource
    {
        if (!$data instanceof DeletionRequestResource) {
            throw new BadRequestHttpException('Expected DeletionRequestResource');
        }

        // Extract customerId from URI
        if (!isset($uriVariables['customerId'])) {
            throw new BadRequestHttpException('Customer ID is required');
        }

        $customerId = CustomerId::fromString($uriVariables['customerId']);

        // Get tenant from context
        $tenantId = $this->tenantContext->getCurrentTenantId();
        if (null === $tenantId) {
            throw new BadRequestHttpException('Tenant context is required');
        }

        // Create command
        $command = new RequestAccountDeletionCommand(
            customerId: $customerId,
            tenantId: $tenantId,
            reason: $data->reason
        );

        $this->commandBus->dispatch($command);

        // Retrieve the created deletion request
        $query = new GetDeletionRequestStatusQuery(
            customerId: $customerId,
            tenantId: $tenantId
        );

        $envelope = $this->queryBus->dispatch($query);
        $handledStamp = $envelope->last(HandledStamp::class);

        if (!$handledStamp instanceof HandledStamp) {
            throw new \RuntimeException('No handler found for query');
        }

        /** @var DeletionRequestStatusDTO|null $dto */
        $dto = $handledStamp->getResult();

        if (null === $dto) {
            throw new \RuntimeException('Deletion request not found after creation');
        }

        // Map DTO to resource
        $resource = $this->mapDtoToResource($dto);
        $resource->message = 'Deletion request created. Please check your email to confirm.';

        return $resource;
    }

    private function mapDtoToResource(DeletionRequestStatusDTO $dto): DeletionRequestResource
    {
        $resource = new DeletionRequestResource();
        $resource->id = $dto->id;
        $resource->customerId = $dto->customerId;
        $resource->status = $dto->status;
        $resource->statusLabel = $dto->statusLabel;
        $resource->reason = $dto->reason;
        $resource->holdReason = $dto->holdReason;
        $resource->scheduledFor = $dto->scheduledFor;
        $resource->confirmedAt = $dto->confirmedAt;
        $resource->completedAt = $dto->completedAt;
        $resource->createdAt = $dto->createdAt;
        $resource->canBeCancelled = $dto->canBeCancelled;
        $resource->isOnHold = $dto->isOnHold;
        $resource->canBeExecuted = $dto->canBeExecuted;

        return $resource;
    }
}
