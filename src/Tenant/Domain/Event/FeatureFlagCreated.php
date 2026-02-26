<?php

declare(strict_types=1);

namespace App\Tenant\Domain\Event;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tenant\Domain\Model\FeatureFlagId;

final readonly class FeatureFlagCreated
{
    public function __construct(
        public FeatureFlagId $flagId,
        public TenantId $tenantId,
        public string $featureName,
        public bool $enabled,
    ) {}
}
