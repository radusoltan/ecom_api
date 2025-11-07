<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Cache;

use App\Catalog\Domain\Model\CategoryId;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\ValueObject\Locale;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * Service for caching translated content
 * Key format: i18n:{tenant}:{entity}:{id}:{locale}.
 */
final class I18nCacheService
{
    private const CACHE_TTL = 3600; // 1 hour
    private const KEY_PREFIX = 'i18n';

    public function __construct(
        private readonly TagAwareCacheInterface $cache
    ) {
    }

    /**
     * Get cached product translations.
     */
    public function getProductTranslations(
        TenantId $tenantId,
        ProductId $productId,
        Locale $locale
    ): ?array {
        $key = $this->buildProductKey($tenantId, $productId, $locale);

        try {
            return $this->cache->get($key, function (ItemInterface $item) {
                // If not in cache, return null (will be populated by caller)
                $item->expiresAfter(self::CACHE_TTL);
                $item->tag($this->getProductTags($tenantId, $productId));

                return null;
            });
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Cache product translations.
     */
    public function setProductTranslations(
        TenantId $tenantId,
        ProductId $productId,
        Locale $locale,
        array $translations
    ): void {
        $key = $this->buildProductKey($tenantId, $productId, $locale);

        $this->cache->get($key, function (ItemInterface $item) use ($translations, $tenantId, $productId) {
            $item->expiresAfter(self::CACHE_TTL);
            $item->tag($this->getProductTags($tenantId, $productId));

            return $translations;
        }, INF); // Force save
    }

    /**
     * Get cached category translations.
     */
    public function getCategoryTranslations(
        TenantId $tenantId,
        CategoryId $categoryId,
        Locale $locale
    ): ?array {
        $key = $this->buildCategoryKey($tenantId, $categoryId, $locale);

        try {
            return $this->cache->get($key, function (ItemInterface $item) {
                // If not in cache, return null
                $item->expiresAfter(self::CACHE_TTL);
                $item->tag($this->getCategoryTags($tenantId, $categoryId));

                return null;
            });
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Cache category translations.
     */
    public function setCategoryTranslations(
        TenantId $tenantId,
        CategoryId $categoryId,
        Locale $locale,
        array $translations
    ): void {
        $key = $this->buildCategoryKey($tenantId, $categoryId, $locale);

        $this->cache->get($key, function (ItemInterface $item) use ($translations, $tenantId, $categoryId) {
            $item->expiresAfter(self::CACHE_TTL);
            $item->tag($this->getCategoryTags($tenantId, $categoryId));

            return $translations;
        }, INF); // Force save
    }

    /**
     * Invalidate all cached translations for a product.
     */
    public function invalidateProduct(
        TenantId $tenantId,
        ProductId $productId,
        ?Locale $locale = null
    ): void {
        if (null !== $locale) {
            // Invalidate specific locale
            $key = $this->buildProductKey($tenantId, $productId, $locale);
            $this->cache->delete($key);
        } else {
            // Invalidate all locales for this product
            $tags = $this->getProductTags($tenantId, $productId);
            $this->cache->invalidateTags($tags);
        }
    }

    /**
     * Invalidate all cached translations for a category.
     */
    public function invalidateCategory(
        TenantId $tenantId,
        CategoryId $categoryId,
        ?Locale $locale = null
    ): void {
        if (null !== $locale) {
            // Invalidate specific locale
            $key = $this->buildCategoryKey($tenantId, $categoryId, $locale);
            $this->cache->delete($key);
        } else {
            // Invalidate all locales for this category
            $tags = $this->getCategoryTags($tenantId, $categoryId);
            $this->cache->invalidateTags($tags);
        }
    }

    /**
     * Invalidate all cached translations for a tenant.
     */
    public function invalidateTenant(TenantId $tenantId): void
    {
        $tag = sprintf('%s:tenant:%s', self::KEY_PREFIX, $tenantId->toString());
        $this->cache->invalidateTags([$tag]);
    }

    /**
     * Warm cache for multiple products.
     */
    public function warmProductsCache(
        TenantId $tenantId,
        array $products,
        array $locales
    ): void {
        foreach ($products as $product) {
            foreach ($locales as $locale) {
                // This would call the domain service to get translations
                // and cache them
            }
        }
    }

    /**
     * Warm cache for multiple categories.
     */
    public function warmCategoriesCache(
        TenantId $tenantId,
        array $categories,
        array $locales
    ): void {
        foreach ($categories as $category) {
            foreach ($locales as $locale) {
                // This would call the domain service to get translations
                // and cache them
            }
        }
    }

    private function buildProductKey(TenantId $tenantId, ProductId $productId, Locale $locale): string
    {
        return sprintf(
            '%s:%s:product:%s:%s',
            self::KEY_PREFIX,
            $tenantId->toString(),
            $productId->toString(),
            $locale->toString()
        );
    }

    private function buildCategoryKey(TenantId $tenantId, CategoryId $categoryId, Locale $locale): string
    {
        return sprintf(
            '%s:%s:category:%s:%s',
            self::KEY_PREFIX,
            $tenantId->toString(),
            $categoryId->toString(),
            $locale->toString()
        );
    }

    private function getProductTags(TenantId $tenantId, ProductId $productId): array
    {
        return [
            sprintf('%s:tenant:%s', self::KEY_PREFIX, $tenantId->toString()),
            sprintf('%s:product:%s', self::KEY_PREFIX, $productId->toString()),
        ];
    }

    private function getCategoryTags(TenantId $tenantId, CategoryId $categoryId): array
    {
        return [
            sprintf('%s:tenant:%s', self::KEY_PREFIX, $tenantId->toString()),
            sprintf('%s:category:%s', self::KEY_PREFIX, $categoryId->toString()),
        ];
    }

    /**
     * Get cache statistics for monitoring.
     */
    /** @return array<string, mixed> */
    public function getStatistics(): array
    {
        // This would integrate with cache adapter to get hit/miss ratios
        return [
            'hit_ratio' => 0.0,
            'miss_ratio' => 0.0,
            'total_items' => 0,
            'memory_usage' => 0,
        ];
    }
}
