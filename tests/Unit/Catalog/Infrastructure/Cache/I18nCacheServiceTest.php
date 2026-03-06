<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Infrastructure\Cache;

use App\Catalog\Domain\Model\CategoryId;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\ValueObject\Locale;
use App\Catalog\Infrastructure\Cache\I18nCacheService;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final class I18nCacheServiceTest extends TestCase
{
    private TagAwareCacheInterface $cache;
    private I18nCacheService $service;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(TagAwareCacheInterface::class);
        $this->service = new I18nCacheService($this->cache);
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
    }

    public function testGetProductTranslationsReturnsCachedData(): void
    {
        $productId = ProductId::fromString('00000000-0000-4000-8000-000000000002');
        $locale = Locale::fromString('en_US');
        $translations = ['name' => 'Product Name', 'description' => 'Desc'];

        $this->cache->method('get')->willReturn($translations);

        $result = $this->service->getProductTranslations($this->tenantId, $productId, $locale);

        self::assertSame($translations, $result);
    }

    public function testGetProductTranslationsReturnsNullOnException(): void
    {
        $productId = ProductId::fromString('00000000-0000-4000-8000-000000000002');
        $locale = Locale::fromString('en_US');

        $this->cache->method('get')->willThrowException(new \Exception('Cache error'));

        $result = $this->service->getProductTranslations($this->tenantId, $productId, $locale);

        self::assertNull($result);
    }

    public function testSetProductTranslationsStoresInCache(): void
    {
        $productId = ProductId::fromString('00000000-0000-4000-8000-000000000002');
        $locale = Locale::fromString('en_US');
        $translations = ['name' => 'Name', 'description' => 'Desc'];

        $expectedKey = 'i18n:00000000-0000-4000-8000-000000000001:product:00000000-0000-4000-8000-000000000002:en_US';

        $this->cache->expects(self::once())->method('get')
            ->with($expectedKey, self::anything(), INF)
            ->willReturnCallback(function ($key, $callback) use ($translations) {
                $item = $this->createMock(ItemInterface::class);
                $item->expects(self::once())->method('expiresAfter')->with(3600);
                $item->expects(self::once())->method('tag');

                $result = $callback($item);
                self::assertSame($translations, $result);

                return $result;
            });

        $this->service->setProductTranslations($this->tenantId, $productId, $locale, $translations);
    }

    public function testGetCategoryTranslationsReturnsCachedData(): void
    {
        $categoryId = CategoryId::fromString('00000000-0000-4000-8000-000000000003');
        $locale = Locale::fromString('fr');

        $translations = ['name' => 'Categorie'];
        $this->cache->method('get')->willReturn($translations);

        $result = $this->service->getCategoryTranslations($this->tenantId, $categoryId, $locale);

        self::assertSame($translations, $result);
    }

    public function testGetCategoryTranslationsReturnsNullOnException(): void
    {
        $categoryId = CategoryId::fromString('00000000-0000-4000-8000-000000000003');
        $locale = Locale::fromString('de');

        $this->cache->method('get')->willThrowException(new \Exception('err'));

        $result = $this->service->getCategoryTranslations($this->tenantId, $categoryId, $locale);

        self::assertNull($result);
    }

    public function testSetCategoryTranslations(): void
    {
        $categoryId = CategoryId::fromString('00000000-0000-4000-8000-000000000003');
        $locale = Locale::fromString('en_US');
        $translations = ['name' => 'Category Name'];

        $this->cache->expects(self::once())->method('get')
            ->with(
                'i18n:00000000-0000-4000-8000-000000000001:category:00000000-0000-4000-8000-000000000003:en_US',
                self::anything(),
                INF
            )
            ->willReturnCallback(function ($key, $callback) {
                $item = $this->createMock(ItemInterface::class);
                $item->method('expiresAfter');
                $item->method('tag');

                return $callback($item);
            });

        $this->service->setCategoryTranslations($this->tenantId, $categoryId, $locale, $translations);
    }

    public function testInvalidateProductWithSpecificLocale(): void
    {
        $productId = ProductId::fromString('00000000-0000-4000-8000-000000000002');
        $locale = Locale::fromString('en_US');

        $expectedKey = 'i18n:00000000-0000-4000-8000-000000000001:product:00000000-0000-4000-8000-000000000002:en_US';
        $this->cache->expects(self::once())->method('delete')->with($expectedKey);

        $this->service->invalidateProduct($this->tenantId, $productId, $locale);
    }

    public function testInvalidateProductAllLocales(): void
    {
        $productId = ProductId::fromString('00000000-0000-4000-8000-000000000002');

        $this->cache->expects(self::once())->method('invalidateTags')->with([
            'i18n:tenant:00000000-0000-4000-8000-000000000001',
            'i18n:product:00000000-0000-4000-8000-000000000002',
        ]);

        $this->service->invalidateProduct($this->tenantId, $productId);
    }

    public function testInvalidateCategoryWithSpecificLocale(): void
    {
        $categoryId = CategoryId::fromString('00000000-0000-4000-8000-000000000003');
        $locale = Locale::fromString('en_US');

        $expectedKey = 'i18n:00000000-0000-4000-8000-000000000001:category:00000000-0000-4000-8000-000000000003:en_US';
        $this->cache->expects(self::once())->method('delete')->with($expectedKey);

        $this->service->invalidateCategory($this->tenantId, $categoryId, $locale);
    }

    public function testInvalidateCategoryAllLocales(): void
    {
        $categoryId = CategoryId::fromString('00000000-0000-4000-8000-000000000003');

        $this->cache->expects(self::once())->method('invalidateTags')->with([
            'i18n:tenant:00000000-0000-4000-8000-000000000001',
            'i18n:category:00000000-0000-4000-8000-000000000003',
        ]);

        $this->service->invalidateCategory($this->tenantId, $categoryId);
    }

    public function testInvalidateTenant(): void
    {
        $this->cache->expects(self::once())->method('invalidateTags')->with([
            'i18n:tenant:00000000-0000-4000-8000-000000000001',
        ]);

        $this->service->invalidateTenant($this->tenantId);
    }

    public function testGetStatisticsReturnsDefaultValues(): void
    {
        $stats = $this->service->getStatistics();

        self::assertArrayHasKey('hit_ratio', $stats);
        self::assertArrayHasKey('miss_ratio', $stats);
        self::assertArrayHasKey('total_items', $stats);
        self::assertArrayHasKey('memory_usage', $stats);
        self::assertSame(0.0, $stats['hit_ratio']);
    }

    public function testWarmProductsCacheAcceptsEmptyArrays(): void
    {
        // Just ensure no exceptions
        $this->service->warmProductsCache($this->tenantId, [], []);

        self::assertTrue(true);
    }

    public function testWarmCategoriesCacheAcceptsEmptyArrays(): void
    {
        $this->service->warmCategoriesCache($this->tenantId, [], []);

        self::assertTrue(true);
    }
}
