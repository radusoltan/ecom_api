<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command\BulkReserveStock;

/**
 * Result item for a single product in bulk reservation.
 */
final readonly class BulkReserveStockResultItem
{
    public function __construct(
        public string $productId,
        public string $warehouseId,
        public int $quantity,
        public bool $success,
        public ?string $error = null,
        public ?int $availableQuantity = null,
    ) {
    }
}
