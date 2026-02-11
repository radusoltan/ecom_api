<?php

declare(strict_types=1);

namespace App\Customer\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Customer\Application\Command\CancelAccountDeletion\CancelAccountDeletionCommand;
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
 * Cancel Deletion Processor.
 *
 * Cancels a pending or confirmed account deletion request.
 *
 * @implements ProcessorInterface<DeletionRequestResource, void>
 */
final readonly class CancelDeletionProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private MessageBusInterface $queryBus,
        private TenantContextInterface $tenantContext
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
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
            throw new BadRequestHttpException('No cancellable deletion request found');
        }

        // Create cancel command
        $command = new CancelAccountDeletionCommand(
            requestId: DeletionRequestId::fromString($statusDto->id),
            customerId: $customerId,
            tenantId: $tenantId
        );

        $this->commandBus->dispatch($command);

        // DELETE operations should return void (204 No Content)
    }
}
