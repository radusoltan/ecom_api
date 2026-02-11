<?php

declare(strict_types=1);

namespace App\Customer\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Customer\Application\Command\RequestDataExport\RequestDataExportCommand;
use App\Customer\Presentation\Api\Resource\DataExportResource;
use App\Shared\Application\Service\TenantContextInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Request Data Export Processor.
 *
 * Creates a new GDPR data export request for a customer.
 *
 * @implements ProcessorInterface<DataExportResource, DataExportResource>
 */
final readonly class RequestDataExportProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private TenantContextInterface $tenantContext
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): DataExportResource
    {
        // Extract customerId from URI
        if (!isset($uriVariables['customerId'])) {
            throw new BadRequestHttpException('Customer ID is required');
        }

        $customerId = $uriVariables['customerId'];

        // Get tenant from context
        $tenantId = $this->tenantContext->getCurrentTenantId();
        if (null === $tenantId) {
            throw new BadRequestHttpException('Tenant context is required');
        }

        // Create command
        $command = new RequestDataExportCommand(
            customerId: $customerId,
            tenantId: $tenantId->toString()
        );

        // Dispatch command (async processing)
        $this->commandBus->dispatch($command);

        // Create response resource
        $resource = new DataExportResource();
        $resource->requestId = \Symfony\Component\Uid\Uuid::v7()->toString(); // Generate request ID
        $resource->customerId = $customerId;
        $resource->status = 'pending';
        $resource->message = 'Export request created. Processing will begin shortly.';
        $resource->estimatedCompletionMinutes = 30;
        $resource->isDownloadable = false;
        $resource->createdAt = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);

        return $resource;
    }
}
