<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command\AllocateStock;

use App\Inventory\Domain\Repository\StockItemRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AllocateStockHandler
{
    public function __construct(
        private StockItemRepositoryInterface $stockItemRepository,
    ) {}

    public function __invoke(AllocateStockCommand $command): void
    {
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

        $stockItem->allocate($command->quantity, $command->orderId);

        $this->stockItemRepository->save($stockItem);
    }
}
