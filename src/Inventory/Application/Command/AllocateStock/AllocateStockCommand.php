<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command\AllocateStock;

use App\Inventory\Domain\Model\Quantity;
use App\Inventory\Domain\Model\WarehouseId;
use App\Shared\Domain\ValueObject\TenantId;

final readonly class AllocateStockCommand
{
    public function __construct(
        public string $productId,
        public WarehouseId $warehouseId,
        public Quantity $quantity,
        public string $orderId,
        public TenantId $tenantId,
    ) {
    }
}
