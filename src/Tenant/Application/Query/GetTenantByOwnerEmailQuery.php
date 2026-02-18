<?php

declare(strict_types=1);

namespace App\Tenant\Application\Query;

final readonly class GetTenantByOwnerEmailQuery
{
    public function __construct(
        public string $ownerEmail,
    ) {
    }
}
