<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command\BulkReserveStock;

use App\Catalog\Domain\Model\ProductId;
use App\Inventory\Domain\Model\Quantity;
use App\Inventory\Domain\Model\WarehouseId;

/**
 * Single item in a bulk reservation request.
 */
final readonly class BulkReserveStockItem
{
    public function __construct(
        public ProductId $productId,
        public WarehouseId $warehouseId,
        public Quantity $quantity,
    ) {
    }
}
