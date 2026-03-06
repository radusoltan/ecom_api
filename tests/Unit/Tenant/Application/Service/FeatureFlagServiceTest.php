<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tenant\Application\Service;

use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Cache\CacheService;
use App\Tenant\Application\Service\FeatureFlagService;
use App\Tenant\Domain\Model\FeatureFlag;
use App\Tenant\Domain\Model\FeatureFlagId;
use App\Tenant\Domain\Repository\FeatureFlagRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

#[CoversClass(FeatureFlagService::class)]
final class FeatureFlagServiceTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    private FeatureFlagRepositoryInterface $repository;
    private CacheService $cacheService;
    private FeatureFlagService $service;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->createMock(FeatureFlagRepositoryInterface::class);

        // Use a real in-memory cache so we can verify caching behaviour
        $arrayAdapter = new ArrayAdapter();
        $this->cacheService = new CacheService(
            $arrayAdapter,
            new NullLogger(),
        );

        $this->service = new FeatureFlagService(
            $this->repository,
            $this->cacheService,
        );

        $this->tenantId = TenantId::fromString(self::TENANT_ID);
    }

    // -----------------------------------------------------------------------
    // isEnabled()
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsTrueWhenFlagIsEnabled(): void
    {
        $flag = $this->makeFlag('dark_mode', enabled: true);

        $this->repository
            ->expects(self::once())
            ->method('findByTenantAndFeature')
            ->with($this->tenantId, 'dark_mode')
            ->willReturn($flag);

        $result = $this->service->isEnabled($this->tenantId, 'dark_mode');

        self::assertTrue($result);
    }

    #[Test]
    public function itReturnsFalseWhenFlagIsDisabled(): void
    {
        $flag = $this->makeFlag('dark_mode', enabled: false);

        $this->repository
            ->expects(self::once())
            ->method('findByTenantAndFeature')
            ->willReturn($flag);

        $result = $this->service->isEnabled($this->tenantId, 'dark_mode');

        self::assertFalse($result);
    }

    #[Test]
    public function itReturnsFalseWhenFlagDoesNotExist(): void
    {
        $this->repository
            ->expects(self::once())
            ->method('findByTenantAndFeature')
            ->willReturn(null);

        $result = $this->service->isEnabled($this->tenantId, 'nonexistent_feature');

        self::assertFalse($result);
    }

    #[Test]
    public function itCachesIsEnabledResultOnSecondCall(): void
    {
        $flag = $this->makeFlag('checkout_v2', enabled: true);

        // Repository must be called only once; second call is served from cache
        $this->repository
            ->expects(self::once())
            ->method('findByTenantAndFeature')
            ->willReturn($flag);

        $first = $this->service->isEnabled($this->tenantId, 'checkout_v2');
        $second = $this->service->isEnabled($this->tenantId, 'checkout_v2');

        self::assertTrue($first);
        self::assertTrue($second);
    }

    // -----------------------------------------------------------------------
    // getConfig()
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsConfigurationWhenFlagExists(): void
    {
        $config = ['max_items' => 5, 'theme' => 'dark'];
        $flag = $this->makeFlag('search_v2', enabled: true, configuration: $config);

        $this->repository
            ->expects(self::once())
            ->method('findByTenantAndFeature')
            ->with($this->tenantId, 'search_v2')
            ->willReturn($flag);

        $result = $this->service->getConfig($this->tenantId, 'search_v2');

        self::assertSame($config, $result);
    }

    #[Test]
    public function itReturnsEmptyArrayWhenFlagDoesNotExist(): void
    {
        $this->repository
            ->expects(self::once())
            ->method('findByTenantAndFeature')
            ->willReturn(null);

        $result = $this->service->getConfig($this->tenantId, 'missing_feature');

        self::assertSame([], $result);
    }

    #[Test]
    public function itReturnsEmptyArrayWhenFlagHasNoConfiguration(): void
    {
        $flag = $this->makeFlag('plain_flag', enabled: true, configuration: []);

        $this->repository
            ->expects(self::once())
            ->method('findByTenantAndFeature')
            ->willReturn($flag);

        $result = $this->service->getConfig($this->tenantId, 'plain_flag');

        self::assertSame([], $result);
    }

    #[Test]
    public function itCachesGetConfigResultOnSecondCall(): void
    {
        $config = ['rate' => 0.1];
        $flag = $this->makeFlag('ab_test', enabled: true, configuration: $config);

        $this->repository
            ->expects(self::once())
            ->method('findByTenantAndFeature')
            ->willReturn($flag);

        $first = $this->service->getConfig($this->tenantId, 'ab_test');
        $second = $this->service->getConfig($this->tenantId, 'ab_test');

        self::assertSame($config, $first);
        self::assertSame($config, $second);
    }

    // -----------------------------------------------------------------------
    // invalidateCache()
    // -----------------------------------------------------------------------

    #[Test]
    public function itInvalidatesCacheAndQueriesRepositoryAgainAfterInvalidation(): void
    {
        $flag = $this->makeFlag('new_checkout', enabled: true);

        // First call populates the cache; after invalidation a second query occurs
        $this->repository
            ->expects(self::exactly(2))
            ->method('findByTenantAndFeature')
            ->willReturn($flag);

        $this->service->isEnabled($this->tenantId, 'new_checkout');
        $this->service->invalidateCache($this->tenantId, 'new_checkout');
        $this->service->isEnabled($this->tenantId, 'new_checkout');
    }

    #[Test]
    public function itInvalidatesBothIsEnabledAndConfigCacheKeys(): void
    {
        $config = ['limit' => 10];
        $flag = $this->makeFlag('feature_x', enabled: false, configuration: $config);

        // Two separate repository calls: once for isEnabled, once for getConfig
        $this->repository
            ->expects(self::exactly(4))
            ->method('findByTenantAndFeature')
            ->willReturn($flag);

        // Populate both caches
        $this->service->isEnabled($this->tenantId, 'feature_x');
        $this->service->getConfig($this->tenantId, 'feature_x');

        // Invalidate both
        $this->service->invalidateCache($this->tenantId, 'feature_x');

        // Both cache entries should be gone -> two more repository calls
        $this->service->isEnabled($this->tenantId, 'feature_x');
        $this->service->getConfig($this->tenantId, 'feature_x');
    }

    // -----------------------------------------------------------------------
    // Cache key isolation between tenants
    // -----------------------------------------------------------------------

    #[Test]
    public function itIsolatesCacheBetweenDifferentTenants(): void
    {
        $tenantA = TenantId::fromString(self::TENANT_ID);
        $tenantB = TenantId::generate();

        $flagA = $this->makeFlag('multi_currency', enabled: true);
        $flagB = $this->makeFlag('multi_currency', enabled: false);

        // Repository is called once per tenant (separate cache keys)
        $this->repository
            ->expects(self::exactly(2))
            ->method('findByTenantAndFeature')
            ->willReturnOnConsecutiveCalls($flagA, $flagB);

        $resultA = $this->service->isEnabled($tenantA, 'multi_currency');
        $resultB = $this->service->isEnabled($tenantB, 'multi_currency');

        self::assertTrue($resultA);
        self::assertFalse($resultB);
    }

    // -----------------------------------------------------------------------
    // Helper
    // -----------------------------------------------------------------------

    /** @param array<string, mixed> $configuration */
    private function makeFlag(
        string $featureName,
        bool $enabled,
        array $configuration = [],
    ): FeatureFlag {
        return FeatureFlag::create(
            id: FeatureFlagId::generate(),
            tenantId: $this->tenantId,
            featureName: $featureName,
            enabled: $enabled,
            configuration: $configuration,
        );
    }
}
