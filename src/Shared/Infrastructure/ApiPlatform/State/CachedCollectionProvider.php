<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Shared\Infrastructure\Cache\CacheService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
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

    public function __construct(
        private ProviderInterface $decorated,
        private CacheService $cacheService,
        private RequestStack $requestStack,
        private LoggerInterface $logger,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request || 'GET' !== $request->getMethod() || !$operation instanceof CollectionOperationInterface) {
            return $this->decorated->provide($operation, $uriVariables, $context);
        }

        if ('true' === $request->headers->get('X-No-Cache')) {
            $this->logger->debug('Cache bypassed by request header');

            return $this->decorated->provide($operation, $uriVariables, $context);
        }

        $ttl = $this->determineTTL($operation);
        if ($ttl <= 0) {
            return $this->decorated->provide($operation, $uriVariables, $context);
        }

        $tenantId = $this->resolveTenantId($request);
        $locale = $this->resolveLocale($request);
        $resource = $this->resolveResource($operation, $request);
        $cacheKey = $this->generateCacheKey($operation, $request, $tenantId, $locale, $resource, $uriVariables);
        $tags = $this->generateTags($tenantId, $resource);

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
                'operation' => $this->operationIdentifier($operation, $request),
                'resource' => $resource,
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

            return $this->decorated->provide($operation, $uriVariables, $context);
        }
    }

    /**
     * @param array<string, mixed> $uriVariables
     */
    private function generateCacheKey(
        Operation $operation,
        Request $request,
        string $tenantId,
        string $locale,
        string $resource,
        array $uriVariables,
    ): string {
        return $this->cacheService->tenantQueryKey(
            $tenantId,
            'api',
            $resource,
            [
                'operation' => $this->operationIdentifier($operation, $request),
                'locale' => $locale,
                'path' => $request->getPathInfo(),
                'query' => $request->query->all(),
                'uri_variables' => $uriVariables,
            ]
        );
    }

    private function determineTTL(Operation $operation): int
    {
        $shortName = (string) $operation->getShortName();

        return match (true) {
            str_contains($shortName, 'Product') => 300,      // 5 minutes
            str_contains($shortName, 'Category') => 300,     // 5 minutes
            str_contains($shortName, 'PriceList') => 300,    // 5 minutes
            str_contains($shortName, 'Warehouse') => 600,    // 10 minutes
            str_contains($shortName, 'Tenant') => 600,       // 10 minutes
            str_contains($shortName, 'Order') => 0,          // No caching (user-specific)
            str_contains($shortName, 'Payment') => 0,        // No caching (sensitive)
            str_contains($shortName, 'Notification') => 0,   // No caching (transient)
            str_contains($shortName, 'Invoice') => 0,        // No caching (sensitive)
            default => self::DEFAULT_TTL,
        };
    }

    /**
     * Generate cache tags for invalidation.
     *
     * @return array<string>
     */
    private function generateTags(string $tenantId, string $resource): array
    {
        return [
            $this->cacheService->tag('api'),
            ...$this->cacheService->tenantScopedTags($tenantId, $resource),
        ];
    }

    private function resolveTenantId(Request $request): string
    {
        $tenantId = $request->headers->get('X-Tenant-ID');

        return is_string($tenantId) && '' !== trim($tenantId) ? $tenantId : 'default';
    }

    private function resolveLocale(Request $request): string
    {
        $acceptLanguage = (string) $request->headers->get('Accept-Language', 'en');
        $locale = strtolower(trim(explode(',', $acceptLanguage)[0] ?? 'en'));
        $locale = trim(explode(';', $locale)[0] ?? $locale);
        $locale = trim(explode('-', $locale)[0] ?? $locale);

        return '' !== $locale ? $locale : 'en';
    }

    private function resolveResource(Operation $operation, Request $request): string
    {
        $shortName = (string) $operation->getShortName();
        $path = $request->getPathInfo();

        return match (true) {
            str_contains($shortName, 'Product') || str_contains($path, '/products') || str_contains($path, '/featured-products') => 'products',
            str_contains($shortName, 'Category') || str_contains($path, '/categories') || str_contains($path, '/home-categories') => 'categories',
            str_contains($shortName, 'PriceList') || str_contains($path, '/price-lists') || str_contains($path, '/price_lists') => 'price_lists',
            str_contains($shortName, 'Promotion') || str_contains($path, '/promotions') => 'promotions',
            str_contains($shortName, 'Warehouse') || str_contains($path, '/warehouses') => 'warehouses',
            str_contains($shortName, 'Tenant') || str_contains($path, '/tenants') => 'tenants',
            default => $this->normalizeResourceName('' !== $shortName ? $shortName : $path),
        };
    }

    private function operationIdentifier(Operation $operation, Request $request): string
    {
        $uriTemplate = $operation instanceof HttpOperation ? $operation->getUriTemplate() : null;

        return (string) ($operation->getName() ?? $uriTemplate ?? $request->getPathInfo());
    }

    private function normalizeResourceName(string $value): string
    {
        $normalized = preg_replace('/(?<!^)[A-Z]/', '_$0', trim($value, '/'));
        $normalized = strtolower((string) $normalized);

        return '' !== $normalized ? str_replace('-', '_', $normalized) : 'collections';
    }
}
