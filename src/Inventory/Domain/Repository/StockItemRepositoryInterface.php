<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Repository;

use App\Catalog\Domain\Model\ProductId;
use App\Inventory\Domain\Model\StockItem;
use App\Inventory\Domain\Model\StockItemId;
use App\Inventory\Domain\Model\WarehouseId;
use App\Shared\Domain\ValueObject\TenantId;

interface StockItemRepositoryInterface
{
    public function save(StockItem $stockItem): void;

    public function findById(StockItemId $id): ?StockItem;

    public function findByProductAndWarehouse(
        ProductId $productId,
        WarehouseId $warehouseId,
        TenantId $tenantId
    ): ?StockItem;

    /**
     * @return array<StockItem>
     */
    public function findByProduct(ProductId $productId, TenantId $tenantId): array;

    /**
     * @return array<StockItem>
     */
    public function findByWarehouse(WarehouseId $warehouseId, TenantId $tenantId): array;

    /**
     * @return array<StockItem>
     */
    public function findLowStockItems(TenantId $tenantId): array;

    /**
     * @return array<StockItem>
     */
    public function findByTenant(TenantId $tenantId): array;

    public function delete(StockItem $stockItem): void;
}
