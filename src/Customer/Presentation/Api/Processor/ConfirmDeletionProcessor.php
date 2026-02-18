<?php

declare(strict_types=1);

namespace App\Customer\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Customer\Application\Command\ConfirmAccountDeletion\ConfirmAccountDeletionCommand;
use App\Customer\Application\DTO\DeletionRequestStatusDTO;
use App\Customer\Application\Query\GetDeletionRequestStatus\GetDeletionRequestStatusQuery;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\DeletionRequestId;
use App\Customer\Presentation\Api\Resource\DeletionRequestResource;
use App\Shared\Application\Service\TenantContextInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Confirm Deletion Processor.
 *
 * Confirms a pending account deletion request.
 *
 * @implements ProcessorInterface<DeletionRequestResource, DeletionRequestResource>
 */
final readonly class ConfirmDeletionProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private MessageBusInterface $queryBus,
        private TenantContextInterface $tenantContext,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): DeletionRequestResource
    {
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

        // First, get the current deletion request to obtain the request ID
        $statusQuery = new GetDeletionRequestStatusQuery(
            customerId: $customerId,
            tenantId: $tenantId
        );

        $statusEnvelope = $this->queryBus->dispatch($statusQuery);
        $statusStamp = $statusEnvelope->last(HandledStamp::class);

        if (!$statusStamp instanceof HandledStamp) {
            throw new \RuntimeException('No handler found for query');
        }

        /** @var DeletionRequestStatusDTO|null $statusDto */
        $statusDto = $statusStamp->getResult();

        if (null === $statusDto) {
            throw new BadRequestHttpException('No pending deletion request found');
        }

        // Create confirm command
        $command = new ConfirmAccountDeletionCommand(
            requestId: DeletionRequestId::fromString($statusDto->id),
            customerId: $customerId,
            tenantId: $tenantId
        );

        $this->commandBus->dispatch($command);

        // Retrieve updated deletion request
        $envelope = $this->queryBus->dispatch($statusQuery);
        $handledStamp = $envelope->last(HandledStamp::class);

        if (!$handledStamp instanceof HandledStamp) {
            throw new \RuntimeException('No handler found for query');
        }

        /** @var DeletionRequestStatusDTO|null $dto */
        $dto = $handledStamp->getResult();

        if (null === $dto) {
            throw new \RuntimeException('Deletion request not found after confirmation');
        }

        // Map DTO to resource
        $resource = $this->mapDtoToResource($dto);
        $resource->message = sprintf(
            'Deletion confirmed. Your account will be deleted on %s.',
            (new \DateTimeImmutable($dto->scheduledFor))->format('Y-m-d')
        );

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
