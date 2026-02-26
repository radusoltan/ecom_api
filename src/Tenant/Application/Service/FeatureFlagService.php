<?php

declare(strict_types=1);

namespace App\Tenant\Application\Service;

use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Cache\CacheService;
use App\Tenant\Domain\Repository\FeatureFlagRepositoryInterface;

final readonly class FeatureFlagService
{
    public function __construct(
        private FeatureFlagRepositoryInterface $repository,
        private CacheService $cache,
    ) {
    }

    public function isEnabled(TenantId $tenantId, string $featureName): bool
    {
        $cacheKey = $this->cache->tenantKey($tenantId->toString(), "feature_flag:{$featureName}");

        return $this->cache->get($cacheKey, function () use ($tenantId, $featureName) {
            $flag = $this->repository->findByTenantAndFeature($tenantId, $featureName);

            return $flag?->isEnabled() ?? false;
        }, 300);
    }

    /** @return array<string, mixed> */
    public function getConfig(TenantId $tenantId, string $featureName): array
    {
        $cacheKey = $this->cache->tenantKey($tenantId->toString(), "feature_flag_config:{$featureName}");

        return $this->cache->get($cacheKey, function () use ($tenantId, $featureName) {
            $flag = $this->repository->findByTenantAndFeature($tenantId, $featureName);

            return $flag?->configuration() ?? [];
        }, 300);
    }

    public function invalidateCache(TenantId $tenantId, string $featureName): void
    {
        $this->cache->invalidate($this->cache->tenantKey($tenantId->toString(), "feature_flag:{$featureName}"));
        $this->cache->invalidate($this->cache->tenantKey($tenantId->toString(), "feature_flag_config:{$featureName}"));
    }
}
