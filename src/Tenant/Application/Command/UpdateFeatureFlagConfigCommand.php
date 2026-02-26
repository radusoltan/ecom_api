<?php

declare(strict_types=1);

namespace App\Tenant\Application\Command;

final readonly class UpdateFeatureFlagConfigCommand
{
    /** @param array<string, mixed> $configuration */
    public function __construct(
        public string $tenantId,
        public string $featureName,
        public array $configuration,
    ) {}
}
