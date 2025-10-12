<?php

declare(strict_types=1);

namespace App\Inventory\Application\Query\GetLowStockItems;

use App\Inventory\Application\Query\GetStockByProduct\StockItemDTO;
use App\Inventory\Domain\Repository\StockItemRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetLowStockItemsHandler
{
    public function __construct(
        private StockItemRepositoryInterface $stockItemRepository,
    ) {}

    /**
     * @return array<StockItemDTO>
     */
    public function __invoke(GetLowStockItemsQuery $query): array
    {
        $stockItems = $this->stockItemRepository->findLowStockItems($query->tenantId);

        return array_map(
            fn($stockItem) => StockItemDTO::fromStockItem($stockItem),
            $stockItems
        );
    }
}
