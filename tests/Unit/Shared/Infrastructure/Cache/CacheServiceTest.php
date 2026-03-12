<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Cache;

use App\Shared\Infrastructure\Cache\CacheService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * Unit tests for CacheService.
 *
 * @covers \App\Shared\Infrastructure\Cache\CacheService
 */
final class CacheServiceTest extends TestCase
{
    private CacheInterface $cache;
    private LoggerInterface $logger;
    private CacheService $cacheService;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(CacheInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->cacheService = new CacheService($this->cache, $this->logger);
    }

    public function testGetWithCacheHitReturnsValue(): void
    {
        // Given: Cache returns a value (cache hit)
        $this->cache
            ->expects(self::once())
            ->method('get')
            ->with('test_key')
            ->willReturn('cached_value');

        $this->logger
            ->expects(self::once())
            ->method('debug')
            ->with('Cache hit', self::anything());

        // When: Getting cached value
        $result = $this->cacheService->get(
            'test_key',
            fn () => 'computed_value'
        );

        // Then: Cached value should be returned
        self::assertSame('cached_value', $result);
    }

    public function testGetWithCacheMissComputesValue(): void
    {
        // Given: Cache callback is invoked (cache miss)
        $callbackInvoked = false;

        $this->cache
            ->expects(self::once())
            ->method('get')
            ->with('test_key')
            ->willReturnCallback(function (string $key, callable $callback) use (&$callbackInvoked) {
                $item = $this->createMock(ItemInterface::class);
                $item->method('getKey')->willReturn($key);
                $item->expects(self::once())->method('expiresAfter')->with(3600);

                $callbackInvoked = true;

                return $callback($item);
            });

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with('Cache miss', self::anything());

        // When: Getting value with cache miss
        $result = $this->cacheService->get(
            'test_key',
            fn () => 'computed_value'
        );

        // Then: Callback should be invoked and value returned
        self::assertTrue($callbackInvoked);
        self::assertSame('computed_value', $result);
    }

    public function testGetAppliesTtl(): void
    {
        // Given: Cache with specific TTL
        $this->cache
            ->expects(self::once())
            ->method('get')
            ->willReturnCallback(function (string $key, callable $callback) {
                $item = $this->createMock(ItemInterface::class);
                $item->method('getKey')->willReturn($key);
                $item->expects(self::once())->method('expiresAfter')->with(1800); // 30 minutes

                return $callback($item);
            });

        // When: Getting value with custom TTL
        $this->cacheService->get(
            'test_key',
            fn () => 'value',
            ttl: 1800
        );
    }

    public function testGetAppliesTags(): void
    {
        // Given: Cache with tags
        $tags = ['tag1', 'tag2', 'tag3'];

        $this->cache
            ->expects(self::once())
            ->method('get')
            ->willReturnCallback(function (string $key, callable $callback) use ($tags) {
                $item = $this->createMock(ItemInterface::class);
                $item->method('getKey')->willReturn($key);
                $item->expects(self::once())->method('expiresAfter');
                $item->expects(self::once())->method('tag')->with($tags);

                return $callback($item);
            });

        // When: Getting value with tags
        $this->cacheService->get(
            'test_key',
            fn () => 'value',
            tags: $tags
        );
    }

    public function testGetDoesNotApplyTagsWhenEmpty(): void
    {
        // Given: Cache without tags
        $this->cache
            ->expects(self::once())
            ->method('get')
            ->willReturnCallback(function (string $key, callable $callback) {
                $item = $this->createMock(ItemInterface::class);
                $item->method('getKey')->willReturn($key);
                $item->expects(self::once())->method('expiresAfter');
                $item->expects(self::never())->method('tag'); // Should not be called

                return $callback($item);
            });

        // When: Getting value without tags
        $this->cacheService->get(
            'test_key',
            fn () => 'value',
            tags: []
        );
    }

    public function testGetFallsBackOnException(): void
    {
        // Given: Cache throws exception
        $this->cache
            ->expects(self::once())
            ->method('get')
            ->willThrowException(new \RuntimeException('Cache error'));

        $this->logger
            ->expects(self::once())
            ->method('error')
            ->with('Cache operation failed', [
                'key' => 'test_key',
                'error' => 'Cache error',
            ]);

        // When: Getting value with cache failure
        $result = $this->cacheService->get(
            'test_key',
            fn () => 'fallback_value'
        );

        // Then: Fallback value should be returned
        self::assertSame('fallback_value', $result);
    }

