<?php

declare(strict_types=1);

namespace App\Customer\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Customer\Application\DTO\DataExportStatusDTO;
use App\Customer\Application\Query\GetDataExportStatus\GetDataExportStatusQuery;
use App\Customer\Presentation\Api\Resource\DataExportResource;
use App\Shared\Application\Service\TenantContextInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Data Export Status Provider.
 *
 * Provides the status of a data export request.
 *
 * @implements ProviderInterface<DataExportResource>
 */
final readonly class DataExportStatusProvider implements ProviderInterface
{
    public function __construct(
        private MessageBusInterface $queryBus,
        private TenantContextInterface $tenantContext,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): DataExportResource
    {
        // Extract IDs from URI
        if (!isset($uriVariables['customerId']) || !isset($uriVariables['requestId'])) {
            throw new BadRequestHttpException('Customer ID and request ID are required');
        }

        $customerId = $uriVariables['customerId'];
        $requestId = $uriVariables['requestId'];

        // Get tenant from context
        $tenantId = $this->tenantContext->getCurrentTenantId();
        if (null === $tenantId) {
            throw new BadRequestHttpException('Tenant context is required');
        }

        // Dispatch query
        $query = new GetDataExportStatusQuery(
            requestId: $requestId,
            customerId: $customerId,
            tenantId: $tenantId->toString()
        );

        $envelope = $this->queryBus->dispatch($query);
        $handledStamp = $envelope->last(HandledStamp::class);

        if (!$handledStamp instanceof HandledStamp) {
            throw new \RuntimeException('No handler found for query');
        }

        /** @var DataExportStatusDTO|null $dto */
        $dto = $handledStamp->getResult();

        if (null === $dto) {
            throw new NotFoundHttpException('Export request not found');
        }

        // Map DTO to resource
        return $this->mapDtoToResource($dto, $customerId);
    }

    private function mapDtoToResource(DataExportStatusDTO $dto, string $customerId): DataExportResource
    {
        $resource = new DataExportResource();
        $resource->requestId = $dto->id;
        $resource->customerId = $customerId;
        $resource->status = $dto->status;
        $resource->isDownloadable = $dto->isDownloadable;
        $resource->expiresAt = $dto->expiresAt?->format(\DateTimeInterface::ATOM);
        $resource->createdAt = $dto->createdAt->format(\DateTimeInterface::ATOM);
        $resource->completedAt = $dto->completedAt?->format(\DateTimeInterface::ATOM);
        $resource->errorMessage = $dto->errorMessage;

        // Add download URL if downloadable
        if ($dto->isDownloadable) {
            $resource->downloadUrl = sprintf(
                '/api/customers/%s/data-export/%s/download',
                $customerId,
                $dto->id
            );
        }

        return $resource;
    }
}
