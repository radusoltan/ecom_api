<?php

declare(strict_types=1);

namespace App\Customer\Application\MessageHandler;

use App\Customer\Application\Message\GenerateDataExportMessage;
use App\Customer\Application\Service\DataExportService;
use App\Customer\Domain\Repository\DataExportRequestRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\DataExportRequestId;
use App\Shared\Domain\ValueObject\TenantId;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Generate Data Export Message Handler.
 *
 * Processes data export requests asynchronously:
 * 1. Marks request as PROCESSING
 * 2. Collects customer data
 * 3. Generates JSON file
 * 4. Stores file securely
 * 5. Marks request as READY with download token and expiration (24h)
 * 6. On error, marks request as FAILED with error message
 */
#[AsMessageHandler]
final readonly class GenerateDataExportMessageHandler
{
    private const EXPIRATION_HOURS = 24;

    public function __construct(
        private DataExportRequestRepositoryInterface $exportRequestRepository,
        private DataExportService $dataExportService,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(GenerateDataExportMessage $message): void
    {
        $requestId = DataExportRequestId::fromString($message->getRequestId());
        $customerId = CustomerId::fromString($message->getCustomerId());
        $tenantId = TenantId::fromString($message->getTenantId());

        $request = $this->exportRequestRepository->findById($requestId);

        if (null === $request) {
            $this->logger->error('Data export request not found', [
                'request_id' => $requestId->toString(),
            ]);

            return;
        }

        try {
            // Mark as processing
            $request->markProcessing();
            $this->exportRequestRepository->save($request);

            $this->logger->info('Generating data export', [
                'request_id' => $requestId->toString(),
                'customer_id' => $customerId->toString(),
                'tenant_id' => $tenantId->toString(),
            ]);

            // Collect customer data
            $data = $this->dataExportService->collectCustomerData($customerId, $tenantId);

            // Generate JSON
            $jsonContent = $this->dataExportService->generateJsonExport($data);

            // Store file
            $filePath = $this->dataExportService->getExportFilePath($requestId);
            $this->dataExportService->storeExportFile($filePath, $jsonContent);

            // Generate download token
            $downloadToken = $this->dataExportService->generateDownloadToken();

            // Calculate expiration (24 hours from now)
            $expiresAt = new \DateTimeImmutable(sprintf('+%d hours', self::EXPIRATION_HOURS));

            // Mark as ready
            $request->markReady($filePath, $downloadToken, $expiresAt);
            $this->exportRequestRepository->save($request);

            $this->logger->info('Data export completed successfully', [
                'request_id' => $requestId->toString(),
                'customer_id' => $customerId->toString(),
                'file_path' => $filePath,
                'expires_at' => $expiresAt->format(\DateTimeInterface::ATOM),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Data export failed', [
                'request_id' => $requestId->toString(),
                'customer_id' => $customerId->toString(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            try {
                $request->markFailed($e->getMessage());
                $this->exportRequestRepository->save($request);
            } catch (\Throwable $saveError) {
                $this->logger->critical('Failed to mark export as failed', [
                    'request_id' => $requestId->toString(),
                    'error' => $saveError->getMessage(),
                ]);
            }
        }
    }
}
