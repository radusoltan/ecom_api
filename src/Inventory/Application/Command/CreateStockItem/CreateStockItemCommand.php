<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command\CreateStockItem;

use App\Catalog\Domain\Model\ProductId;
use App\Inventory\Domain\Model\Quantity;
use App\Inventory\Domain\Model\StockItemId;
use App\Inventory\Domain\Model\WarehouseId;
use App\Shared\Domain\ValueObject\TenantId;

final readonly class CreateStockItemCommand
{
    public function __construct(
        public StockItemId $stockItemId,
        public ProductId $productId,
        public WarehouseId $warehouseId,
        public Quantity $initialQuantity,
        public ?Quantity $lowStockThreshold,
        public TenantId $tenantId,
    ) {
    }
}
