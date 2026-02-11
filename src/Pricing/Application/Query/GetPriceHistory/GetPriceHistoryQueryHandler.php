<?php

declare(strict_types=1);

namespace App\Pricing\Application\Query\GetPriceHistory;

use App\Catalog\Domain\Model\ProductId;
use App\Pricing\Application\DTO\PriceHistoryDTO;
use App\Pricing\Domain\Repository\PriceHistoryRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Handler for GetPriceHistoryQuery.
 *
 * Returns price history records for a specific product.
 */
final readonly class GetPriceHistoryQueryHandler
{
    public function __construct(
        private PriceHistoryRepositoryInterface $priceHistoryRepository
    ) {
    }

    /**
     * @return array<PriceHistoryDTO>
     */
    public function __invoke(GetPriceHistoryQuery $query): array
    {
        $productId = ProductId::fromString($query->productId);
        $tenantId = TenantId::fromString($query->tenantId);

        $priceChanges = $this->priceHistoryRepository->findByProductId(
            $productId,
            $tenantId,
            $query->limit,
            $query->offset
        );

        return array_map(
            fn($priceChange) => PriceHistoryDTO::fromPriceChange($priceChange),
            $priceChanges
        );
    }
}
