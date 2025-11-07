<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command\ReleaseStock;

use App\Inventory\Domain\Repository\StockItemRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ReleaseStockHandler
{
    public function __construct(
        private StockItemRepositoryInterface $stockItemRepository,
    ) {
    }

    public function __invoke(ReleaseStockCommand $command): void
    {
        $stockItem = $this->stockItemRepository->findByProductAndWarehouse(
            $command->productId,
            $command->warehouseId,
            $command->tenantId
        );

        if (null === $stockItem) {
            throw new \DomainException(sprintf('Stock item not found for product %s in warehouse %s', $command->productId->toString(), $command->warehouseId->toString()));
        }

        $stockItem->release($command->quantity, $command->reason);

        $this->stockItemRepository->save($stockItem);
    }
}
