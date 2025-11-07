<?php

declare(strict_types=1);

namespace App\Tests\Integration\Performance;

use App\Shared\Infrastructure\Cache\CacheService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Integration tests for caching infrastructure.
 *
 * Tests CacheService with real cache adapter behavior.
 */
final class CacheIntegrationTest extends KernelTestCase
{
    private CacheInterface $cache;
    private CacheService $cacheService;

    protected function setUp(): void
    {
        self::bootKernel();

        // Use ArrayAdapter for predictable testing
        $this->cache = new ArrayAdapter();
        $this->cacheService = new CacheService(
            $this->cache,
            self::getContainer()->get('logger')
        );
    }

    public function testCacheHitReturnsCachedValue(): void
    {
        // Given: Value is cached
        $callbackInvoked = false;
        $this->cacheService->get('test_key', function () use (&$callbackInvoked) {
            $callbackInvoked = true;

            return 'initial_value';
        });

        // When: Getting same key again
        $callbackInvoked = false;
        $result = $this->cacheService->get('test_key', function () use (&$callbackInvoked) {
            $callbackInvoked = true;

            return 'new_value';
        });

        // Then: Should return cached value without invoking callback
        self::assertSame('initial_value', $result);
        self::assertFalse($callbackInvoked, 'Callback should not be invoked on cache hit');
    }

    public function testCacheMissInvokesCallback(): void
    {
        // Given: No cached value
        $callbackInvoked = false;

        // When: Getting uncached key
        $result = $this->cacheService->get('new_key', function () use (&$callbackInvoked) {
            $callbackInvoked = true;

            return 'computed_value';
        });

        // Then: Callback should be invoked
        self::assertTrue($callbackInvoked);
        self::assertSame('computed_value', $result);
    }

    public function testInvalidationRemovesCachedValue(): void
    {
        // Given: Cached value
        $this->cacheService->get('test_key', fn () => 'cached_value');

        // When: Invalidating the cache
        $result = $this->cacheService->invalidate('test_key');

        // Then: Invalidation should succeed
        self::assertTrue($result);

        // And: Next get should invoke callback (cache miss)
        $callbackInvoked = false;
        $this->cacheService->get('test_key', function () use (&$callbackInvoked) {
            $callbackInvoked = true;

            return 'new_value';
        });

        self::assertTrue($callbackInvoked, 'Callback should be invoked after invalidation');
    }

    public function testInvalidateMultipleRemovesAllKeys(): void
    {
        // Given: Multiple cached values
        $this->cacheService->get('key1', fn () => 'value1');
        $this->cacheService->get('key2', fn () => 'value2');
        $this->cacheService->get('key3', fn () => 'value3');

        // When: Invalidating all keys
        $this->cacheService->invalidateMultiple(['key1', 'key2', 'key3']);

        // Then: All should be cache misses
        $invokedCount = 0;
        $this->cacheService->get('key1', function () use (&$invokedCount) {
            ++$invokedCount;

            return 'value1';
        });
        $this->cacheService->get('key2', function () use (&$invokedCount) {
            ++$invokedCount;

            return 'value2';
        });
        $this->cacheService->get('key3', function () use (&$invokedCount) {
            ++$invokedCount;

            return 'value3';
        });

        self::assertSame(3, $invokedCount, 'All three callbacks should be invoked after invalidation');
    }

    public function testTenantIsolation(): void
    {
        // Given: Same key for different tenants
        $tenant1Key = $this->cacheService->tenantKey('tenant-1', 'products');
        $tenant2Key = $this->cacheService->tenantKey('tenant-2', 'products');

        // When: Caching values for both tenants
        $this->cacheService->get($tenant1Key, fn () => 'tenant1_products');
        $this->cacheService->get($tenant2Key, fn () => 'tenant2_products');

        // Then: Values should be isolated
        $result1 = $this->cacheService->get($tenant1Key, fn () => 'should_not_be_called');
        $result2 = $this->cacheService->get($tenant2Key, fn () => 'should_not_be_called');

        self::assertSame('tenant1_products', $result1);
        self::assertSame('tenant2_products', $result2);
    }

    public function testLocaleIsolation(): void
    {
        // Given: Same key for different locales
        $enKey = $this->cacheService->localeKey('en', 'translations');
        $frKey = $this->cacheService->localeKey('fr', 'translations');

        // When: Caching values for both locales
        $this->cacheService->get($enKey, fn () => 'English translations');
        $this->cacheService->get($frKey, fn () => 'Traductions françaises');

        // Then: Values should be isolated
        $resultEn = $this->cacheService->get($enKey, fn () => 'should_not_be_called');
        $resultFr = $this->cacheService->get($frKey, fn () => 'should_not_be_called');

        self::assertSame('English translations', $resultEn);
        self::assertSame('Traductions françaises', $resultFr);
    }

    public function testWarmMultipleStoresAllValues(): void
    {
        // Given: Multiple values to warm
        $data = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
        ];

        // When: Warming cache
        $this->cacheService->warmMultiple($data);

        // Then: All values should be cached (no callback invocations)
        $invokedCount = 0;
        $result1 = $this->cacheService->get('key1', function () use (&$invokedCount) {
            ++$invokedCount;

            return 'new_value';
        });
        $result2 = $this->cacheService->get('key2', function () use (&$invokedCount) {
            ++$invokedCount;

            return 'new_value';
        });
        $result3 = $this->cacheService->get('key3', function () use (&$invokedCount) {
            ++$invokedCount;

            return 'new_value';
        });

        self::assertSame(0, $invokedCount, 'No callbacks should be invoked (cache hits)');
        self::assertSame('value1', $result1);
        self::assertSame('value2', $result2);
        self::assertSame('value3', $result3);
    }

    public function testCacheSupportsComplexDataTypes(): void
    {
        // Given: Complex data structure
        $complexData = [
            'id' => 123,
            'name' => 'Product Name',
            'nested' => [
                'price' => 99.99,
                'currency' => 'USD',
            ],
            'tags' => ['electronics', 'sale', 'featured'],
        ];

        // When: Caching and retrieving
        $this->cacheService->get('complex_key', fn () => $complexData);
        $result = $this->cacheService->get('complex_key', fn () => null);

        // Then: Data structure should be preserved
        self::assertEquals($complexData, $result);
    }

    public function testInvalidateNonExistentKeyHandlesGracefully(): void
    {
        // When: Invalidating non-existent key
        $result = $this->cacheService->invalidate('nonexistent_key');

        // Then: Should complete without error (implementation-dependent result)
        // ArrayAdapter returns true even for non-existent keys
        self::assertIsBool($result);
    }

    public function testTenantLocaleKeyCombinesBoth(): void
    {
        // Given: Tenant + locale key
        $key = $this->cacheService->tenantLocaleKey('tenant-123', 'en_US', 'product:1');

        // When: Caching value
        $this->cacheService->get($key, fn () => 'tenant-locale-value');

        // Then: Value should be retrievable with same key
        $result = $this->cacheService->get($key, fn () => 'should_not_be_called');
        self::assertSame('tenant-locale-value', $result);

        // And: Key format should be correct
        self::assertStringContainsString('tenant:tenant-123', $key);
        self::assertStringContainsString('locale:en_US', $key);
        self::assertStringContainsString('product:1', $key);
    }
}
