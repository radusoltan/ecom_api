<?php

declare(strict_types=1);

namespace App\Cart\Application\Service;

use App\Cart\Domain\Exception\InsufficientStockException;
use App\Catalog\Domain\Model\ProductId;
use App\Inventory\Domain\Repository\StockItemRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * StockValidator Service.
 *
 * Responsibilities:
 * - Check stock availability for product+variant
 * - Return available quantity
 * - Throw InsufficientStockException if insufficient stock
 *
 * Integration Points:
 * - Inventory context: Query stock availability via StockItemRepository
 * - Multi-warehouse aggregation
 *
 * Business Rules:
 * - Show available quantity in error message
 * - "Only 5 items available" vs "Out of stock"
 * - Check across all warehouses for availability
 * - Available = onHand - reserved - allocated (per warehouse)
 */
final readonly class StockValidator
{
    public function __construct(
        private StockItemRepositoryInterface $stockItemRepository,
    ) {
    }

    /**
     * Validate that sufficient stock is available.
     *
     * @param ProductId   $productId         The product to check
     * @param string|null $variantId         Optional variant ID
     * @param TenantId    $tenantId          Tenant context
     * @param int         $requestedQuantity Quantity requested
     *
     * @throws InsufficientStockException If insufficient stock available
     */
    public function validateStockAvailability(
        ProductId $productId,
        ?string $variantId,
        TenantId $tenantId,
        int $requestedQuantity,
    ): void {
        $availableQuantity = $this->getAvailableQuantity($productId, $variantId, $tenantId);

        if ($availableQuantity < $requestedQuantity) {
            throw InsufficientStockException::forProduct($productId->toString(), $requestedQuantity, $availableQuantity);
        }
    }

    /**
     * Get available quantity for a product/variant.
     *
     * Aggregates available stock across all warehouses for the product.
     * Available stock = onHand - reserved - allocated
     *
     * @param ProductId   $productId The product to check
     * @param string|null $variantId Optional variant ID (currently not used - future enhancement)
     * @param TenantId    $tenantId  Tenant context
     *
     * @return int Available quantity (aggregated across warehouses)
     */
    public function getAvailableQuantity(
        ProductId $productId,
        ?string $variantId,
        TenantId $tenantId,
    ): int {
        // Query all stock items for this product across all warehouses
        $stockItems = $this->stockItemRepository->findByProduct($productId, $tenantId);

        // If no stock items found, product has zero availability
        if (empty($stockItems)) {
            return 0;
        }

        // Aggregate available quantity across all warehouses
        // Available = onHand - reserved - allocated (per warehouse)
        $totalAvailable = 0;

        foreach ($stockItems as $stockItem) {
            $available = $stockItem->calculateAvailable();
            $totalAvailable += $available->value();
        }

        return $totalAvailable;
    }

    /**
     * Check if a product is in stock (any quantity available).
     *
     * @param ProductId   $productId The product to check
     * @param string|null $variantId Optional variant ID
     * @param TenantId    $tenantId  Tenant context
     *
     * @return bool True if any stock available, false if out of stock
     */
    public function isInStock(
        ProductId $productId,
        ?string $variantId,
        TenantId $tenantId,
    ): bool {
        return $this->getAvailableQuantity($productId, $variantId, $tenantId) > 0;
    }

    /**
     * Get stock availability details.
     *
     * @param ProductId   $productId The product to check
     * @param string|null $variantId Optional variant ID
     * @param TenantId    $tenantId  Tenant context
     *
     * @return array{available: int, isInStock: bool, isLowStock: bool}
     */
    public function getStockDetails(
        ProductId $productId,
        ?string $variantId,
        TenantId $tenantId,
    ): array {
        $available = $this->getAvailableQuantity($productId, $variantId, $tenantId);

        return [
            'available' => $available,
            'isInStock' => $available > 0,
            'isLowStock' => $available > 0 && $available <= 10, // Low stock threshold: 10 units
        ];
    }
}
