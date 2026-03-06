<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command\TransferStock;

use App\Catalog\Domain\Model\ProductId;
use App\Inventory\Domain\Model\Quantity;
use App\Inventory\Domain\Model\WarehouseId;
use App\Shared\Domain\ValueObject\TenantId;

final readonly class TransferStockCommand
{
    public function __construct(
        public ProductId $productId,
        public WarehouseId $sourceWarehouseId,
        public WarehouseId $destinationWarehouseId,
        public Quantity $quantity,
        public TenantId $tenantId,
    ) {
    }
}
