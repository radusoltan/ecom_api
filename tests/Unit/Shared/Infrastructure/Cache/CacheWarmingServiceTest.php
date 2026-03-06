<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Cache;

use App\Internationalization\Domain\Model\Locale;
use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Cache\CacheService;
use App\Shared\Infrastructure\Cache\CacheWarmingService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class CacheWarmingServiceTest extends TestCase
{
    private CacheService $cacheService;
    private Connection $connection;

    protected function setUp(): void
    {
        $this->cacheService = $this->createMock(CacheService::class);
        $this->connection = $this->createMock(Connection::class);
    }

    public function testWarmTenantWithLocale(): void
    {
        $tenantId = TenantId::generate();
        $locale = Locale::fromString('en_US');

        // Translations query
        $this->connection
            ->method('fetchAllAssociative')
            ->willReturnOnConsecutiveCalls(
                [['domain' => 'messages', 'key' => 'hello', 'value' => 'Hello']],
                [['id' => 'p1', 'sku' => 'SKU1', 'name_translations' => '{}', 'price_amount' => 1000, 'price_currency' => 'EUR', 'status' => 'active']],
                [['id' => 'c1', 'slug' => 'electronics', 'name_translations' => '{}', 'parent_id' => null, 'position' => 1]],
            );

        $this->cacheService
            ->method('tenantLocaleKey')
            ->willReturn('tenant:xxx:locale:en_US:translation:messages:hello');

        $this->cacheService
            ->method('tenantKey')
            ->willReturnCallback(fn (string $t, string $k) => "tenant:{$t}:{$k}");

        $this->cacheService
            ->expects(self::exactly(3))
            ->method('warmMultiple');

        $service = new CacheWarmingService($this->cacheService, $this->connection, new NullLogger());
        $stats = $service->warmTenant($tenantId, $locale);

        self::assertArrayHasKey('translations', $stats);
        self::assertArrayHasKey('products', $stats);
        self::assertArrayHasKey('categories', $stats);
        self::assertSame(1, $stats['translations']);
        self::assertSame(1, $stats['products']);
        self::assertSame(1, $stats['categories']);
    }

    public function testWarmTenantWithoutLocale(): void
    {
        $tenantId = TenantId::generate();

        $this->connection
            ->method('fetchAllAssociative')
            ->willReturnOnConsecutiveCalls(
                [], // products (empty)
                [], // categories (empty)
            );

        $service = new CacheWarmingService($this->cacheService, $this->connection, new NullLogger());
        $stats = $service->warmTenant($tenantId);

        self::assertSame(0, $stats['translations']);
        self::assertSame(0, $stats['products']);
        self::assertSame(0, $stats['categories']);
    }

    public function testWarmTenantHandlesException(): void
    {
        $tenantId = TenantId::generate();
        $locale = Locale::fromString('en_US');

        $this->connection
            ->method('fetchAllAssociative')
            ->willThrowException(new \RuntimeException('DB error'));

        $service = new CacheWarmingService($this->cacheService, $this->connection, new NullLogger());
        $stats = $service->warmTenant($tenantId, $locale);

        // Stats should be returned with zeros after exception
        self::assertSame(0, $stats['translations']);
        self::assertSame(0, $stats['products']);
        self::assertSame(0, $stats['categories']);
    }

    public function testWarmAllTenants(): void
    {
        $tenantUuid = '00000000-0000-4000-8000-000000000001';

        $this->connection
            ->method('fetchAllAssociative')
            ->willReturnOnConsecutiveCalls(
                [['id' => $tenantUuid, 'name' => 'Test Tenant']], // getActiveTenants
                [], // products for tenant
                [], // categories for tenant
            );

        $service = new CacheWarmingService($this->cacheService, $this->connection, new NullLogger());
        $totalStats = $service->warmAllTenants();

        self::assertArrayHasKey($tenantUuid, $totalStats);
    }

    public function testWarmAllTenantsNoActiveTenants(): void
    {
        $this->connection
            ->method('fetchAllAssociative')
            ->willReturn([]); // No active tenants

        $service = new CacheWarmingService($this->cacheService, $this->connection, new NullLogger());
        $totalStats = $service->warmAllTenants();

        self::assertEmpty($totalStats);
    }

    public function testWarmProductsCachesByIdAndSku(): void
    {
        $tenantId = TenantId::generate();

        $this->connection
            ->method('fetchAllAssociative')
            ->willReturnOnConsecutiveCalls(
                [
                    ['id' => 'p1', 'sku' => 'SKU-001', 'name_translations' => '{}', 'price_amount' => 1000, 'price_currency' => 'EUR', 'status' => 'active'],
                    ['id' => 'p2', 'sku' => 'SKU-002', 'name_translations' => '{}', 'price_amount' => 2000, 'price_currency' => 'EUR', 'status' => 'active'],
                ],
                [], // categories
            );

        $tenantKeys = [];
        $this->cacheService
            ->method('tenantKey')
            ->willReturnCallback(function (string $t, string $k) use (&$tenantKeys) {
                $key = "tenant:{$t}:{$k}";
                $tenantKeys[] = $key;

                return $key;
            });

        $warmData = [];
        $this->cacheService
            ->method('warmMultiple')
            ->willReturnCallback(function (array $data) use (&$warmData) {
                $warmData = array_merge($warmData, $data);
            });

        $service = new CacheWarmingService($this->cacheService, $this->connection, new NullLogger());
        $stats = $service->warmTenant($tenantId);

        // 2 products, each cached by ID and SKU = 4 entries
        self::assertSame(2, $stats['products']);
    }

    public function testWarmTranslationsEmptyResult(): void
    {
        $tenantId = TenantId::generate();
        $locale = Locale::fromString('de_DE');

        $this->connection
            ->method('fetchAllAssociative')
            ->willReturnOnConsecutiveCalls(
                [], // translations (empty)
                [], // products
                [], // categories
            );

        // warmMultiple should NOT be called for empty translations
        $this->cacheService
            ->expects(self::never())
            ->method('warmMultiple');

        $service = new CacheWarmingService($this->cacheService, $this->connection, new NullLogger());
        $stats = $service->warmTenant($tenantId, $locale);

        self::assertSame(0, $stats['translations']);
    }
}
