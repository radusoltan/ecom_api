<?php

declare(strict_types=1);

namespace App\Inventory\Application\Query\GetStockByProduct;

use App\Inventory\Domain\Repository\StockItemRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetStockByProductHandler
{
    public function __construct(
        private StockItemRepositoryInterface $stockItemRepository,
    ) {}

    /**
     * @return array<StockItemDTO>
     */
    public function __invoke(GetStockByProductQuery $query): array
    {
        $stockItems = $this->stockItemRepository->findByProduct(
            $query->productId,
            $query->tenantId
        );

        return array_map(
            fn($stockItem) => StockItemDTO::fromStockItem($stockItem),
            $stockItems
        );
    }
}
