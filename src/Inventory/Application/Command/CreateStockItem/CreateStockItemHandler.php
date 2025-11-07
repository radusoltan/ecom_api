<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command\CreateStockItem;

use App\Inventory\Domain\Model\StockItem;
use App\Inventory\Domain\Repository\StockItemRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateStockItemHandler
{
    public function __construct(
        private StockItemRepositoryInterface $stockItemRepository,
    ) {
    }

    public function __invoke(CreateStockItemCommand $command): void
    {
        // Check if stock item already exists
        $existingStockItem = $this->stockItemRepository->findByProductAndWarehouse(
            $command->productId,
            $command->warehouseId,
            $command->tenantId
        );

        if (null !== $existingStockItem) {
            throw new \DomainException(sprintf('Stock item already exists for product %s in warehouse %s', $command->productId->toString(), $command->warehouseId->toString()));
        }

        $stockItem = StockItem::create(
            $command->stockItemId,
            $command->tenantId,
            $command->productId,
            $command->warehouseId,
            $command->initialQuantity,
            $command->lowStockThreshold
        );

        $this->stockItemRepository->save($stockItem);
    }
}
