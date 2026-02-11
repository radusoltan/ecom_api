<?php

declare(strict_types=1);

namespace App\Inventory\Application\Query\CheckStockAvailability;

/**
 * Result DTO for stock availability check.
 */
final readonly class StockAvailabilityResult
{
    /**
     * @param array<ItemAvailabilityResult> $items
     */
    public function __construct(
        public bool $available,
        public array $items,
    ) {
    }
}
