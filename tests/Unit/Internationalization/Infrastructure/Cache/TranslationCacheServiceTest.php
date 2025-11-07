<?php

declare(strict_types=1);

namespace App\Tests\Unit\Internationalization\Infrastructure\Cache;

use App\Internationalization\Domain\Model\TranslationDomain;
use App\Internationalization\Domain\Model\TranslationEntry;
use App\Internationalization\Domain\Model\TranslationKey;
use App\Internationalization\Domain\Model\TranslationValue;
use App\Internationalization\Domain\Repository\TranslationEntryRepositoryInterface;
use App\Internationalization\Infrastructure\Cache\TranslationCacheService;
use App\Shared\Domain\ValueObject\LanguageCode;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Unit tests for TranslationCacheService.
 *
 * Tests:
 * - Cache hit with existing data
 * - Cache miss with fallback to database
 * - Cache invalidation (single entry)
 * - Bulk invalidation (all locales/domains)
 * - Cache warming
 * - Error handling and fallback
 */
final class TranslationCacheServiceTest extends TestCase
{
    private CacheInterface $cache;
    private TranslationEntryRepositoryInterface $repository;
    private LoggerInterface $logger;
    private TranslationCacheService $service;
    private TenantId $tenantId;
    private LanguageCode $locale;
    private TranslationDomain $domain;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(CacheInterface::class);
        $this->repository = $this->createMock(TranslationEntryRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new TranslationCacheService(
            cache: $this->cache,
            repository: $this->repository,
            logger: $this->logger,
            cacheTtl: 3600, // 1 hour for testing
        );

        $this->tenantId = TenantId::fromString('51bd1332-307c-480c-bd3c-6bfe5ccd7829');
        $this->locale = LanguageCode::fromString('en');
        $this->domain = TranslationDomain::fromString('messages');
    }

    public function testGetTranslationsCacheHit(): void
    {
        // Arrange
        $expectedTranslations = [
            'hello.world' => 'Hello World',
            'goodbye' => 'Goodbye',
        ];

        // Cache returns data directly (cache hit)
        $this->cache->expects($this->once())
            ->method('get')
            ->willReturn($expectedTranslations);

        $this->repository->expects($this->never())
            ->method('findAll');

        // Act
        $result = $this->service->getTranslations($this->tenantId, $this->locale, $this->domain);

        // Assert
        $this->assertSame($expectedTranslations, $result);
    }

    public function testGetTranslationsCacheMiss(): void
    {
        // Arrange
        $entry1 = TranslationEntry::create(
            tenantId: $this->tenantId,
            locale: $this->locale,
            domain: $this->domain,
            key: TranslationKey::fromString('hello.world'),
            value: TranslationValue::fromString('Hello World'),
        );

        $entry2 = TranslationEntry::create(
            tenantId: $this->tenantId,
            locale: $this->locale,
            domain: $this->domain,
            key: TranslationKey::fromString('goodbye'),
            value: TranslationValue::fromString('Goodbye'),
        );

        // Cache miss, will call callback
        $this->cache->expects($this->once())
            ->method('get')
            ->willReturnCallback(function ($key, $callback) {
                $item = $this->createMock(ItemInterface::class);
                $item->expects($this->once())
                    ->method('expiresAfter')
                    ->with(3600);

                return $callback($item);
            });

        $this->repository->expects($this->once())
            ->method('findAll')
            ->with(
                tenantId: $this->tenantId,
                filters: [
                    'locale' => 'en',
                    'domain' => 'messages',
                ],
                page: 1,
                perPage: 10000
            )
            ->willReturn([$entry1, $entry2]);

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Translation cache miss', $this->isType('array'));

        // Act
        $result = $this->service->getTranslations($this->tenantId, $this->locale, $this->domain);

        // Assert
        $this->assertEquals([
            'hello.world' => 'Hello World',
            'goodbye' => 'Goodbye',
        ], $result);
    }

    public function testGetTranslationsCacheMissReturnsEmptyArray(): void
    {
        // Arrange - no translations in database
        $this->cache->expects($this->once())
            ->method('get')
            ->willReturnCallback(function ($key, $callback) {
                $item = $this->createMock(ItemInterface::class);

                return $callback($item);
            });

        $this->repository->expects($this->once())
            ->method('findAll')
            ->willReturn([]);

        // Act
        $result = $this->service->getTranslations($this->tenantId, $this->locale, $this->domain);

        // Assert
        $this->assertSame([], $result);
    }

