<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\ApiPlatform\Resource;

/**
 * Availability result for a single item (response).
 */
final readonly class StockAvailabilityItemResultResource
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
