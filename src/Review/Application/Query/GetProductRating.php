<?php

declare(strict_types=1);

namespace App\Review\Application\Query;

use App\Catalog\Domain\Model\ProductId;
use App\Shared\Domain\ValueObject\TenantId;

final readonly class GetProductRating
{
    public function __construct(
        public ProductId $productId,
        public TenantId $tenantId
    ) {
    }
}
