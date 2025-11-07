<?php

declare(strict_types=1);

namespace App\Inventory\Application\Query\GetStockByProduct;

use App\Inventory\Domain\Model\StockItem;

final readonly class StockItemDTO
{
    public function __construct(
        public string $stockItemId,
        public string $tenantId,
        public string $productId,
        public string $warehouseId,
        public int $onHand,
        public int $reserved,
        public int $allocated,
        public int $available,
        public int $lowStockThreshold,
        public bool $isLowStock,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function fromStockItem(StockItem $stockItem): self
    {
        $available = $stockItem->calculateAvailable();

        return new self(
            stockItemId: $stockItem->id()->toString(),
            tenantId: $stockItem->tenantId()->toString(),
            productId: $stockItem->productId()->toString(),
            warehouseId: $stockItem->warehouseId()->toString(),
            onHand: $stockItem->onHand()->value(),
            reserved: $stockItem->reserved()->value(),
            allocated: $stockItem->allocated()->value(),
            available: $available->value(),
            lowStockThreshold: $stockItem->lowStockThreshold()->value(),
            isLowStock: $available->isLessThanOrEqual($stockItem->lowStockThreshold()),
            createdAt: $stockItem->createdAt(),
            updatedAt: $stockItem->updatedAt(),
        );
    }
}
