<?php

declare(strict_types=1);

namespace App\Inventory\Application\Query\CheckStockAvailability;

/**
 * Availability result for a single item.
 */
final readonly class ItemAvailabilityResult
{
    public function __construct(
        public string $productId,
        public ?string $variantId,
        public int $requestedQuantity,
        public int $availableQuantity,
        public bool $available,
    ) {
    }
}
