<?php

declare(strict_types=1);

namespace App\Tenant\Application\Query;

final readonly class GetTenantByIdQuery
{
    public function __construct(
        public string $tenantId,
    ) {
    }
}
