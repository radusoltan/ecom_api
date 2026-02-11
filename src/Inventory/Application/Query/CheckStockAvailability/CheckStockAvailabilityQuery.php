<?php

declare(strict_types=1);

namespace App\Inventory\Application\Query\CheckStockAvailability;

use App\Shared\Domain\ValueObject\TenantId;

/**
 * Query to check stock availability for multiple items.
 *
 * Used for cart validation, checkout pre-checks, and bulk availability queries.
 */
final readonly class CheckStockAvailabilityQuery
{
    /**
     * @param array<StockAvailabilityItem> $items Array of items to check
     */
    public function __construct(
        public array $items,
        public TenantId $tenantId,
    ) {
    }
}
