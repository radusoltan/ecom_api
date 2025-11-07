<?php

declare(strict_types=1);

namespace App\Inventory\Application\Service;

use App\Catalog\Domain\Model\ProductId;
use App\Inventory\Domain\Model\Quantity;
use App\Inventory\Domain\Model\WarehouseId;
use App\Inventory\Domain\Repository\StockItemRepositoryInterface;
use App\Inventory\Domain\Repository\WarehouseRepositoryInterface;
use App\Shared\Domain\ValueObject\Address;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * WarehouseRoutingService.
 *
 * Selects the optimal warehouse for order fulfillment based on:
 * - Stock availability
 * - Warehouse priority (higher = more preferred)
 * - Distance to shipping address (Phase 3 - not yet implemented)
 *
 * Business Rules:
 * - Only active warehouses can fulfill orders
 * - Warehouses must have sufficient stock
 * - Higher priority warehouses are preferred
 * - Priority ranges from 1 (lowest) to 10 (highest)
 */
final readonly class WarehouseRoutingService
{
    public function __construct(
        private WarehouseRepositoryInterface $warehouseRepository,
        private StockItemRepositoryInterface $stockItemRepository,
    ) {
    }

    /**
     * Select the best warehouse to fulfill an order line item.
     *
     * Algorithm:
     * 1. Get all active warehouses for the tenant
     * 2. Filter warehouses that have sufficient stock for the product
     * 3. Sort by priority (descending - higher priority first)
     * 4. Return the first warehouse (highest priority with stock)
     *
     * @param ProductId $productId       The product to fulfill
     * @param Quantity  $quantity        The quantity needed
     * @param Address   $shippingAddress The delivery address (for future distance calculation)
     * @param TenantId  $tenantId        The tenant context
     *
     * @return WarehouseId|null The selected warehouse ID, or null if no warehouse can fulfill
     *
     * @throws \DomainException if quantity is invalid
     */
    public function selectWarehouse(
        ProductId $productId,
        Quantity $quantity,
        Address $shippingAddress,
        TenantId $tenantId
    ): ?WarehouseId {
        // Validate quantity
        if ($quantity->value() <= 0) {
            throw new \DomainException('Quantity must be positive');
        }

        // Step 1: Get all active warehouses ordered by priority
        $warehouses = $this->warehouseRepository->findActiveByTenant($tenantId);

        if (empty($warehouses)) {
            return null;
        }

        // Step 2: Filter warehouses with sufficient stock
        $warehousesWithStock = [];

        foreach ($warehouses as $warehouse) {
            $stockItem = $this->stockItemRepository->findByProductAndWarehouse(
                $productId,
                $warehouse->id(),
                $tenantId
            );

            if (null === $stockItem) {
                continue;
            }

            // Check if warehouse has sufficient available stock
            if ($stockItem->calculateAvailable()->value() >= $quantity->value()) {
                $warehousesWithStock[] = [
                    'warehouse' => $warehouse,
                    'priority' => $warehouse->priority(),
                    'availableStock' => $stockItem->calculateAvailable()->value(),
                ];
            }
        }

        if (empty($warehousesWithStock)) {
            return null;
        }

        // Step 3: Sort by priority (descending - higher priority first)
        usort($warehousesWithStock, function ($a, $b) {
            // Primary sort: by priority (descending)
            if ($a['priority'] !== $b['priority']) {
                return $b['priority'] <=> $a['priority'];
            }

            // Secondary sort: by available stock (descending) for tie-breaking
            return $b['availableStock'] <=> $a['availableStock'];
        });

        // Step 4: Return the highest priority warehouse with stock
        return $warehousesWithStock[0]['warehouse']->id();
    }

    /**
     * Get all warehouses that can fulfill a product order.
     *
     * @param ProductId $productId The product to fulfill
     * @param Quantity  $quantity  The quantity needed
     * @param TenantId  $tenantId  The tenant context
     *
     * @return array<array{warehouseId: WarehouseId, priority: int, availableStock: int}>
     */
    public function getAvailableWarehouses(
        ProductId $productId,
        Quantity $quantity,
        TenantId $tenantId
    ): array {
        $warehouses = $this->warehouseRepository->findActiveByTenant($tenantId);
        $availableWarehouses = [];

        foreach ($warehouses as $warehouse) {
            $stockItem = $this->stockItemRepository->findByProductAndWarehouse(
                $productId,
                $warehouse->id(),
                $tenantId
            );

            if (null === $stockItem) {
                continue;
            }

            if ($stockItem->calculateAvailable()->value() >= $quantity->value()) {
                $availableWarehouses[] = [
                    'warehouseId' => $warehouse->id(),
                    'priority' => $warehouse->priority(),
                    'availableStock' => $stockItem->calculateAvailable()->value(),
                ];
            }
        }

        // Sort by priority descending
        usort($availableWarehouses, function ($a, $b) {
            if ($a['priority'] !== $b['priority']) {
                return $b['priority'] <=> $a['priority'];
            }

            return $b['availableStock'] <=> $a['availableStock'];
        });

        return $availableWarehouses;
    }

    /**
     * Check if any warehouse can fulfill the order.
     *
     * @param ProductId $productId The product to check
     * @param Quantity  $quantity  The quantity needed
     * @param TenantId  $tenantId  The tenant context
     *
     * @return bool True if at least one warehouse can fulfill
     */
    public function canFulfill(
        ProductId $productId,
        Quantity $quantity,
        TenantId $tenantId
    ): bool {
        $warehouses = $this->warehouseRepository->findActiveByTenant($tenantId);

        foreach ($warehouses as $warehouse) {
            $stockItem = $this->stockItemRepository->findByProductAndWarehouse(
                $productId,
                $warehouse->id(),
                $tenantId
            );

            if (null !== $stockItem && $stockItem->calculateAvailable()->value() >= $quantity->value()) {
                return true;
            }
        }

        return false;
    }
}
