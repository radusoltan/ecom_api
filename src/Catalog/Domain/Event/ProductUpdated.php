<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Event;

use App\Catalog\Domain\Model\ProductId;
use App\Shared\Domain\ValueObject\TenantId;

final readonly class ProductUpdated
{
    public function __construct(
        public ProductId $productId,
        public TenantId $tenantId
    ) {
    }
}
