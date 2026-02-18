<?php

declare(strict_types=1);

namespace App\Customer\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Customer\Application\DTO\DataExportStatusDTO;
use App\Customer\Application\Query\GetDataExportStatus\GetDataExportStatusQuery;
use App\Shared\Application\Service\TenantContextInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Data Export Download Provider.
 *
 * Provides the download stream for completed data export requests.
 * Verifies export status and expiration before serving the file.
 *
 * @implements ProviderInterface<object>
 */
final readonly class DataExportDownloadProvider implements ProviderInterface
{
    public function __construct(
        private MessageBusInterface $queryBus,
        private TenantContextInterface $tenantContext,
        private string $exportStoragePath = '/var/www/new_ecom/backend/var/exports',
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array
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

        // Get export status
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

        // Check if export is downloadable
        if (!$dto->isDownloadable) {
            throw new NotFoundHttpException('Export is not yet ready for download');
        }

        // Check if export has expired
        if (null !== $dto->expiresAt && $dto->expiresAt < new \DateTimeImmutable()) {
            throw new GoneHttpException('Export has expired and is no longer available');
        }

        // Build file path
        $filePath = sprintf(
            '%s/%s/%s.json',
            $this->exportStoragePath,
            $tenantId->toString(),
            $requestId
        );

        // Check if file exists
        if (!file_exists($filePath)) {
            throw new NotFoundHttpException('Export file not found');
        }

        // Read and return file content
        $content = file_get_contents($filePath);
        if (false === $content) {
            throw new \RuntimeException('Failed to read export file');
        }

        // Return JSON response with appropriate headers
        return new JsonResponse(
            json_decode($content, true),
            Response::HTTP_OK,
            [
                'Content-Disposition' => sprintf(
                    'attachment; filename="customer-data-export-%s.json"',
                    $customerId
                ),
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]
        );
    }
}
