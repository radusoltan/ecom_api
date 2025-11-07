<?php

declare(strict_types=1);

namespace App\Inventory\Application\Query\GetAllWarehouses;

use App\Shared\Domain\ValueObject\TenantId;

final readonly class GetAllWarehouses
{
    public function __construct(
        public TenantId $tenantId,
        public bool $activeOnly = false,
    ) {
    }
}
