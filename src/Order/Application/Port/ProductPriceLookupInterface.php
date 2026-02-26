<?php

declare(strict_types=1);

namespace App\Order\Application\Port;

use App\Shared\Domain\ValueObject\Money;

/**
 * Port for looking up product prices from outside the Order context.
 *
 * Anti-Corruption Layer interface that decouples Order from Catalog.
 * The infrastructure layer provides the adapter that bridges to Catalog.
 */
interface ProductPriceLookupInterface
{
    /**
     * Look up the current price for a product.
     *
     * @return Money|null The current price, or null if the product doesn't exist
     */
    public function findCurrentPrice(string $productId, ?string $tenantId = null): ?Money;
}
