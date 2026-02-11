<?php

declare(strict_types=1);

namespace App\Pricing\Application\Query\GetFlashSaleById;

use App\Pricing\Domain\Model\FlashSaleId;
use App\Shared\Domain\ValueObject\TenantId;

final readonly class GetFlashSaleByIdQuery
{
    public function __construct(
        public FlashSaleId $flashSaleId,
        public TenantId $tenantId
    ) {
    }
}
