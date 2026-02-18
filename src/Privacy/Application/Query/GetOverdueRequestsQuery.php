<?php

declare(strict_types=1);

namespace App\Privacy\Application\Query;

use App\Shared\Domain\ValueObject\TenantId;

final readonly class GetOverdueRequestsQuery
{
    public function __construct(
        public ?TenantId $tenantId = null,
    ) {
    }
}
