<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Cache;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * Centralized cache service with Redis backend.
 *
 * Features:
 * - TTL-based caching
 * - Namespace support for multi-tenancy
 * - Automatic invalidation
 * - Cache warming support
 * - Performance monitoring
 *
 * Business Rules (PRD Section 10.2):
 * - Cache hit rate > 90% target
 * - TTL based on data volatility
 * - Automatic cache invalidation on updates
 * - Support for cache tags
 */
final readonly class CacheService
{
    public function __construct(
        private CacheInterface $cache,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Get cached value or compute and store it.
     *
     * @template T
     *
     * @param string        $key      Cache key
     * @param callable(): T $callback Function to compute value if not cached
     * @param int           $ttl      Time to live in seconds
     * @param array<string> $tags     Tags for cache invalidation
     *
     * @return T
     */
    public function get(string $key, callable $callback, int $ttl = 3600, array $tags = []): mixed
    {
        $startTime = microtime(true);

        try {
            $value = $this->cache->get($key, function (ItemInterface $item) use ($callback, $ttl, $tags) {
                $item->expiresAfter($ttl);

                if (!empty($tags)) {
                    $item->tag($tags);
                }

                $this->logger->info('Cache miss', [
                    'key' => $item->getKey(),
                    'ttl' => $ttl,
                    'tags' => $tags,
                ]);

                return $callback();
            });

            $duration = (microtime(true) - $startTime) * 1000;
            $this->logger->debug('Cache hit', [
                'key' => $key,
                'duration_ms' => round($duration, 2),
            ]);

            return $value;
        } catch (\Throwable $e) {
            $this->logger->error('Cache operation failed', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            // Fallback to computing value directly
            return $callback();
        }
    }

    /**
     * Invalidate cache by key.
     */
    public function invalidate(string $key): bool
    {
        try {
            $result = $this->cache->delete($key);

            $this->logger->info('Cache invalidated', [
                'key' => $key,
                'success' => $result,
            ]);

            return $result;
        } catch (\Throwable $e) {
            $this->logger->error('Cache invalidation failed', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Invalidate multiple cache entries by keys.
     *
     * @param array<string> $keys
     */
    public function invalidateMultiple(array $keys): void
    {
        foreach ($keys as $key) {
            $this->invalidate($key);
        }
    }

    /**
     * Invalidate cache entries by tags.
     *
     * @param array<string> $tags
     */
    public function invalidateTags(array $tags): bool
    {
        if (!$this->cache instanceof TagAwareCacheInterface) {
            $this->logger->warning('Cache backend does not support tag invalidation', [
                'tags' => $tags,
            ]);

            return false;
        }

        try {
            $result = $this->cache->invalidateTags($tags);

            $this->logger->info('Cache tags invalidated', [
                'tags' => $tags,
                'success' => $result,
            ]);

            return $result;
        } catch (\Throwable $e) {
            $this->logger->error('Cache tag invalidation failed', [
                'tags' => $tags,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Generate tenant-specific cache key.
     */
    public function tenantKey(string $tenantId, string $key): string
    {
        return sprintf('tenant:%s:%s', $tenantId, $key);
    }

    /**
     * Generate locale-specific cache key.
     */
    public function localeKey(string $locale, string $key): string
    {
        return sprintf('locale:%s:%s', $locale, $key);
    }

    /**
     * Generate compound key for tenant + locale.
     */
    public function tenantLocaleKey(string $tenantId, string $locale, string $key): string
    {
        return sprintf('tenant:%s:locale:%s:%s', $tenantId, $locale, $key);
    }

    public function tenantTag(string $tenantId): string
    {
        return $this->tag('tenant', $tenantId);
    }

    public function tenantResourceTag(string $tenantId, string $resource): string
    {
        return $this->tag('tenant', $tenantId, $resource);
    }

    /**
     * @return array<string>
     */
    public function tenantScopedTags(string $tenantId, string $resource, ?string $itemTag = null): array
    {
        $tags = [
            $this->tenantTag($tenantId),
            $this->tag($resource),
            $this->tenantResourceTag($tenantId, $resource),
        ];

        if (null !== $itemTag) {
            $tags[] = $this->tag($itemTag);
        }

        return array_values(array_unique($tags));
    }

    public function tag(string ...$segments): string
    {
        $normalized = array_values(array_filter(
            array_map(fn (string $segment): string => $this->normalizeTagSegment($segment), $segments),
            static fn (string $segment): bool => '' !== $segment
        ));

        return implode('.', $normalized);
    }

    /**
     * Build an ADR-compliant tenant-scoped query cache key.
     *
     * @param array<string, mixed>|string $query
     */
    public function tenantQueryKey(string $tenantId, string $context, string $resource, array|string $query): string
    {
        return sprintf(
            'tenant:%s:%s:%s:%s',
            $tenantId,
            $context,
            $resource,
            $this->queryHash($query)
        );
    }

    /**
     * @param array<string, mixed>|string $query
     */
    public function queryHash(array|string $query): string
    {
        if (is_string($query)) {
            return md5($query);
        }

        /** @var array<string, mixed> $normalized */
        $normalized = $this->normalizeQuery($query);

        return md5((string) json_encode($normalized, JSON_THROW_ON_ERROR));
    }

    /**
     * Warm cache with predefined data.
     *
     * @param array<string, mixed> $data Key => value pairs
     * @param int                  $ttl  Time to live
     */
    public function warmMultiple(array $data, int $ttl = 3600): void
    {
        $startTime = microtime(true);
        $count = 0;

        foreach ($data as $key => $value) {
            try {
                $this->cache->get($key, function (ItemInterface $item) use ($value, $ttl) {
                    $item->expiresAfter($ttl);

                    return $value;
                });
                ++$count;
            } catch (\Throwable $e) {
                $this->logger->warning('Cache warming failed for key', [
                    'key' => $key,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $duration = (microtime(true) - $startTime) * 1000;
        $this->logger->info('Cache warmed', [
            'count' => $count,
            'total' => count($data),
            'duration_ms' => round($duration, 2),
        ]);
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array<string, mixed>
     */
    private function normalizeQuery(array $query): array
    {
        ksort($query);

        foreach ($query as $key => $value) {
            if (is_array($value)) {
                $query[$key] = $this->normalizeQuery($value);

                continue;
            }

            if ($value instanceof \DateTimeInterface) {
                $query[$key] = $value->format(\DateTimeInterface::ATOM);

                continue;
            }

            if ($value instanceof \Stringable) {
                $query[$key] = (string) $value;
            }
        }

        return $query;
    }

    private function normalizeTagSegment(string $segment): string
    {
        $normalized = preg_replace('/[{}()\/\\\\@:]+/', '.', $segment);

        return trim((string) $normalized, '.');
    }
}
