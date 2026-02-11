<?php

declare(strict_types=1);

namespace App\Customer\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Customer\Application\Command\ReleaseDeletionHold\ReleaseDeletionHoldCommand;
use App\Customer\Domain\ValueObject\DeletionRequestId;
use App\Customer\Presentation\Api\Resource\DeletionRequestResource;
use App\Shared\Application\Service\TenantContextInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Release Deletion Hold Processor.
 *
 * Admin endpoint: Releases a deletion request from hold status.
 *
 * @implements ProcessorInterface<DeletionRequestResource, DeletionRequestResource>
 */
final readonly class ReleaseDeletionHoldProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private TenantContextInterface $tenantContext
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): DeletionRequestResource
    {
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

        // Create release command
        $command = new ReleaseDeletionHoldCommand(
            requestId: $requestId,
            tenantId: $tenantId
        );

        $this->commandBus->dispatch($command);

        // Create simplified response
        $resource = new DeletionRequestResource();
        $resource->id = $requestId->toString();
        $resource->status = 'confirmed';
        $resource->statusLabel = 'Confirmed - Scheduled for Deletion';
        $resource->holdReason = null;
        $resource->isOnHold = false;
        $resource->message = 'Deletion request released from hold';

        return $resource;
    }
}
