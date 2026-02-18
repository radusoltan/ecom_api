<?php

declare(strict_types=1);

namespace App\Customer\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Customer\Application\DTO\DeletionRequestStatusDTO;
use App\Customer\Application\Query\GetDeletionRequestStatus\GetDeletionRequestStatusQuery;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Presentation\Api\Resource\DeletionRequestResource;
use App\Shared\Application\Service\TenantContextInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Deletion Request Status Provider.
 *
 * Provides the status of a customer's account deletion request.
 *
 * @implements ProviderInterface<DeletionRequestResource>
 */
final readonly class DeletionRequestStatusProvider implements ProviderInterface
{
    public function __construct(
        private MessageBusInterface $queryBus,
        private TenantContextInterface $tenantContext,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): DeletionRequestResource
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

        // Dispatch query
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
            throw new NotFoundHttpException('No active deletion request found');
        }

        // Map DTO to resource
        return $this->mapDtoToResource($dto);
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
