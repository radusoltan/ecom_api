<?php

declare(strict_types=1);

namespace App\Inventory\Application\Query\GetLowStockItems;

use App\Shared\Domain\ValueObject\TenantId;

final readonly class GetLowStockItemsQuery
{
    public function __construct(
        public TenantId $tenantId,
    ) {}
}
