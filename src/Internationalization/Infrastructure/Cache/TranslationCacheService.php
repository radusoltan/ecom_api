<?php

declare(strict_types=1);

namespace App\Internationalization\Infrastructure\Cache;

use App\Internationalization\Domain\Model\TranslationDomain;
use App\Internationalization\Domain\Repository\TranslationEntryRepositoryInterface;
use App\Shared\Domain\ValueObject\LanguageCode;
use App\Shared\Domain\ValueObject\TenantId;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Translation Cache Service
 *
 * Manages Redis caching for translations with:
 * - Cache key pattern: translations:{tenantId}:{locale}:{domain}
 * - TTL: 24 hours (configurable)
 * - Cache warming on deployment
 * - Cache invalidation on CRUD operations
 * - Fallback to database on cache miss
 *
 * Business Rules:
 * - Cache hit rate target: >90%
 * - Response time target: <10ms for cached translations
 * - Bulk invalidation for import operations
 */
final readonly class TranslationCacheService
{
    /**
     * Cache key prefix
     */
    private const CACHE_PREFIX = 'translations';

    /**
     * Default TTL: 24 hours
     */
    private const DEFAULT_TTL = 86400;

    public function __construct(
        private CacheInterface $cache,
        private TranslationEntryRepositoryInterface $repository,
        private LoggerInterface $logger,
        private int $cacheTtl = self::DEFAULT_TTL,
    ) {}

    /**
     * Get translations for a locale and domain (cached)
     *
     * @return array<string, string> Key-value pairs of translations
     */
    public function getTranslations(
        TenantId $tenantId,
        LanguageCode $locale,
        TranslationDomain $domain,
    ): array {
        $cacheKey = $this->buildCacheKey($tenantId, $locale, $domain);

        try {
            return $this->cache->get($cacheKey, function (ItemInterface $item) use ($tenantId, $locale, $domain) {
                $item->expiresAfter($this->cacheTtl);

                $this->logger->info('Translation cache miss', [
                    'tenant' => $tenantId->toString(),
                    'locale' => $locale->value(),
                    'domain' => $domain->value(),
                ]);

                return $this->loadFromDatabase($tenantId, $locale, $domain);
            });
        } catch (\Exception $e) {
            $this->logger->error('Translation cache error, falling back to database', [
                'error' => $e->getMessage(),
                'tenant' => $tenantId->toString(),
                'locale' => $locale->value(),
                'domain' => $domain->value(),
            ]);

            return $this->loadFromDatabase($tenantId, $locale, $domain);
        }
    }

    /**
     * Invalidate cache for a specific locale and domain
     */
    public function invalidate(
        TenantId $tenantId,
        LanguageCode $locale,
        TranslationDomain $domain,
    ): void {
        $cacheKey = $this->buildCacheKey($tenantId, $locale, $domain);

        try {
            $this->cache->delete($cacheKey);

            $this->logger->info('Translation cache invalidated', [
                'tenant' => $tenantId->toString(),
                'locale' => $locale->value(),
                'domain' => $domain->value(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to invalidate translation cache', [
                'error' => $e->getMessage(),
                'tenant' => $tenantId->toString(),
                'locale' => $locale->value(),
                'domain' => $domain->value(),
            ]);
        }
    }

    /**
     * Bulk invalidate cache for all locales and domains
     * Used after bulk import operations
     */
    public function invalidateAll(TenantId $tenantId): void
    {
        $locales = ['en', 'fr', 'de'];
        $domains = ['messages', 'validators', 'emails', 'auth', 'admin', 'catalog', 'inventory', 'order', 'customer', 'pricing'];

        foreach ($locales as $localeStr) {
            foreach ($domains as $domainStr) {
                try {
                    $locale = LanguageCode::fromString($localeStr);
                    $domain = TranslationDomain::fromString($domainStr);
                    $this->invalidate($tenantId, $locale, $domain);
                } catch (\Exception $e) {
                    $this->logger->warning('Failed to invalidate cache entry', [
                        'error' => $e->getMessage(),
                        'locale' => $localeStr,
                        'domain' => $domainStr,
                    ]);
                }
            }
        }

        $this->logger->info('Bulk translation cache invalidation completed', [
            'tenant' => $tenantId->toString(),
        ]);
    }

    /**
     * Warm cache for all locales and domains
     * Should be called on deployment or cache clear
     */
    public function warmCache(TenantId $tenantId): int
    {
        $locales = ['en', 'fr', 'de'];
        $domains = ['messages', 'validators', 'emails', 'auth', 'admin', 'catalog', 'inventory', 'order', 'customer', 'pricing'];
        $warmedCount = 0;

        foreach ($locales as $localeStr) {
            foreach ($domains as $domainStr) {
                try {
                    $locale = LanguageCode::fromString($localeStr);
                    $domain = TranslationDomain::fromString($domainStr);

                    // Load and cache translations
                    $translations = $this->getTranslations($tenantId, $locale, $domain);

                    if (count($translations) > 0) {
                        $warmedCount++;
                    }

                    $this->logger->debug('Cache warmed', [
                        'locale' => $localeStr,
                        'domain' => $domainStr,
                        'count' => count($translations),
                    ]);
                } catch (\Exception $e) {
                    $this->logger->warning('Failed to warm cache entry', [
                        'error' => $e->getMessage(),
                        'locale' => $localeStr,
                        'domain' => $domainStr,
                    ]);
                }
            }
        }

        $this->logger->info('Translation cache warming completed', [
            'tenant' => $tenantId->toString(),
            'warmed' => $warmedCount,
        ]);

        return $warmedCount;
    }

    /**
     * Clear all translation caches for a tenant
     */
    public function clearCache(TenantId $tenantId): void
    {
        $this->invalidateAll($tenantId);

        $this->logger->info('Translation cache cleared', [
            'tenant' => $tenantId->toString(),
        ]);
    }

    /**
     * Build cache key
     */
    private function buildCacheKey(
        TenantId $tenantId,
        LanguageCode $locale,
        TranslationDomain $domain,
    ): string {
        return sprintf(
            '%s:%s:%s:%s',
            self::CACHE_PREFIX,
            $tenantId->toString(),
            $locale->value(),
            $domain->value()
        );
    }

    /**
     * Load translations from database
     *
     * @return array<string, string> Key-value pairs
     */
    private function loadFromDatabase(
        TenantId $tenantId,
        LanguageCode $locale,
        TranslationDomain $domain,
    ): array {
        $entries = $this->repository->findAll(
            tenantId: $tenantId,
            filters: [
                'locale' => $locale->value(),
                'domain' => $domain->value(),
            ],
            page: 1,
            perPage: 10000, // Large number to get all
        );

        $translations = [];
        foreach ($entries as $entry) {
            $translations[$entry->key->value()] = $entry->value->value();
        }

        return $translations;
    }

    /**
     * Get cache statistics
     *
     * @return array{hits: int, misses: int, hitRate: float}
     */
    public function getStatistics(): array
    {
        // This would require tracking hits/misses in Redis
        // For now, return placeholder
        return [
            'hits' => 0,
            'misses' => 0,
            'hitRate' => 0.0,
        ];
    }
}
