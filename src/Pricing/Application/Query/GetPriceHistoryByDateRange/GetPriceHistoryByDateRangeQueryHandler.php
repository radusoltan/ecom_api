<?php

declare(strict_types=1);

namespace App\Pricing\Application\Query\GetPriceHistoryByDateRange;

use App\Pricing\Application\DTO\PriceHistoryDTO;
use App\Pricing\Domain\Repository\PriceHistoryRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Handler for GetPriceHistoryByDateRangeQuery.
 *
 * Returns all price history records within a date range for analytics.
 */
final readonly class GetPriceHistoryByDateRangeQueryHandler
{
    public function __construct(
        private PriceHistoryRepositoryInterface $priceHistoryRepository,
    ) {
    }

    /**
     * @return array<PriceHistoryDTO>
     */
    public function __invoke(GetPriceHistoryByDateRangeQuery $query): array
    {
        $tenantId = TenantId::fromString($query->tenantId);

        $priceChanges = $this->priceHistoryRepository->findByDateRange(
            $tenantId,
            $query->from,
            $query->to,
            $query->limit,
            $query->offset
        );

        return array_map(
            fn ($priceChange) => PriceHistoryDTO::fromPriceChange($priceChange),
            $priceChanges
        );
    }
}
