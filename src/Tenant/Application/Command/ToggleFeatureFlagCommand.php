<?php

declare(strict_types=1);

namespace App\Tenant\Application\Command;

final readonly class ToggleFeatureFlagCommand
{
    public function __construct(
        public string $tenantId,
        public string $featureName,
        public bool $enabled,
    ) {}
}