    public function testGetTranslationsCacheErrorFallsBackToDatabase(): void
    {
        // Arrange
        $entry = TranslationEntry::create(
            tenantId: $this->tenantId,
            locale: $this->locale,
            domain: $this->domain,
            key: TranslationKey::fromString('hello.world'),
            value: TranslationValue::fromString('Hello World'),
        );

        // Cache throws exception
        $this->cache->expects($this->once())
            ->method('get')
            ->willThrowException(new \RuntimeException('Cache error'));

        $this->repository->expects($this->once())
            ->method('findAll')
            ->willReturn([$entry]);

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Translation cache error, falling back to database', $this->isType('array'));

        // Act
        $result = $this->service->getTranslations($this->tenantId, $this->locale, $this->domain);

        // Assert
        $this->assertEquals(['hello.world' => 'Hello World'], $result);
    }

    public function testInvalidate(): void
    {
        // Arrange
        $expectedKey = 'translations:51bd1332-307c-480c-bd3c-6bfe5ccd7829:en:messages';

        $this->cache->expects($this->once())
            ->method('delete')
            ->with($expectedKey)
            ->willReturn(true);

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Translation cache invalidated', $this->isType('array'));

        // Act
        $this->service->invalidate($this->tenantId, $this->locale, $this->domain);
    }

