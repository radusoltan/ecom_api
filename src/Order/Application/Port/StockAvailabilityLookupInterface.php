<?php

declare(strict_types=1);

namespace App\Order\Application\Port;

/**
 * Port for checking stock availability from outside the Order context.
 *
 * Anti-Corruption Layer interface that decouples Order from Inventory/Catalog.
 */
interface StockAvailabilityLookupInterface
{
    /**
     * Get total available quantity for a product across all warehouses.
     */
    public function getAvailableQuantity(string $productId, string $tenantId): int;
}
