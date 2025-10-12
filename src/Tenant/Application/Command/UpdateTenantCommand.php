<?php

declare(strict_types=1);

namespace App\Tenant\Application\Command;

final readonly class UpdateTenantCommand
{
    public function __construct(
        public string $id,
        public string $name,
        public string $ownerEmail
    ) {
    }
}
