<?php

declare(strict_types=1);

namespace App\Pricing\Application\Query\GetActivePromotions;

use App\Shared\Domain\ValueObject\TenantId;

final readonly class GetActivePromotionsQuery
{
    public function __construct(
        public TenantId $tenantId
    ) {
    }
}
