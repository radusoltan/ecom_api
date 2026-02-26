<?php

declare(strict_types=1);

namespace App\Order\Domain\Service;

use App\Catalog\Domain\Model\ProductId;
use App\Inventory\Domain\Model\WarehouseId;
use App\Inventory\Domain\Repository\StockItemRepositoryInterface;
use App\Inventory\Domain\Repository\WarehouseRepositoryInterface;
use App\Order\Domain\Model\Order;
use App\Shared\Domain\ValueObject\TenantId;
use Psr\Log\LoggerInterface;

/**
 * Warehouse Routing Service.
 *
 * Domain service responsible for selecting the optimal warehouse for order fulfillment.
 *
 * Routing Strategy:
 * 1. Find all active warehouses for the tenant
 * 2. Check stock availability for all order items
 * 3. Select warehouse by priority (lower number = higher priority)
 * 4. Future: Consider proximity to customer, shipping costs
 *
 * Business Rules:
 * - Only active warehouses can be selected
 * - Warehouse must have sufficient stock for ALL items in order
 * - Multi-warehouse fulfillment not supported in v1 (single warehouse per order)
 * - Priority determines warehouse selection when multiple warehouses have stock
 */
final readonly class WarehouseRoutingService
{
    public function __construct(
        private WarehouseRepositoryInterface $warehouseRepository,
        private StockItemRepositoryInterface $stockItemRepository,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Find the best warehouse to fulfill the given order.
     *
     * @return WarehouseId|null Returns warehouse ID or null if no warehouse can fulfill
     */
    public function findBestWarehouse(Order $order): ?WarehouseId
    {
        $tenantId = $order->tenantId();

        // Get all active warehouses ordered by priority
        $warehouses = $this->warehouseRepository->findActiveByTenant($tenantId);

        if (empty($warehouses)) {
            $this->logger->warning('No active warehouses found for tenant', [
                'tenant_id' => $tenantId->toString(),
                'order_id' => $order->id()->toString(),
            ]);

            return null;
        }

        // Sort by priority (lower number = higher priority)
        usort($warehouses, fn ($a, $b) => $a->priority() <=> $b->priority());

        // Try each warehouse in priority order
        foreach ($warehouses as $warehouse) {
            if ($this->canWarehouseFulfillOrder($warehouse->id(), $order, $tenantId)) {
                $this->logger->info('Warehouse selected for order fulfillment', [
                    'warehouse_id' => $warehouse->id()->toString(),
                    'warehouse_code' => $warehouse->code()->toString(),
                    'warehouse_priority' => $warehouse->priority(),
                    'order_id' => $order->id()->toString(),
                    'tenant_id' => $tenantId->toString(),
                ]);

                return $warehouse->id();
            }
        }

        $this->logger->error('No warehouse found with sufficient stock for order', [
            'order_id' => $order->id()->toString(),
            'tenant_id' => $tenantId->toString(),
            'warehouses_checked' => count($warehouses),
        ]);

        return null;
    }

    /**
     * Check if a specific warehouse can fulfill all items in the order.
     */
    public function canWarehouseFulfillOrder(
        WarehouseId $warehouseId,
        Order $order,
        TenantId $tenantId,
    ): bool {
        $lines = $order->lines();

        foreach ($lines as $line) {
            $productId = ProductId::fromString($line->productId()->toString());
            $requiredQuantity = $line->quantity();

            // Find stock item for this product at this warehouse
            $stockItem = $this->stockItemRepository->findByProductAndWarehouse(
                $productId,
                $warehouseId,
                $tenantId
            );

            // If no stock item exists, warehouse doesn't have this product
            if (null === $stockItem) {
                $this->logger->debug('Product not available at warehouse', [
                    'product_id' => $productId->toString(),
                    'warehouse_id' => $warehouseId->toString(),
                    'order_id' => $order->id()->toString(),
                ]);

                return false;
            }

            // Check if available quantity is sufficient
            $availableQuantity = $stockItem->calculateAvailable();

            if ($availableQuantity->value() < $requiredQuantity) {
                $this->logger->debug('Insufficient stock at warehouse', [
                    'product_id' => $productId->toString(),
                    'warehouse_id' => $warehouseId->toString(),
                    'required' => $requiredQuantity,
                    'available' => $availableQuantity->value(),
                    'order_id' => $order->id()->toString(),
                ]);

                return false;
            }
        }

        // All items are available with sufficient quantities
        return true;
    }

    /**
     * Get stock availability summary for an order across all warehouses.
     *
     * Returns array of warehouse IDs with their fulfillment capability
     *
     * @return array<string, array{warehouseId: WarehouseId, canFulfill: bool, missingProducts: array}>
     */
    public function getAvailabilityReport(Order $order): array
    {
        $tenantId = $order->tenantId();
        $warehouses = $this->warehouseRepository->findActiveByTenant($tenantId);
        $report = [];

        foreach ($warehouses as $warehouse) {
            $warehouseId = $warehouse->id();
            $missingProducts = [];

            foreach ($order->lines() as $line) {
                $stockItem = $this->stockItemRepository->findByProductAndWarehouse(
                    ProductId::fromString($line->productId()->toString()),
                    $warehouseId,
                    $tenantId
                );

                if (null === $stockItem) {
                    $missingProducts[] = [
                        'product_id' => $line->productId()->toString(),
                        'required' => $line->quantity(),
                        'available' => 0,
                        'reason' => 'not_stocked',
                    ];

                    continue;
                }

                $available = $stockItem->calculateAvailable()->value();
                if ($available < $line->quantity()) {
                    $missingProducts[] = [
                        'product_id' => $line->productId()->toString(),
                        'required' => $line->quantity(),
                        'available' => $available,
                        'reason' => 'insufficient_stock',
                    ];
                }
            }

            $report[$warehouseId->toString()] = [
                'warehouseId' => $warehouseId,
                'warehouseCode' => $warehouse->code()->toString(),
                'warehouseName' => $warehouse->name()->toString(),
                'priority' => $warehouse->priority(),
                'canFulfill' => empty($missingProducts),
                'missingProducts' => $missingProducts,
            ];
        }

        return $report;
    }
}
