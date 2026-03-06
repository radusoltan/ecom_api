<?php

declare(strict_types=1);

namespace App\Tenant\Application\Command;

final readonly class CreateFeatureFlagCommand
{
    /** @param array<string, mixed> $configuration */
    public function __construct(
        public string $tenantId,
        public string $featureName,
        public bool $enabled = false,
        public array $configuration = [],
        public ?string $description = null,
    ) {
    }
}
