<?php

declare(strict_types=1);

namespace App\Pricing\Application\Query\GetLowestPriceInLastDays;

use App\Catalog\Domain\Model\ProductId;
use App\Pricing\Application\DTO\PriceHistoryDTO;
use App\Pricing\Domain\Repository\PriceHistoryRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Handler for GetLowestPriceInLastDaysQuery.
 *
 * Returns the lowest price for a product in the last N days.
 * Critical for EU consumer protection compliance.
 */
final readonly class GetLowestPriceInLastDaysQueryHandler
{
    public function __construct(
        private PriceHistoryRepositoryInterface $priceHistoryRepository,
    ) {
    }

    public function __invoke(GetLowestPriceInLastDaysQuery $query): ?PriceHistoryDTO
    {
        $productId = ProductId::fromString($query->productId);
        $tenantId = TenantId::fromString($query->tenantId);

        $priceChange = $this->priceHistoryRepository->getLowestPriceInLastDays(
            $productId,
            $tenantId,
            $query->days
        );

        return $priceChange ? PriceHistoryDTO::fromPriceChange($priceChange) : null;
    }
}
