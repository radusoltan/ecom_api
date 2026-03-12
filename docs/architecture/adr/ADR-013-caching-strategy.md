# ADR-013: Multi-Layer Caching Strategy

**Date**: 2026-03-10
**Status**: Accepted
**Deciders**: [Radu — Architect]

## Context

Sprint 2 identified two performance gaps:

1. **Missing HTTP cache headers** on several endpoints — browsers and CDNs cannot cache responses.
2. **Invoice PDF endpoint approaching 200ms p95** — database-heavy query without application-level caching.

With TSK-21 completing seed data (1,000+ products, categories, price lists), the working set is now
large enough that uncached queries hit PostgreSQL on every request. Redis is available locally
(`redis://localhost:6379`) but not yet wired to Symfony's cache pools.

### Current State

| Component | Status |
|-----------|--------|
| `CacheService` (Shared) | Active — wraps `CacheInterface` with TTL, tags, tenant keys |
| `CachedCollectionProvider` | **Disabled** — awaiting Redis pool configuration |
| `ApiCacheSubscriber` | Active — HTTP headers on `/api/` GET routes |
| `StorefrontCacheSubscriber` | Active — `stale-while-revalidate` on storefront routes |
| `TranslationCacheService` | Active — 24h TTL, domain-keyed |
| `I18nCacheService` (Catalog) | Active — tag-aware product/category translations |
| Redis provider | Available — `REDIS_URL=redis://localhost:6379` |
| Cache pools (framework) | **Filesystem default** — Redis commented out in `cache.yaml` |
| Cache invalidation via events | **Not implemented** — events exist but don't trigger invalidation |

## Decision

Implement a **three-layer caching strategy**: HTTP response caching, Redis application caching, and
event-driven invalidation. Each layer serves a distinct purpose and all layers enforce tenant isolation.

---

## Layer 1: HTTP Cache Headers

### Strategy

All public GET endpoints return `Cache-Control`, `ETag`, and `Vary` headers. TTLs are tuned per
resource volatility. The existing `ApiCacheSubscriber` and `StorefrontCacheSubscriber` already cover
most cases — this decision formalizes the policy and fills gaps.

### TTL Matrix

| Resource | Audience | Cache-Control | max-age | stale-while-revalidate | Rationale |
|----------|----------|---------------|---------|------------------------|-----------|
| Categories | Public | `public` | 600s (10min) | 1200s (20min) | Tree structure rarely changes |
| Products (listing) | Public | `public` | 300s (5min) | 600s (10min) | Moderate update frequency |
| Products (detail) | Public | `public` | 300s (5min) | 600s (10min) | Same as listings |
| Translations | Public | `public` | 3600s (1h) | 7200s (2h) | Very stable once published |
| Price lists | Public | `public` | 300s (5min) | 600s (10min) | Promotions change periodically |
| Promotions | Public | `public` | 300s (5min) | 600s (10min) | Active set changes infrequently |
| Warehouses | Public | `public` | 600s (10min) | 1200s (20min) | Rarely modified |
| Tenants | Public | `public` | 600s (10min) | — | Admin-only changes |
| Search results | Public | `public` | 60s (1min) | 120s (2min) | Real-time relevance matters |
| Stock availability | Private | `private, no-store` | 0 | — | Real-time accuracy required |
| Orders | Private | `private, no-cache` | 0 | — | User-specific, sensitive |
| Payments | Private | `private, no-cache` | 0 | — | User-specific, sensitive |
| Customers | Private | `private, no-cache` | 0 | — | PII, per-user |
| Returns | Private | `private, no-cache` | 0 | — | User-specific, sensitive |
| Cart | Private | `private, no-cache` | 0 | — | Session-specific, volatile |
| Invoice PDF | Private | `private` | 3600s (1h) | — | Immutable once generated |
| Exports (CSV/XLSX) | Private | `no-cache, no-store` | 0 | — | One-time download |

### Required Headers on All Cacheable Responses

```
Cache-Control: public, max-age={ttl}, stale-while-revalidate={swr}
Vary: Accept, Accept-Language, X-Tenant-ID
ETag: "{md5(content)}"
```

### Key Design Points

1. **`Vary: X-Tenant-ID`** — Ensures CDNs and shared caches never serve Tenant A's data to Tenant B.
   This is the primary multi-tenant isolation mechanism at the HTTP layer.

2. **`stale-while-revalidate`** — Allows serving stale content while revalidating in the background.
   This eliminates cache stampedes on expiry and improves perceived latency.

3. **`ETag` for conditional requests** — Clients send `If-None-Match`, server responds `304 Not
   Modified` when content hasn't changed, saving bandwidth.

