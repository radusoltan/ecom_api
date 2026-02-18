<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query;

use App\Catalog\Domain\Model\ProductId;

/**
 * GetBundleItemsQuery.
 *
 * Query to retrieve bundle items for a product.
 */
final readonly class GetBundleItemsQuery
{
    public function __construct(
        public ProductId $bundleProductId,
    ) {
    }
}
