<?php

declare(strict_types=1);

namespace App\Inventory\Application\Query\GetStockByProduct;

use App\Catalog\Domain\Model\ProductId;
use App\Shared\Domain\ValueObject\TenantId;

final readonly class GetStockByProductQuery
{
    public function __construct(
        public ProductId $productId,
        public TenantId $tenantId,
    ) {}
}
