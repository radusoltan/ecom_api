<?php

declare(strict_types=1);

namespace App\Tenant\Application\Command;

final readonly class CreateTenantCommand
{
    public function __construct(
        public string $name,
        public string $ownerEmail
    ) {
    }
}
