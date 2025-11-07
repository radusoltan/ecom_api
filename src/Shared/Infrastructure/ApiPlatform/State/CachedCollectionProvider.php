<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Shared\Infrastructure\Cache\CacheService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Cached Collection Provider for API Platform.
 *
 * Wraps collection providers with Redis caching to improve
 * API response times for GET requests.
 *
 * Features:
 * - Automatic caching of GET requests
 * - Tenant-aware cache keys
 * - Configurable TTL
 * - Cache tags for invalidation
 * - Performance monitoring
 *
 * Business Rules (PRD Section 9.1):
 * - API response < 200ms target
 * - Cache hit rate > 90%
 * - Tenant isolation in cache
 * - 5-minute default TTL
 */
final readonly class CachedCollectionProvider implements ProviderInterface
{
    private const DEFAULT_TTL = 300; // 5 minutes
    private const MAX_TTL = 3600; // 1 hour

    public function __construct(
        private ProviderInterface $decorated,
        private CacheService $cacheService,
        private RequestStack $requestStack,
        private LoggerInterface $logger
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $request = $this->requestStack->getCurrentRequest();

        // Only cache GET requests
        if (null === $request || 'GET' !== $request->getMethod()) {
            return $this->decorated->provide($operation, $uriVariables, $context);
        }

        // Skip caching if explicitly disabled
        if ('true' === $request->headers->get('X-No-Cache')) {
            $this->logger->debug('Cache bypassed by request header');

            return $this->decorated->provide($operation, $uriVariables, $context);
        }

        // Generate cache key from operation + tenant + query params
        $tenantId = $request->headers->get('X-Tenant-ID', 'default');
        $locale = $request->getPreferredLanguage(['en', 'fr', 'de']) ?? 'en';

        $cacheKey = $this->generateCacheKey(
            $operation,
            $tenantId,
            $locale,
            $request->getQueryString() ?? ''
        );

        // Determine TTL based on operation
        $ttl = $this->determineTTL($operation);

        // Generate cache tags for invalidation
        $tags = $this->generateTags($operation, $tenantId);

        $startTime = microtime(true);

        try {
            $result = $this->cacheService->get(
                key: $cacheKey,
                callback: fn () => $this->decorated->provide($operation, $uriVariables, $context),
                ttl: $ttl,
                tags: $tags
            );

            $duration = (microtime(true) - $startTime) * 1000;

            $this->logger->debug('API collection cache lookup', [
                'operation' => $operation->getShortName(),
                'cache_key' => $cacheKey,
                'duration_ms' => round($duration, 2),
                'ttl' => $ttl,
            ]);

            return $result;
        } catch (\Throwable $e) {
            $this->logger->warning('API caching failed, falling back to direct call', [
                'operation' => $operation->getShortName(),
                'error' => $e->getMessage(),
            ]);

            // Fallback to direct provider call
            return $this->decorated->provide($operation, $uriVariables, $context);
        }
    }

    /**
     * Generate cache key from operation and request parameters.
     */
    private function generateCacheKey(
        Operation $operation,
        string $tenantId,
        string $locale,
        string $queryString
    ): string {
        $baseKey = sprintf(
            'api:%s:%s:%s',
            $operation->getShortName(),
            $locale,
            md5($queryString)
        );

        return $this->cacheService->tenantKey($tenantId, $baseKey);
    }

    /**
     * Determine TTL based on operation type.
     *
     * Different resources have different volatility:
     * - Products: 5 minutes (frequently updated)
     * - Categories: 10 minutes (rarely updated)
     * - Orders: No caching (user-specific)
     * - Default: 5 minutes
     */
    private function determineTTL(Operation $operation): int
    {
        $shortName = $operation->getShortName();

        return match (true) {
            str_contains($shortName, 'Product') => 300,      // 5 minutes
            str_contains($shortName, 'Category') => 600,     // 10 minutes
            str_contains($shortName, 'PriceList') => 300,    // 5 minutes
            str_contains($shortName, 'Warehouse') => 600,    // 10 minutes
            str_contains($shortName, 'Tenant') => 600,       // 10 minutes
            str_contains($shortName, 'Order') => 0,          // No caching (user-specific)
            str_contains($shortName, 'Payment') => 0,        // No caching (sensitive)
            default => self::DEFAULT_TTL,
        };
    }

    /**
     * Generate cache tags for invalidation.
     *
     * @return array<string>
     */
    private function generateTags(Operation $operation, string $tenantId): array
    {
        $shortName = $operation->getShortName();

        $tags = [
            'api',
            'tenant:'.$tenantId,
        ];

        // Add resource-specific tags
        if (str_contains($shortName, 'Product')) {
            $tags[] = 'products';
            $tags[] = 'tenant:'.$tenantId.':products';
        } elseif (str_contains($shortName, 'Category')) {
            $tags[] = 'categories';
            $tags[] = 'tenant:'.$tenantId.':categories';
        } elseif (str_contains($shortName, 'PriceList')) {
            $tags[] = 'price_lists';
            $tags[] = 'tenant:'.$tenantId.':price_lists';
        } elseif (str_contains($shortName, 'Warehouse')) {
            $tags[] = 'warehouses';
            $tags[] = 'tenant:'.$tenantId.':warehouses';
        } elseif (str_contains($shortName, 'Tenant')) {
            $tags[] = 'tenants';
        }

        return $tags;
    }
}
