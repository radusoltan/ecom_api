<?php

declare(strict_types=1);

namespace App\Customer\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Customer\Application\Command\HoldDeletion\HoldDeletionCommand;
use App\Customer\Domain\ValueObject\DeletionRequestId;
use App\Customer\Presentation\Api\Resource\DeletionRequestResource;
use App\Shared\Application\Service\TenantContextInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Hold Deletion Processor.
 *
 * Admin endpoint: Places a deletion request on hold.
 *
 * @implements ProcessorInterface<DeletionRequestResource, DeletionRequestResource>
 */
final readonly class HoldDeletionProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private TenantContextInterface $tenantContext
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): DeletionRequestResource
    {
        if (!$data instanceof DeletionRequestResource) {
            throw new BadRequestHttpException('Expected DeletionRequestResource');
        }

        // Extract requestId from URI
        if (!isset($uriVariables['requestId'])) {
            throw new BadRequestHttpException('Request ID is required');
        }

        $requestId = DeletionRequestId::fromString($uriVariables['requestId']);

        // Get tenant from context
        $tenantId = $this->tenantContext->getCurrentTenantId();
        if (null === $tenantId) {
            throw new BadRequestHttpException('Tenant context is required');
        }

        // Validate hold reason
        if (empty($data->reason)) {
            throw new BadRequestHttpException('Hold reason is required');
        }

        // Create hold command
        $command = new HoldDeletionCommand(
            requestId: $requestId,
            tenantId: $tenantId,
            holdReason: $data->reason
        );

        $this->commandBus->dispatch($command);

        // Retrieve updated deletion request (need to get customer ID from somewhere)
        // For admin endpoints, we need to fetch the request details differently
        // For now, we'll create a simplified response
        $resource = new DeletionRequestResource();
        $resource->id = $requestId->toString();
        $resource->status = 'on_hold';
        $resource->statusLabel = 'On Hold';
        $resource->holdReason = $data->reason;
        $resource->isOnHold = true;
        $resource->message = 'Deletion request placed on hold';

        return $resource;
    }
}
