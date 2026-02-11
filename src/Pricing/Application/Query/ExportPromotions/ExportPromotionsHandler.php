<?php

declare(strict_types=1);

namespace App\Pricing\Application\Query\ExportPromotions;

use App\Pricing\Application\DTO\PromotionDTO;
use App\Pricing\Application\Service\PricingExportService;
use App\Pricing\Domain\Repository\PromotionRepositoryInterface;

/**
 * Handler for ExportPromotionsQuery.
 *
 * Exports Promotions in CSV or JSON format with optional filtering
 */
final class ExportPromotionsHandler
{
    public function __construct(
        private readonly PromotionRepositoryInterface $repository,
        private readonly PricingExportService $exportService
    ) {
    }

    public function __invoke(ExportPromotionsQuery $query): string
    {
        // Fetch all promotions for tenant
        $promotions = $this->repository->findAll($query->filter->tenantId);

        // Apply filters
        if (null !== $query->filter->isActive) {
            $promotions = array_filter(
                $promotions,
                fn ($promotion) => $promotion->isActive() === $query->filter->isActive
            );
        }

        // Apply date range filter if specified
        if ($query->filter->hasDateRange()) {
            $promotions = $this->filterByDateRange($promotions, $query->filter);
        }

        // Convert to DTOs
        $dtos = array_map(
            fn ($promotion) => PromotionDTO::fromDomain($promotion),
            $promotions
        );

        // Export in requested format
        if ($query->isCsvFormat()) {
            return $this->exportService->exportPromotionsToCsv($dtos);
        }

        return $this->exportService->exportPromotionsToJson($dtos);
    }

    /**
     * @param array<\App\Pricing\Domain\Model\Promotion> $promotions
     * @return array<\App\Pricing\Domain\Model\Promotion>
     */
    private function filterByDateRange(array $promotions, \App\Pricing\Application\DTO\ExportFilter $filter): array
    {
        return array_filter($promotions, function ($promotion) use ($filter) {
            $createdAt = $promotion->createdAt();

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
