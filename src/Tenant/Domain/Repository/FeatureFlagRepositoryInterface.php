<?php

declare(strict_types=1);

namespace App\Tenant\Domain\Repository;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tenant\Domain\Model\FeatureFlag;
use App\Tenant\Domain\Model\FeatureFlagId;

interface FeatureFlagRepositoryInterface
{
    public function save(FeatureFlag $flag): void;
    public function findById(FeatureFlagId $id): ?FeatureFlag;
    public function findByTenantAndFeature(TenantId $tenantId, string $featureName): ?FeatureFlag;
    /** @return FeatureFlag[] */
    public function findAllByTenant(TenantId $tenantId): array;
    public function delete(FeatureFlag $flag): void;
}