    public function testInvalidateHandlesErrors(): void
    {
        // Arrange
        $this->cache->expects($this->once())
            ->method('delete')
            ->willThrowException(new \RuntimeException('Delete failed'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Failed to invalidate translation cache', $this->isType('array'));

        // Act & Assert - should not throw
        $this->service->invalidate($this->tenantId, $this->locale, $this->domain);
    }

    public function testInvalidateAll(): void
    {
        // Arrange - expect 30 delete calls (3 locales × 10 domains)
        $this->cache->expects($this->exactly(30))
            ->method('delete')
            ->willReturn(true);

        // Logger is called 31 times: 30 invalidate() calls + 1 invalidateAll() call
        $this->logger->expects($this->exactly(31))
            ->method('info');

        // Act
        $this->service->invalidateAll($this->tenantId);
    }

    public function testInvalidateAllContinuesOnError(): void
    {
        // Arrange - test that InvalidArgumentException from value objects is caught
        // Since we're using hardcoded valid values, we need to test the catch block differently
        // Let's just verify the method completes successfully even if one invalidate() call fails
        $callCount = 0;
        $this->cache->expects($this->exactly(30))
            ->method('delete')
            ->willReturnCallback(function () use (&$callCount) {
                ++$callCount;
                // Throw exception on 5th call (caught by invalidate's internal try-catch)
                if (5 === $callCount) {
                    throw new \RuntimeException('Delete failed');
                }

                return true;
            });

        // Since invalidate() has its own try-catch, it logs error and continues
        // Logger gets called for error
        $this->logger->expects($this->exactly(1))
            ->method('error')
            ->with('Failed to invalidate translation cache', $this->isType('array'));

        // Act & Assert - should not throw
        $this->service->invalidateAll($this->tenantId);
    }

    public function testWarmCache(): void
    {
        // Arrange - mock getTranslations for all locale/domain combinations
        $entry = TranslationEntry::create(
            tenantId: $this->tenantId,
            locale: LanguageCode::fromString('en'),
            domain: TranslationDomain::fromString('messages'),
            key: TranslationKey::fromString('test'),
            value: TranslationValue::fromString('Test'),
        );

        // Expect 30 cache get calls (3 locales × 10 domains)
        $this->cache->expects($this->exactly(30))
            ->method('get')
            ->willReturnCallback(function ($key, $callback) {
                $item = $this->createMock(ItemInterface::class);

                // Return callback result which will call repository
                return $callback($item);
            });

        // Expect 30 repository findAll calls
        $this->repository->expects($this->exactly(30))
            ->method('findAll')
            ->willReturn([$entry]);

        // Logger is called 31 times: 30 cache misses + 1 warming completed
        $this->logger->expects($this->exactly(31))
            ->method('info');

        // Act
        $warmedCount = $this->service->warmCache($this->tenantId);

        // Assert
        $this->assertEquals(30, $warmedCount);
    }

    public function testWarmCacheSkipsEmptyTranslations(): void
    {
        // Arrange - some locales have no translations
        $callCount = 0;
        $entry = TranslationEntry::create(
            tenantId: $this->tenantId,
            locale: LanguageCode::fromString('en'),
            domain: TranslationDomain::fromString('messages'),
            key: TranslationKey::fromString('test'),
            value: TranslationValue::fromString('Test'),
        );

        // All 30 combinations are attempted
        $this->cache->expects($this->any())
            ->method('get')
            ->willReturnCallback(function ($key, $callback) use (&$callCount) {
                $item = $this->createMock(ItemInterface::class);

                return $callback($item);
            });

        $this->repository->expects($this->any())
            ->method('findAll')
            ->willReturnCallback(function () use (&$callCount, $entry) {
                ++$callCount;

                // First 15 calls return translations, rest return empty
                return $callCount <= 15 ? [$entry] : [];
            });

        // Act
        $warmedCount = $this->service->warmCache($this->tenantId);

        // Assert - only 15 combinations had translations
        $this->assertEquals(15, $warmedCount);
    }

    public function testWarmCacheContinuesOnError(): void
    {
        // Arrange - Exception in getTranslations is caught and falls back to database
        // So even with cache error, all 30 still get warmed (from DB)
        $callCount = 0;
        $entry = TranslationEntry::create(
            tenantId: $this->tenantId,
            locale: LanguageCode::fromString('en'),
            domain: TranslationDomain::fromString('messages'),
            key: TranslationKey::fromString('test'),
            value: TranslationValue::fromString('Test'),
        );

        $this->cache->expects($this->any())
            ->method('get')
            ->willReturnCallback(function ($key, $callback) use (&$callCount) {
                ++$callCount;
                // On the 5th call, throw exception (caught by getTranslations, falls back to DB)
                if (5 === $callCount) {
                    throw new \RuntimeException('Cache error');
                }
                $item = $this->createMock(ItemInterface::class);

                return $callback($item);
            });

        // Repository called 30 times: 29 from cache callback + 1 from fallback
        $this->repository->expects($this->any())
            ->method('findAll')
            ->willReturn([$entry]);

        // Logger logs the cache error once
        $this->logger->expects($this->exactly(1))
            ->method('error')
            ->with('Translation cache error, falling back to database', $this->isType('array'));

        // Act
        $warmedCount = $this->service->warmCache($this->tenantId);

        // Assert - All 30 combinations are warmed (1 from DB fallback, 29 from cache)
        $this->assertEquals(30, $warmedCount);
    }

    public function testClearCache(): void
    {
        // Arrange - clearCache calls invalidateAll which calls invalidate 30 times
        $this->cache->expects($this->exactly(30))
            ->method('delete')
            ->willReturn(true);

        // Logger is called 32 times: 30 invalidate() + 1 invalidateAll() + 1 clearCache()
        $this->logger->expects($this->exactly(32))
            ->method('info');

        // Act
        $this->service->clearCache($this->tenantId);
    }

    public function testBuildCacheKeyFormat(): void
    {
        // This tests the cache key format indirectly through invalidate
        $expectedKey = 'translations:51bd1332-307c-480c-bd3c-6bfe5ccd7829:fr:validators';

        $frenchLocale = LanguageCode::fromString('fr');
        $validatorsDomain = TranslationDomain::fromString('validators');

        $this->cache->expects($this->once())
            ->method('delete')
            ->with($expectedKey)
            ->willReturn(true);

        // Act
        $this->service->invalidate($this->tenantId, $frenchLocale, $validatorsDomain);
    }

    public function testCustomCacheTtl(): void
    {
        // Arrange - service with custom TTL
        $customService = new TranslationCacheService(
            cache: $this->cache,
            repository: $this->repository,
            logger: $this->logger,
            cacheTtl: 7200, // 2 hours
        );

        $entry = TranslationEntry::create(
            tenantId: $this->tenantId,
            locale: $this->locale,
            domain: $this->domain,
            key: TranslationKey::fromString('test'),
            value: TranslationValue::fromString('Test'),
        );

        $this->cache->expects($this->once())
            ->method('get')
            ->willReturnCallback(function ($key, $callback) {
                $item = $this->createMock(ItemInterface::class);
                $item->expects($this->once())
                    ->method('expiresAfter')
                    ->with(7200); // Verify custom TTL

                return $callback($item);
            });

        $this->repository->expects($this->once())
            ->method('findAll')
            ->willReturn([$entry]);

        // Act
        $customService->getTranslations($this->tenantId, $this->locale, $this->domain);
    }
}
