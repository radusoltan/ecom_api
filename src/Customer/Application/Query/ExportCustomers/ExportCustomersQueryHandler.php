<?php

declare(strict_types=1);

namespace App\Customer\Application\Query\ExportCustomers;

use App\Customer\Application\DTO\ExportResult;
use App\Customer\Application\Service\CustomerExportService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler for exporting customers to CSV or JSON.
 */
#[AsMessageHandler]
final readonly class ExportCustomersQueryHandler
{
    public function __construct(
        private CustomerExportService $exportService
    ) {
    }

    public function __invoke(ExportCustomersQuery $query): ExportResult
    {
        if ($query->isCsvFormat()) {
            $content = $this->exportService->exportToCsv(
                tenantId: $query->tenantId,
                filters: $query->filters
            );

            return ExportResult::csv($content);
        }

        $content = $this->exportService->exportToJson(
            tenantId: $query->tenantId,
            filters: $query->filters
        );

        return ExportResult::json($content);
    }
}
