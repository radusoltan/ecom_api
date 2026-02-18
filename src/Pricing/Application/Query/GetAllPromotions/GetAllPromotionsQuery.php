<?php

declare(strict_types=1);

namespace App\Pricing\Application\Query\GetAllPromotions;

use App\Shared\Domain\ValueObject\TenantId;

final readonly class GetAllPromotionsQuery
{
    public function __construct(
        public TenantId $tenantId,
    ) {
    }
}
