<?php

declare(strict_types=1);

namespace App\Cart\Application\Service;

use App\Cart\Domain\Exception\InsufficientStockException;
use App\Catalog\Domain\Model\ProductId;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * StockValidator Service
 *
 * Responsibilities:
 * - Check stock availability for product+variant
 * - Return available quantity
 * - Throw InsufficientStockException if insufficient stock
 *
 * Integration Points:
 * - Inventory context: Query stock availability
 * - Multi-warehouse aggregation
 *
 * Business Rules:
 * - Show available quantity in error message
 * - "Only 5 items available" vs "Out of stock"
 * - Check across all warehouses for availability
 */
final readonly class StockValidator
{
    public function __construct(
        // TODO: Inject InventoryQuery service when Inventory context is ready
        // private GetStockAvailabilityQueryHandler $stockQueryHandler
    ) {
    }

    /**
     * Validate that sufficient stock is available
     *
     * @param ProductId $productId The product to check
     * @param string|null $variantId Optional variant ID
     * @param TenantId $tenantId Tenant context
     * @param int $requestedQuantity Quantity requested
     * @throws InsufficientStockException If insufficient stock available
     */
    public function validateStockAvailability(
        ProductId $productId,
        ?string $variantId,
        TenantId $tenantId,
        int $requestedQuantity
    ): void {
        $availableQuantity = $this->getAvailableQuantity($productId, $variantId, $tenantId);

        if ($availableQuantity < $requestedQuantity) {
            throw InsufficientStockException::forProduct(
                $productId->toString(),
                $requestedQuantity,
                $availableQuantity
            );
        }
    }

    /**
     * Get available quantity for a product/variant
     *
     * @param ProductId $productId The product to check
     * @param string|null $variantId Optional variant ID
     * @param TenantId $tenantId Tenant context
     * @return int Available quantity (aggregated across warehouses)
     */
    public function getAvailableQuantity(
        ProductId $productId,
        ?string $variantId,
        TenantId $tenantId
    ): int {
        // TODO: Query Inventory context for stock availability
        // For now, return a placeholder value
        // This will be implemented when Inventory context integration is ready

        // Query: GetStockAvailability
        // Input: ProductId, ?VariantId, TenantId
        // Output: { available: int, reserved: int, warehouses: [] }

        // Temporary implementation: assume stock is available
        // In production, this would query the Inventory bounded context
        return 999; // Placeholder - always available for now
    }

    /**
     * Check if a product is in stock (any quantity available)
     *
     * @param ProductId $productId The product to check
     * @param string|null $variantId Optional variant ID
     * @param TenantId $tenantId Tenant context
     * @return bool True if any stock available, false if out of stock
     */
    public function isInStock(
        ProductId $productId,
        ?string $variantId,
        TenantId $tenantId
    ): bool {
        return $this->getAvailableQuantity($productId, $variantId, $tenantId) > 0;
    }

    /**
     * Get stock availability details
     *
     * @param ProductId $productId The product to check
     * @param string|null $variantId Optional variant ID
     * @param TenantId $tenantId Tenant context
     * @return array{available: int, isInStock: bool, isLowStock: bool}
     */
    public function getStockDetails(
        ProductId $productId,
        ?string $variantId,
        TenantId $tenantId
    ): array {
        $available = $this->getAvailableQuantity($productId, $variantId, $tenantId);

        return [
            'available' => $available,
            'isInStock' => $available > 0,
            'isLowStock' => $available > 0 && $available <= 10, // Low stock threshold: 10 units
        ];
    }
}
