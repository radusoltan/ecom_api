<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command\ReleaseStock;

use App\Catalog\Domain\Model\ProductId;
use App\Inventory\Domain\Model\Quantity;
use App\Inventory\Domain\Model\WarehouseId;
use App\Shared\Domain\ValueObject\TenantId;

final readonly class ReleaseStockCommand
{
    public function __construct(
        public ProductId $productId,
        public WarehouseId $warehouseId,
        public Quantity $quantity,
        public string $reason,
        public TenantId $tenantId,
    ) {
    }
}