4. **`X-No-Cache: true` bypass** — Admin interfaces and internal tooling can skip caching per-request
   without disabling it globally.

5. **Search results get short TTL (60s)** — Balances freshness (new products appear within 1 minute)
   against load reduction (identical queries within the window are served from cache).

---

## Layer 2: Redis Application Cache

### Pool Configuration

Enable Redis as the cache backend with dedicated, tag-aware pools:

```yaml
# config/packages/cache.yaml
framework:
    cache:
        app: cache.adapter.redis_tag_aware
        default_redis_provider: '%env(REDIS_URL)%'

        pools:
            cache.api_collections:
                adapter: cache.adapter.redis_tag_aware
                default_lifetime: 300

            cache.translations:
                adapter: cache.adapter.redis_tag_aware
                default_lifetime: 86400

            cache.catalog:
                adapter: cache.adapter.redis_tag_aware
                default_lifetime: 300

            cache.pricing:
                adapter: cache.adapter.redis_tag_aware
                default_lifetime: 300
```

**Why `redis_tag_aware`**: Tag-based invalidation is essential for multi-tenant cache clearing. When
a product is updated, we invalidate by tag (`tenant:{id}:products`) rather than scanning all keys.

### Cache Key Pattern

All keys follow a hierarchical, tenant-scoped pattern:

```
{tenant_id}:{context}:{resource}:{query_hash}
```

Examples:
```
tenant:550e8400-...:catalog:products:a1b2c3d4        # Product listing with specific filters
tenant:550e8400-...:catalog:product:uuid-123          # Single product detail
tenant:550e8400-...:catalog:categories:tree           # Full category tree
tenant:550e8400-...:pricing:active_price_lists:all    # All active price lists
tenant:550e8400-...:i18n:messages:en_US               # Translation bundle
```

The `query_hash` is `md5()` of the normalized query string (sorted parameters), ensuring identical
queries hit the same cache entry regardless of parameter ordering.

### What to Cache

| Query / Resource | Pool | TTL | Tags | Justification |
|------------------|------|-----|------|---------------|
| Product listings (paginated) | `cache.api_collections` | 300s | `products`, `tenant:{id}:products` | High-volume, ~10 queries/s |
| Single product detail | `cache.catalog` | 300s | `product:{id}`, `tenant:{id}:products` | Frequent storefront access |
| Category tree | `cache.catalog` | 600s | `categories`, `tenant:{id}:categories` | Read-heavy, stable structure |
| Active price lists | `cache.pricing` | 300s | `price_lists`, `tenant:{id}:price_lists` | Used in every pricing calc |
| Promotion lookups | `cache.pricing` | 300s | `promotions`, `tenant:{id}:promotions` | Cart/checkout hot path |
| Translation bundles | `cache.translations` | 86400s | `i18n`, `tenant:{id}:i18n:{locale}` | Very stable, high hit rate |
| Product translations | `cache.catalog` | 3600s | `i18n:product:{id}`, `tenant:{id}:i18n` | Locale-specific content |
| Invoice line items | `cache.api_collections` | 3600s | `invoices`, `tenant:{id}:invoices` | Immutable after creation |
| Search autocomplete | `cache.catalog` | 60s | `search`, `tenant:{id}:search` | High-volume, short-lived |

### What NOT to Cache in Redis

- **Cart contents** — Session-bound, high write frequency, stale data causes UX issues.
- **Stock levels** — Real-time accuracy is a business requirement. Stale stock = overselling.
- **Order state** — Transitions must be immediately visible (payment, shipping).
- **Payment status** — Security-critical, must always reflect current state.
- **Customer PII** — Minimizes data exposure surface in a shared cache.

### Enable CachedCollectionProvider

Uncomment the decorator in `services.yaml` once Redis pools are configured:

```yaml
App\Shared\Infrastructure\ApiPlatform\State\CachedCollectionProvider:
    decorates: 'api_platform.state.provider.content_negotiation'
    arguments:
        $decorated: '@.inner'
```

This intercepts all API Platform collection GET operations and wraps them with the existing
`CacheService.get()` logic — tenant-aware keys, configurable TTL, tag-based invalidation.

---

## Layer 3: Event-Driven Cache Invalidation

### Strategy

Domain events trigger targeted cache invalidation. Rather than time-based expiry alone (which risks
serving stale data for up to TTL duration), write operations immediately invalidate affected cache
entries via tags.

### Invalidation Map

