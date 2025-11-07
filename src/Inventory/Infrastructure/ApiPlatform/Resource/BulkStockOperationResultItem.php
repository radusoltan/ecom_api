<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\ApiPlatform\Resource;

/**
 * Result for a single item in bulk stock operation.
 */
final readonly class BulkStockOperationResultItem
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
