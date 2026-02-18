<?php

declare(strict_types=1);

namespace App\Pricing\Application\Query\GetUpcomingFlashSales;

use App\Shared\Domain\ValueObject\TenantId;

final readonly class GetUpcomingFlashSalesQuery
{
    public function __construct(
        public TenantId $tenantId,
    ) {
    }
}
