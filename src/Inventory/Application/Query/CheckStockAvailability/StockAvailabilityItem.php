<?php

declare(strict_types=1);

namespace App\Inventory\Application\Query\CheckStockAvailability;

use App\Catalog\Domain\Model\ProductId;

/**
 * Single item for stock availability check.
 */
final readonly class StockAvailabilityItem
{
    public function __construct(
        public ProductId $productId,
        public ?string $variantId,
        public int $quantity,
    ) {
    }
}
