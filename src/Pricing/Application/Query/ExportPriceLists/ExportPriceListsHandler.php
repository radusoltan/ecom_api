<?php

declare(strict_types=1);

namespace App\Pricing\Application\Query\ExportPriceLists;

use App\Pricing\Application\DTO\PriceListDTO;
use App\Pricing\Application\Service\PricingExportService;
use App\Pricing\Domain\Repository\PriceListRepositoryInterface;

/**
 * Handler for ExportPriceListsQuery.
 *
 * Exports PriceLists in CSV or JSON format with optional filtering
 */
final class ExportPriceListsHandler
{
    public function __construct(
        private readonly PriceListRepositoryInterface $repository,
        private readonly PricingExportService $exportService,
    ) {
    }

    public function __invoke(ExportPriceListsQuery $query): string
    {
        // Fetch PriceLists with filters
        $priceLists = $this->repository->findByTenant(
            $query->filter->tenantId,
            $query->filter->onlyActive()
        );

        // Apply date range filter if specified
        if ($query->filter->hasDateRange()) {
            $priceLists = $this->filterByDateRange($priceLists, $query->filter);
        }

        // Convert to DTOs
        $dtos = array_map(
            fn ($priceList) => PriceListDTO::fromDomainModel($priceList),
            $priceLists
        );

        // Export in requested format
        if ($query->isCsvFormat()) {
            return $this->exportService->exportPriceListsToCsv($dtos);
        }

        return $this->exportService->exportPriceListsToJson($dtos);
    }

    /**
     * @param array<\App\Pricing\Domain\Model\PriceList> $priceLists
     *
     * @return array<\App\Pricing\Domain\Model\PriceList>
     */
    private function filterByDateRange(array $priceLists, \App\Pricing\Application\DTO\ExportFilter $filter): array
    {
        return array_filter($priceLists, function ($priceList) use ($filter) {
            $createdAt = $priceList->createdAt();

            if (null !== $filter->dateFrom && $createdAt < $filter->dateFrom) {
                return false;
            }

            if (null !== $filter->dateTo && $createdAt > $filter->dateTo) {
                return false;
            }

            return true;
        });
    }
}
