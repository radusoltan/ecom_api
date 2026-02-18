<?php

declare(strict_types=1);

namespace App\Payment\Application\Query;

use App\Shared\Domain\ValueObject\TenantId;

final readonly class GetAllPayments
{
    public function __construct(
        public TenantId $tenantId,
    ) {
    }
}
