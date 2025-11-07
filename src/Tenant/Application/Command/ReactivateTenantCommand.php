<?php

declare(strict_types=1);

namespace App\Tenant\Application\Command;

final readonly class ReactivateTenantCommand
{
    public function __construct(
        public string $tenantId
    ) {
    }
}
