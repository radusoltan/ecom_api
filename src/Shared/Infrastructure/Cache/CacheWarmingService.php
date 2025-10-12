<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Cache;

use App\Internationalization\Domain\Model\Locale;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

/**
 * Service for warming cache with frequently accessed data.
 *
 * Features:
 * - Pre-load translations
 * - Pre-load active products
 * - Pre-load categories
 * - Pre-load tenant configurations
 * - Scheduled warming via cron
 *
 * Business Rules (PRD Section 10.2):
 * - Warm cache during off-peak hours
 * - Prioritize most accessed data
 * - Monitor warming performance
 * - Support incremental warming
 */
final readonly class CacheWarmingService
{
    private const DEFAULT_WARMING_TTL = 7200; // 2 hours

    public function __construct(
        private CacheService $cacheService,
        private Connection $connection,
        private LoggerInterface $logger
    ) {}

    /**
     * Warm cache for a specific tenant.
     */
    public function warmTenant(TenantId $tenantId, ?Locale $locale = null): array
    {
        $startTime = microtime(true);
        $stats = [
            'translations' => 0,
            'products' => 0,
            'categories' => 0,
        ];

        try {
            // Warm translations
            if ($locale !== null) {
                $stats['translations'] = $this->warmTranslations($tenantId, $locale);
            }

            // Warm active products
            $stats['products'] = $this->warmActiveProducts($tenantId, $locale);

            // Warm categories
            $stats['categories'] = $this->warmCategories($tenantId, $locale);

            $duration = (microtime(true) - $startTime) * 1000;

            $this->logger->info('Cache warming completed for tenant', [
                'tenant_id' => $tenantId->toString(),
                'locale' => $locale?->toString(),
                'stats' => $stats,
                'duration_ms' => round($duration, 2),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Cache warming failed for tenant', [
                'tenant_id' => $tenantId->toString(),
                'error' => $e->getMessage(),
            ]);
        }

        return $stats;
    }

    /**
     * Warm all tenants (for scheduled job).
     */
    public function warmAllTenants(): array
    {
        $startTime = microtime(true);
        $tenants = $this->getActiveTenants();
        $totalStats = [];

        foreach ($tenants as $tenantData) {
            $tenantId = TenantId::fromString($tenantData['id']);

            // Warm for default locale
            $locale = Locale::fromString('en_US'); // Default
            $stats = $this->warmTenant($tenantId, $locale);

            $totalStats[$tenantData['id']] = $stats;
        }

        $duration = (microtime(true) - $startTime) * 1000;

        $this->logger->info('Cache warming completed for all tenants', [
            'tenant_count' => count($tenants),
            'duration_ms' => round($duration, 2),
            'stats' => $totalStats,
        ]);

        return $totalStats;
    }

    /**
     * Warm translations for tenant and locale.
     */
    private function warmTranslations(TenantId $tenantId, Locale $locale): int
    {
        $query = <<<SQL
            SELECT domain, key, value
            FROM translations
            WHERE tenant_id = :tenant_id
            AND locale = :locale
            LIMIT 1000
        SQL;

        $translations = $this->connection->fetchAllAssociative($query, [
            'tenant_id' => $tenantId->toString(),
            'locale' => $locale->toString(),
        ]);

        $cacheData = [];
        foreach ($translations as $translation) {
            $key = $this->cacheService->tenantLocaleKey(
                $tenantId->toString(),
                $locale->toString(),
                sprintf('translation:%s:%s', $translation['domain'], $translation['key'])
            );
            $cacheData[$key] = $translation['value'];
        }

        if (!empty($cacheData)) {
            $this->cacheService->warmMultiple($cacheData, self::DEFAULT_WARMING_TTL);
        }

        return count($cacheData);
    }

    /**
     * Warm active products for tenant.
     */
    private function warmActiveProducts(TenantId $tenantId, ?Locale $locale): int
    {
        $query = <<<SQL
            SELECT id, sku, name_translations, price_amount, price_currency, status
            FROM products
            WHERE tenant_id = :tenant_id
            AND status = 'active'
            ORDER BY created_at DESC
            LIMIT 100
        SQL;

        $products = $this->connection->fetchAllAssociative($query, [
            'tenant_id' => $tenantId->toString(),
        ]);

        $cacheData = [];
        foreach ($products as $product) {
            // Cache by ID
            $key = $this->cacheService->tenantKey(
                $tenantId->toString(),
                sprintf('product:%s', $product['id'])
            );
            $cacheData[$key] = $product;

            // Cache by SKU
            $skuKey = $this->cacheService->tenantKey(
                $tenantId->toString(),
                sprintf('product:sku:%s', $product['sku'])
            );
            $cacheData[$skuKey] = $product;
        }

        if (!empty($cacheData)) {
            $this->cacheService->warmMultiple($cacheData, self::DEFAULT_WARMING_TTL);
        }

        return count($products);
    }

    /**
     * Warm categories for tenant.
     */
    private function warmCategories(TenantId $tenantId, ?Locale $locale): int
    {
        $query = <<<SQL
            SELECT id, slug, name_translations, parent_id, position
            FROM categories
            WHERE tenant_id = :tenant_id
            AND is_active = true
            ORDER BY position ASC
            LIMIT 50
        SQL;

        $categories = $this->connection->fetchAllAssociative($query, [
            'tenant_id' => $tenantId->toString(),
        ]);

        $cacheData = [];
        foreach ($categories as $category) {
            $key = $this->cacheService->tenantKey(
                $tenantId->toString(),
                sprintf('category:%s', $category['id'])
            );
            $cacheData[$key] = $category;
        }

        if (!empty($cacheData)) {
            $this->cacheService->warmMultiple($cacheData, self::DEFAULT_WARMING_TTL);
        }

        return count($categories);
    }

    /**
     * Get active tenants from database.
     *
     * @return array<array{id: string, name: string}>
     */
    private function getActiveTenants(): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT id, name FROM tenants WHERE status = :status LIMIT 100',
            ['status' => 'active']
        );
    }
}