| Domain Event | Invalidated Tags | Scope |
|-------------|------------------|-------|
| `ProductCreated` | `tenant:{id}:products`, `categories` | Listings + category counts |
| `ProductUpdated` | `product:{productId}`, `tenant:{id}:products` | Detail + listings |
| `ProductDeactivated` | `product:{productId}`, `tenant:{id}:products` | Remove from listings |
| `ProductPublished` | `tenant:{id}:products` | Add to listings |
| `ProductTranslationsUpdated` | `i18n:product:{productId}`, `tenant:{id}:i18n` | Translated content |
| `CategoryCreated` | `tenant:{id}:categories` | Category tree |
| `CategoryUpdated` | `tenant:{id}:categories` | Category tree |
| `CategoryTranslationsUpdated` | `tenant:{id}:categories`, `tenant:{id}:i18n` | Translated tree |
| `PriceListActivated` | `tenant:{id}:price_lists` | Active pricing |
| `PriceListDeactivated` | `tenant:{id}:price_lists` | Active pricing |
| `PromotionCreated` | `tenant:{id}:promotions` | Promotion lookups |
| `PromotionUpdated` | `tenant:{id}:promotions` | Promotion lookups |
| `PromotionActivated` | `tenant:{id}:promotions`, `tenant:{id}:products` | Promo prices on products |
| `PromotionDeactivated` | `tenant:{id}:promotions`, `tenant:{id}:products` | Remove promo prices |
| `FlashSaleActivated` | `tenant:{id}:promotions`, `tenant:{id}:products` | Time-sensitive pricing |
| `FlashSaleEnded` | `tenant:{id}:promotions`, `tenant:{id}:products` | Restore normal pricing |
| `TranslationUpdated` | `tenant:{id}:i18n:{locale}` | Single locale bundle |
| `TranslationDeleted` | `tenant:{id}:i18n:{locale}` | Single locale bundle |
| `StockAdjusted` | — | No caching for stock (real-time) |
| `WarehouseUpdated` | `tenant:{id}:warehouses` | Warehouse metadata |
| `TenantDeactivated` | `tenant:{id}` | All tenant data |

### Implementation Pattern

A single `CacheInvalidationSubscriber` in `Shared/Infrastructure/EventSubscriber/` listens to domain
events via the Symfony message bus and invalidates corresponding tags:

```php
#[AsMessageHandler]
final readonly class CacheInvalidationSubscriber
{
    public function __construct(
        private TagAwareCacheInterface $cache,
        private LoggerInterface $logger,
    ) {}

    #[AsMessageHandler]
    public function onProductUpdated(ProductUpdated $event): void
    {
        $this->cache->invalidateTags([
            'product:' . $event->productId,
            'tenant:' . $event->tenantId . ':products',
        ]);
    }

    // ... handlers for each event in the invalidation map
}
```

### Invalidation Guarantees

- **Eventual consistency window**: Between event dispatch and subscriber execution (typically <100ms
  with synchronous bus, <1s with async RabbitMQ transport). Acceptable for all non-stock resources.
- **Async-safe**: If events are processed via RabbitMQ, invalidation is at-least-once. Duplicate
  invalidation is harmless (just causes a cache miss on next read).
- **Failure mode**: If Redis is down, `CacheService` already falls back to direct DB query. Missed
  invalidation is bounded by TTL (stale data is served for at most `max-age` seconds).

---

## Cache Warming

### Existing Infrastructure

`CacheWarmCommand` (`app:cache:warm`) already warms translations, products, and categories per tenant.
Extend it to also warm:

- Active price lists (per tenant)
- Promotion lookups (per tenant)
- Category tree (per tenant)

### Deployment Pipeline

```bash
# After deployment, warm critical caches:
php bin/console app:cache:warm              # All tenants
php bin/console cache:pool:clear cache.app  # Only if schema changed
```

### Scheduled Warming

Run `app:cache:warm` via cron every 2 hours to maintain >90% hit rate target, pre-populating entries
that expired naturally.

---

## Multi-Tenant Isolation (CRITICAL)

### HTTP Layer

`Vary: X-Tenant-ID` ensures that shared caches (CDNs, reverse proxies) maintain separate cache
entries per tenant. Without this header, a CDN could serve Tenant A's product catalog to Tenant B.

### Redis Layer

All cache keys are prefixed with `tenant:{tenant_id}:`. This provides:

1. **Namespace isolation** — Tenant A's keys can never collide with Tenant B's keys.
2. **Bulk invalidation** — `invalidateTags(['tenant:{id}'])` clears all data for a single tenant
   (e.g., on `TenantDeactivated`).
3. **Debugging** — Keys are human-readable and can be inspected via `redis-cli KEYS tenant:*`.

### Defense in Depth

