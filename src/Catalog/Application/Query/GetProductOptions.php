<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query;

use App\Catalog\Domain\Model\ProductId;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Query to get all options for a product
 */
final readonly class GetProductOptions
{
    public function __construct(
        public ProductId $productId,
        public TenantId $tenantId
    ) {}
}