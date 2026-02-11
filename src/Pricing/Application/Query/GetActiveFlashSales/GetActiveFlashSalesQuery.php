<?php

declare(strict_types=1);

namespace App\Pricing\Application\Query\GetActiveFlashSales;

use App\Shared\Domain\ValueObject\TenantId;

final readonly class GetActiveFlashSalesQuery
{
    public function __construct(
        public TenantId $tenantId
    ) {
    }
}