    public function testInvalidateDeletesKey(): void
    {
        // Given: Cache delete succeeds
        $this->cache
            ->expects(self::once())
            ->method('delete')
            ->with('test_key')
            ->willReturn(true);

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with('Cache invalidated', [
                'key' => 'test_key',
                'success' => true,
            ]);

        // When: Invalidating key
        $result = $this->cacheService->invalidate('test_key');

        // Then: Should return true
        self::assertTrue($result);
    }

    public function testInvalidateReturnsFalseOnFailure(): void
    {
        // Given: Cache delete fails
        $this->cache
            ->expects(self::once())
            ->method('delete')
            ->with('test_key')
            ->willReturn(false);

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with('Cache invalidated', [
                'key' => 'test_key',
                'success' => false,
            ]);

        // When: Invalidating key
        $result = $this->cacheService->invalidate('test_key');

        // Then: Should return false
        self::assertFalse($result);
    }

    public function testInvalidateHandlesException(): void
    {
        // Given: Cache delete throws exception
        $this->cache
            ->expects(self::once())
            ->method('delete')
            ->willThrowException(new \RuntimeException('Delete error'));

        $this->logger
            ->expects(self::once())
            ->method('error')
            ->with('Cache invalidation failed', [
                'key' => 'test_key',
                'error' => 'Delete error',
            ]);

        // When: Invalidating key with exception
        $result = $this->cacheService->invalidate('test_key');

        // Then: Should return false
        self::assertFalse($result);
    }

    public function testInvalidateMultipleDeletesAllKeys(): void
    {
        // Given: Multiple keys to invalidate
        $keys = ['key1', 'key2', 'key3'];

        $this->cache
            ->expects(self::exactly(3))
            ->method('delete')
            ->willReturnOnConsecutiveCalls(true, true, true);

        // When: Invalidating multiple keys
        $this->cacheService->invalidateMultiple($keys);
    }

    public function testInvalidateTagsUsesTagAwareBackend(): void
    {
        $tagAwareCache = $this->createMock(TagAwareCacheInterface::class);
        $tagAwareCache
            ->expects(self::once())
            ->method('invalidateTags')
            ->with(['tenant.tenant-123.products'])
            ->willReturn(true);

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with('Cache tags invalidated', [
                'tags' => ['tenant.tenant-123.products'],
                'success' => true,
            ]);

        $cacheService = new CacheService($tagAwareCache, $this->logger);

        self::assertTrue($cacheService->invalidateTags(['tenant.tenant-123.products']));
    }

    public function testInvalidateTagsReturnsFalseWhenBackendIsNotTagAware(): void
    {
        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with('Cache backend does not support tag invalidation', [
                'tags' => ['products'],
            ]);

        self::assertFalse($this->cacheService->invalidateTags(['products']));
    }

    public function testTenantKeyFormatsCorrectly(): void
    {
        // When: Generating tenant key
        $key = $this->cacheService->tenantKey('tenant-123', 'products:all');

        // Then: Should format correctly
        self::assertSame('tenant:tenant-123:products:all', $key);
    }

    public function testLocaleKeyFormatsCorrectly(): void
    {
        // When: Generating locale key
        $key = $this->cacheService->localeKey('en', 'translations:categories');

        // Then: Should format correctly
        self::assertSame('locale:en:translations:categories', $key);
    }

    public function testTenantLocaleKeyFormatsCorrectly(): void
    {
        // When: Generating tenant + locale key
        $key = $this->cacheService->tenantLocaleKey('tenant-123', 'en', 'products');

        // Then: Should format correctly
        self::assertSame('tenant:tenant-123:locale:en:products', $key);
    }

    public function testTenantQueryKeyIsStableAcrossQueryParameterOrder(): void
    {
        $firstKey = $this->cacheService->tenantQueryKey('tenant-123', 'catalog', 'products', [
            'page' => 1,
            'filters' => [
                'priceMax' => 5000,
                'priceMin' => 1000,
            ],
            'sort' => 'name',
        ]);

        $secondKey = $this->cacheService->tenantQueryKey('tenant-123', 'catalog', 'products', [
            'sort' => 'name',
            'filters' => [
                'priceMin' => 1000,
                'priceMax' => 5000,
            ],
            'page' => 1,
        ]);

        self::assertSame($firstKey, $secondKey);
    }

