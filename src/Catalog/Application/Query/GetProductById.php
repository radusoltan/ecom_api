<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query;

use App\Catalog\Domain\Model\ProductId;
use App\Shared\Domain\ValueObject\TenantId;

final readonly class GetProductById
{
    public function __construct(
        public TenantId $tenantId,
        public ProductId $id,
    ) {
    }
}
