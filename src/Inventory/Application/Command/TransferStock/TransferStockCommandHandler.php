<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command\TransferStock;

use App\Inventory\Domain\Model\StockItem;
use App\Inventory\Domain\Model\StockItemId;
use App\Inventory\Domain\Repository\StockItemRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class TransferStockCommandHandler
{
    public function __construct(
        private StockItemRepositoryInterface $stockItemRepository,
    ) {
    }

    public function __invoke(TransferStockCommand $command): void
    {
        if ($command->sourceWarehouseId->toString() === $command->destinationWarehouseId->toString()) {
            throw new \DomainException('Source and destination warehouses must be different.');
        }

        $sourceStockItem = $this->stockItemRepository->findByProductAndWarehouse(
            $command->productId,
            $command->sourceWarehouseId,
            $command->tenantId
        );

        if (null === $sourceStockItem) {
            throw new \DomainException(sprintf('Stock item not found for product %s in source warehouse %s', $command->productId->toString(), $command->sourceWarehouseId->toString()));
        }

        $sourceStockItem->transferOut($command->quantity, $command->destinationWarehouseId);

        $destinationStockItem = $this->stockItemRepository->findByProductAndWarehouse(
            $command->productId,
            $command->destinationWarehouseId,
            $command->tenantId
        );

        if (null === $destinationStockItem) {
            $destinationStockItem = StockItem::create(
                StockItemId::generate(),
                $command->tenantId,
                $command->productId,
                $command->destinationWarehouseId,
                $command->quantity,
            );
        } else {
            $destinationStockItem->transferIn($command->quantity);
        }

        $this->stockItemRepository->save($sourceStockItem);
        $this->stockItemRepository->save($destinationStockItem);
    }
}
