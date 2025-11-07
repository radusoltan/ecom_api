<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command\ReserveStock;

use App\Catalog\Domain\Model\ProductId;
use App\Inventory\Domain\Model\Quantity;
use App\Inventory\Domain\Model\WarehouseId;
use App\Shared\Domain\ValueObject\TenantId;

final readonly class ReserveStockCommand
{
    public function __construct(
        public ProductId $productId,
        public WarehouseId $warehouseId,
        public Quantity $quantity,
        public string $reservationId,
        public TenantId $tenantId,
    ) {
    }
}
