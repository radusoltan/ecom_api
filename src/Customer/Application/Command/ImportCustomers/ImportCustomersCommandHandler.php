<?php

declare(strict_types=1);

namespace App\Customer\Application\Command\ImportCustomers;

use App\Customer\Application\DTO\ImportResult;
use App\Customer\Application\Service\CustomerImportService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler for importing customers from CSV or JSON.
 *
 * Validates data, handles duplicates, and processes imports in batches.
 */
#[AsMessageHandler]
final readonly class ImportCustomersCommandHandler
{
    public function __construct(
        private CustomerImportService $importService,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ImportCustomersCommand $command): ImportResult
    {
        $this->logger->info('Starting customer import', [
            'tenant_id' => $command->tenantId->toString(),
            'format' => $command->format,
            'update_existing' => $command->updateExisting,
        ]);

        try {
            if ($command->isCsvFormat()) {
                $result = $this->importService->importFromCsv(
                    tenantId: $command->tenantId,
                    content: $command->content,
                    updateExisting: $command->updateExisting
                );
            } else {
                $result = $this->importService->importFromJson(
                    tenantId: $command->tenantId,
                    content: $command->content,
                    updateExisting: $command->updateExisting
                );
            }

            $this->logger->info('Customer import completed', [
                'tenant_id' => $command->tenantId->toString(),
                'total_rows' => $result->totalRows,
                'imported' => $result->imported,
                'updated' => $result->updated,
                'skipped' => $result->skipped,
                'errors' => count($result->errors),
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Customer import failed', [
                'tenant_id' => $command->tenantId->toString(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
