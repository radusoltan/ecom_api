<?php

declare(strict_types=1);

namespace App\Pricing\Application\Query\GetActivePriceLists;

use App\Shared\Domain\ValueObject\TenantId;

final readonly class GetActivePriceListsQuery
{
    public function __construct(
        public TenantId $tenantId
    ) {
    }
}