    public function testTenantQueryKeyKeepsTenantIsolation(): void
    {
        $tenantOneKey = $this->cacheService->tenantQueryKey('tenant-1', 'catalog', 'products', ['page' => 1]);
        $tenantTwoKey = $this->cacheService->tenantQueryKey('tenant-2', 'catalog', 'products', ['page' => 1]);

        self::assertNotSame($tenantOneKey, $tenantTwoKey);
        self::assertStringStartsWith('tenant:tenant-1:catalog:products:', $tenantOneKey);
        self::assertStringStartsWith('tenant:tenant-2:catalog:products:', $tenantTwoKey);
    }

    public function testWarmMultipleStoresAllValues(): void
    {
        // Given: Multiple values to warm
        $data = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
        ];

        $this->cache
            ->expects(self::exactly(3))
            ->method('get')
            ->willReturnCallback(function (string $key, callable $callback) {
                $item = $this->createMock(ItemInterface::class);
                $item->expects(self::once())->method('expiresAfter')->with(3600);

                return $callback($item);
            });

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with('Cache warmed', self::callback(function (array $context) {
                return 3 === $context['count']
                    && 3 === $context['total']
                    && isset($context['duration_ms']);
            }));

        // When: Warming cache
        $this->cacheService->warmMultiple($data);
    }

    public function testWarmMultipleWithCustomTtl(): void
    {
        // Given: Values to warm with custom TTL
        $data = ['key1' => 'value1'];

        $this->cache
            ->expects(self::once())
            ->method('get')
            ->willReturnCallback(function (string $key, callable $callback) {
                $item = $this->createMock(ItemInterface::class);
                $item->expects(self::once())->method('expiresAfter')->with(7200); // 2 hours

                return $callback($item);
            });

        // When: Warming cache with custom TTL
        $this->cacheService->warmMultiple($data, ttl: 7200);
    }

    public function testWarmMultipleContinuesOnPartialFailure(): void
    {
        // Given: Multiple values, one fails
        $data = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
        ];

        $this->cache
            ->expects(self::exactly(3))
            ->method('get')
            ->willReturnOnConsecutiveCalls(
                'value1', // Success
                $this->throwException(new \RuntimeException('Warming error')), // Failure
                'value3'  // Success
            );

        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with('Cache warming failed for key', [
                'key' => 'key2',
                'error' => 'Warming error',
            ]);

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with('Cache warmed', self::callback(function (array $context) {
                // Only 2 successful, 3 total
                return 2 === $context['count']
                    && 3 === $context['total'];
            }));

        // When: Warming cache with partial failure
        $this->cacheService->warmMultiple($data);
    }

    public function testWarmMultipleWithEmptyData(): void
    {
        // Given: Empty data
        $this->cache
            ->expects(self::never())
            ->method('get');

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with('Cache warmed', self::callback(function (array $context) {
                return 0 === $context['count']
                    && 0 === $context['total']
                    && isset($context['duration_ms'])
                    && is_numeric($context['duration_ms']);
            }));

        // When: Warming cache with empty data
        $this->cacheService->warmMultiple([]);
    }

    public function testGetLogsPerformanceMetrics(): void
    {
        // Given: Cache returns value
        $this->cache
            ->expects(self::once())
            ->method('get')
            ->willReturn('value');

        $this->logger
            ->expects(self::once())
            ->method('debug')
            ->with('Cache hit', self::callback(function (array $context) {
                return isset($context['key'])
                    && isset($context['duration_ms'])
                    && is_numeric($context['duration_ms']);
            }));

        // When: Getting value
        $this->cacheService->get('test_key', fn () => 'value');
    }

    public function testInvalidateMultipleHandlesEmptyArray(): void
    {
        // Given: Empty keys array
        $this->cache
            ->expects(self::never())
            ->method('delete');

        // When: Invalidating empty array
        $this->cacheService->invalidateMultiple([]);
    }
}
