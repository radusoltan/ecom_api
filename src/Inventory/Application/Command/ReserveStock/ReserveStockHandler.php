<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command\ReserveStock;

use App\Inventory\Domain\Model\StockItem;
use App\Inventory\Domain\Model\StockItemId;
use App\Inventory\Domain\Repository\StockItemRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ReserveStockHandler
{
    public function __construct(
        private StockItemRepositoryInterface $stockItemRepository,
    ) {}

    public function __invoke(ReserveStockCommand $command): void
    {
        // Find existing stock item or create new one
        $stockItem = $this->stockItemRepository->findByProductAndWarehouse(
            $command->productId,
            $command->warehouseId,
            $command->tenantId
        );

        if ($stockItem === null) {
            throw new \DomainException(
                sprintf(
                    'Stock item not found for product %s in warehouse %s',
                    $command->productId->toString(),
                    $command->warehouseId->toString()
                )
            );
        }

        $stockItem->reserve($command->quantity, $command->reservationId);

        $this->stockItemRepository->save($stockItem);
    }
}