Caching does NOT bypass PostgreSQL RLS. Even if a cache entry is somehow served to the wrong tenant
(bug, misconfiguration), the underlying queries still enforce RLS when the cache misses. The cache
layer is a performance optimization, not a security boundary — RLS remains the authoritative
access control.

---

## Trade-Offs

### Consistency vs. Performance

| Approach | Consistency | Latency | Complexity |
|----------|-------------|---------|------------|
| No caching (current for most queries) | Strong | High | Low |
| TTL-only caching | Eventual (bounded by TTL) | Low | Low |
| TTL + event invalidation (this ADR) | Near-real-time | Low | Medium |
| Write-through cache | Strong | Medium | High |

**Decision**: TTL + event invalidation. This gives near-real-time consistency for writes while
maintaining low latency for reads. The added complexity of event subscribers is manageable because
the domain event infrastructure already exists.

### Stale Data Risk Assessment

| Resource | Max staleness | Business impact | Acceptable? |
|----------|--------------|-----------------|-------------|
| Products | 5 min (with instant invalidation on update) | Outdated description shown briefly | Yes |
| Categories | 10 min | Wrong navigation briefly after restructure | Yes |
| Prices | 5 min (with instant invalidation) | Old price shown, but order uses live price | Yes — order validation catches mismatches |
| Stock | 0 (not cached) | N/A | N/A |
| Translations | 1 hour | Old label shown until cache refresh | Yes |
| Orders | 0 (not cached) | N/A | N/A |

Key insight: **Prices displayed in catalog may be stale, but the order placement flow always
recalculates from live data.** This means a customer might see an old price but will never be
charged an incorrect amount.

---

## Consequences

### Positive

- **API p95 latency drops below 200ms** — Redis lookups are <1ms vs. 50-150ms for PostgreSQL queries
- **Eliminates invoice endpoint bottleneck** — Immutable invoice data cached for 1 hour
- **Cache hit rate >90% achievable** — Warm start + high read-to-write ratio on catalog data
- **CDN-ready** — Correct `Vary`, `ETag`, and `Cache-Control` headers enable edge caching
- **Graceful degradation** — Redis failure falls back to direct DB queries, no outage

### Negative

- **Increased operational complexity** — Redis becomes a critical dependency for performance (but not
  correctness — system still works without it, just slower)
- **Cache debugging overhead** — Stale data issues require checking Redis state in addition to DB
- **Memory consumption** — Estimated ~50-100MB Redis for 1,000 products × 10 tenants × 5 locales
- **Event subscriber maintenance** — New domain events must be evaluated for cache invalidation impact

### Neutral

- `CachedCollectionProvider` decorator is already implemented, just needs activation
- Existing `CacheService` abstraction means bounded contexts don't depend on Redis directly
- Cache warming infrastructure (`app:cache:warm`) already exists and needs minor extension

## Implementation Plan

1. **Enable Redis pools** in `config/packages/cache.yaml`
2. **Activate `CachedCollectionProvider`** in `services.yaml`
3. **Implement `CacheInvalidationSubscriber`** for domain events → tag invalidation
4. **Add `stale-while-revalidate`** to `ApiCacheSubscriber` for applicable resources
5. **Add search result caching** (60s TTL) to search endpoints
6. **Extend `CacheWarmCommand`** with price lists and promotions
7. **Verify** — Load test to confirm p95 <200ms and hit rate >90%

## References

- PRD §9.1: Performance requirements (API <200ms p95)
- PRD §10.2: Cache hit rate >90% target
- `/var/www/ecom_api/src/Shared/Infrastructure/Cache/CacheService.php` — Core cache abstraction
- `/var/www/ecom_api/src/Shared/Infrastructure/ApiPlatform/State/CachedCollectionProvider.php` — API collection decorator
- `/var/www/ecom_api/src/Shared/Infrastructure/EventSubscriber/ApiCacheSubscriber.php` — HTTP cache headers
- `/var/www/ecom_api/src/Catalog/Infrastructure/EventSubscriber/StorefrontCacheSubscriber.php` — Storefront cache headers
- `/var/www/ecom_api/src/Internationalization/Infrastructure/Cache/TranslationCacheService.php` — Translation caching
- `/var/www/ecom_api/src/Shared/Infrastructure/Console/CacheWarmCommand.php` — Cache warming CLI
- `/var/www/ecom_api/config/packages/cache.yaml` — Framework cache configuration
- [Symfony Cache Component](https://symfony.com/doc/current/cache.html)
- [HTTP Caching — RFC 7234](https://httpwg.org/specs/rfc7234.html)
- [stale-while-revalidate — RFC 5861](https://httpwg.org/specs/rfc5861.html)
