# Cache Tenant Isolation Review — TSK-33

**Date:** 2026-03-10
**Reviewer:** Claude Opus 4.6 (Security Cross-Audit)
**Severity:** P0 (cross-tenant data exposure)
**Status:** Review complete — implementation spec for Codex CLI

---

## Executive Summary

The Sprint 3 audit identified cross-tenant cache leakage in pricing and inventory cache invalidation. This deep review confirms **all reported issues** and identifies **additional gaps** not in the original findings:

1. **5 cache invalidation calls use bare (unscoped) tags** — invalidating ALL tenants' data instead of just one tenant
2. **4 domain events lack `tenantId`** — making tenant-scoped cache invalidation impossible without a repository lookup
3. **3 warehouse events lack `tenantId`** — same root cause as above
4. **6 stock events have ZERO cache invalidation handlers** — stale stock data served to customers
5. **HTTP vs backend TTL mismatch** on categories (600s HTTP vs 300s backend) causes cache incoherence
6. **`PriceListActivated`/`PriceListDeactivated` events lack `tenantId`** — root cause of the pricing leakage

---

## Finding 1: PricingCacheInvalidationSubscriber — Cross-Tenant Leakage

**File:** `src/Pricing/Infrastructure/EventSubscriber/PricingCacheInvalidationSubscriber.php`
**Severity:** P0

### Problem

Lines 53 and 58 use a bare `['price_lists']` tag without tenant scoping:

```php
// Line 51-54 — BUG
public function onPriceListActivated(PriceListActivated $event): void
{
    $this->invalidateTags(['price_lists'], PriceListActivated::class);
}

// Line 56-59 — BUG
public function onPriceListDeactivated(PriceListDeactivated $event): void
{
    $this->invalidateTags(['price_lists'], PriceListDeactivated::class);
}
```

Compare with the **correct** pattern on line 43-49:
```php
public function onPriceListCreated(PriceListCreated $event): void
{
    $this->invalidateTags(
        $this->tenantResourceTags($event->tenantId(), 'price_lists'),
        PriceListCreated::class
    );
}
```

### Root Cause

`PriceListActivated` and `PriceListDeactivated` events **do not carry `tenantId`**:

```php
// src/Pricing/Domain/Event/PriceListActivated.php
final readonly class PriceListActivated implements DomainEvent
{
    public function __construct(
        private string $priceListId,           // ← only ID
        private \DateTimeImmutable $occurredOn,
    ) {}
}
```

Whereas `PriceListCreated` **does** carry `tenantId`:
```php
final readonly class PriceListCreated implements DomainEvent
{
    public function __construct(
        private string $priceListId,
        private string $tenantId,  // ← present
        private string $name,
        private int $priority,
        private \DateTimeImmutable $occurredOn,
    ) {}
}
```

### Impact

When any tenant activates/deactivates a price list, the bare `price_lists` tag matches **every** cache entry tagged with `price_lists` across **all tenants**. This causes:
- **Cache thundering herd** — all tenants' price list caches evicted simultaneously
- **Performance degradation** — mass cache misses trigger N×tenants database queries
- Not a direct data leak, but causes unnecessary cross-tenant cache invalidation (denial of service pattern)

### Fix Specification

**Step 1:** Add `tenantId` to both events:

```php
// src/Pricing/Domain/Event/PriceListActivated.php
final readonly class PriceListActivated implements DomainEvent
{
    public function __construct(
        private string $priceListId,
        private string $tenantId,              // ADD THIS
        private \DateTimeImmutable $occurredOn,
    ) {}

    public function tenantId(): string         // ADD THIS
    {
        return $this->tenantId;
    }
    // ... update toArray() too
}
```

Same change for `PriceListDeactivated`.

**Step 2:** Find where these events are dispatched (in the PriceList aggregate or command handler) and pass `$this->tenantId` (or `$priceList->tenantId()`) as the second constructor argument.

**Step 3:** Update the subscriber:

```php
public function onPriceListActivated(PriceListActivated $event): void
{
    $this->invalidateTags(
        $this->tenantResourceTags($event->tenantId(), 'price_lists'),
        PriceListActivated::class
    );
}

public function onPriceListDeactivated(PriceListDeactivated $event): void
{
    $this->invalidateTags(
        $this->tenantResourceTags($event->tenantId(), 'price_lists'),
        PriceListDeactivated::class
    );
}
```

---

## Finding 2: InventoryCacheInvalidationSubscriber — Cross-Tenant Leakage

**File:** `src/Inventory/Infrastructure/EventSubscriber/InventoryCacheInvalidationSubscriber.php`
**Severity:** P0

### Problem

Lines 35, 41, 47 use bare `['warehouses']` tag:

```php
// Line 32-36 — BUG
#[AsMessageHandler(bus: 'event.bus')]
public function onWarehouseUpdated(WarehouseUpdated $event): void
{
    $this->invalidateTags(['warehouses'], WarehouseUpdated::class);
}

// Line 38-42 — BUG
public function onWarehouseActivated(WarehouseActivated $event): void
{
    $this->invalidateTags(['warehouses'], WarehouseActivated::class);
}

// Line 44-48 — BUG
public function onWarehouseDeactivated(WarehouseDeactivated $event): void
{
    $this->invalidateTags(['warehouses'], WarehouseDeactivated::class);
}
```

Compare with the **correct** `onWarehouseCreated`:
```php
public function onWarehouseCreated(WarehouseCreated $event): void
{
    $this->invalidateTags(
        [$this->cacheService->tenantResourceTag($event->tenantId->toString(), 'warehouses')],
        WarehouseCreated::class
    );
}
```

### Root Cause

Three warehouse events lack `tenantId`:

| Event | Has `tenantId`? |
|-------|----------------|
| `WarehouseCreated` | YES (`public TenantId $tenantId`) — via `WarehouseCreated.php` line 20 |
| `WarehouseUpdated` | NO — only has `WarehouseId` + `WarehouseName` |
| `WarehouseActivated` | NO — only has `WarehouseId` |
| `WarehouseDeactivated` | NO — only has `WarehouseId` |

### Fix Specification

**Step 1:** Add `TenantId` to all three events:

```php
// src/Inventory/Domain/Event/WarehouseUpdated.php
final readonly class WarehouseUpdated implements DomainEvent
{
    public function __construct(
        public WarehouseId $warehouseId,
        public TenantId $tenantId,      // ADD THIS
        public WarehouseName $name,
        public \DateTimeImmutable $occurredOn,
    ) {}
}
```

```php
// src/Inventory/Domain/Event/WarehouseActivated.php
final readonly class WarehouseActivated implements DomainEvent
{
    public function __construct(
        public WarehouseId $warehouseId,
        public TenantId $tenantId,      // ADD THIS
        public \DateTimeImmutable $occurredOn,
    ) {}
}
```

```php
// src/Inventory/Domain/Event/WarehouseDeactivated.php (same pattern)
```

**Step 2:** Update dispatch sites in the Warehouse aggregate to pass `$this->tenantId`.

**Step 3:** Update subscriber handlers:

```php
public function onWarehouseUpdated(WarehouseUpdated $event): void
{
    $this->invalidateTags(
        [$this->cacheService->tenantResourceTag($event->tenantId->toString(), 'warehouses')],
        WarehouseUpdated::class
    );
}
// Same pattern for onWarehouseActivated, onWarehouseDeactivated
```

---

## Finding 3: Missing Stock Event Cache Invalidation

**File:** `src/Inventory/Infrastructure/EventSubscriber/InventoryCacheInvalidationSubscriber.php`
**Severity:** P1

### Problem

The subscriber only handles 4 warehouse events. It does NOT handle any of the 6 stock events:

| Stock Event | Cache Invalidation Handler | Existing Handler |
|-------------|---------------------------|-----------------|
| `StockAdjusted` | NONE | Mercure notification only |
| `StockAllocated` | NONE | Audit log only |
| `StockReleased` | NONE | Audit log only |
| `StockReserved` | NONE | Audit log only |
| `StockDepleted` | NONE | Email alert only |
| `StockTransferred` | NONE | NONE |

### Impact

When stock levels change (restock, allocation, reservation, transfer), cached product listings continue serving **stale stock counts**. Customers may:
- Add out-of-stock items to cart
- See "in stock" for depleted items
- See wrong availability after transfers between warehouses

### TenantId Availability in Stock Events

| Event | Has `tenantId`? | Has `productId`? |
|-------|----------------|-----------------|
| `StockAdjusted` | NO | NO (only `StockItemId`) |
| `StockAllocated` | NO | NO (only `StockItemId`) |
| `StockReleased` | NO | NO (only `StockItemId`) |
| `StockReserved` | NO | NO (only `StockItemId`) |
| `StockDepleted` | YES | YES |
| `StockTransferred` | YES | YES |

### Fix Specification

**Step 1:** Add `TenantId` and `ProductId` to the 4 events that lack them.

The `StockItem` aggregate has `$this->tenantId` and `$this->productId` available at all dispatch sites. Add these to the event constructors:

```php
// src/Inventory/Domain/Event/StockAdjusted.php
final readonly class StockAdjusted implements DomainEvent
{
    public function __construct(
        public StockItemId $stockItemId,
        public TenantId $tenantId,          // ADD
        public ProductId $productId,        // ADD
        public Quantity $previousQuantity,
        public Quantity $newQuantity,
        public string $reason,
        public \DateTimeImmutable $occurredOn,
    ) {}
}
```

Same pattern for `StockAllocated`, `StockReleased`, `StockReserved`.

**Step 2:** Update dispatch sites in `StockItem` aggregate (lines 99, 129, 174, 199):

```php
// StockItem.php line 99 (reserve method)
$this->recordEvent(new StockReserved(
    $this->id,
    $this->tenantId,     // ADD
    $this->productId,    // ADD
    $quantity,
    $reservationId,
    new \DateTimeImmutable()
));
```

Same pattern for `allocate()`, `release()`, `adjust()`.

**Step 3:** Add stock event handlers to `InventoryCacheInvalidationSubscriber`:

```php
#[AsMessageHandler(bus: 'event.bus')]
public function onStockAdjusted(StockAdjusted $event): void
{
    $this->invalidateStockTags($event->tenantId, $event->productId);
}

#[AsMessageHandler(bus: 'event.bus')]
public function onStockAllocated(StockAllocated $event): void
{
    $this->invalidateStockTags($event->tenantId, $event->productId);
}

#[AsMessageHandler(bus: 'event.bus')]
public function onStockReleased(StockReleased $event): void
{
    $this->invalidateStockTags($event->tenantId, $event->productId);
}

#[AsMessageHandler(bus: 'event.bus')]
public function onStockReserved(StockReserved $event): void
{
    $this->invalidateStockTags($event->tenantId, $event->productId);
}

#[AsMessageHandler(bus: 'event.bus')]
public function onStockDepleted(StockDepleted $event): void
{
    $this->invalidateStockTags($event->tenantId, $event->productId);
}

#[AsMessageHandler(bus: 'event.bus')]
public function onStockTransferred(StockTransferred $event): void
{
    $tenantId = $event->tenantId->toString();
    $this->invalidateTags([
        $this->cacheService->tenantResourceTag($tenantId, 'products'),
        $this->cacheService->tag('product', $event->productId->toString()),
        $this->cacheService->tenantResourceTag($tenantId, 'warehouses'),
    ], StockTransferred::class);
}

private function invalidateStockTags(TenantId $tenantId, ProductId $productId): void
{
    $tid = $tenantId->toString();
    $this->invalidateTags([
        $this->cacheService->tenantResourceTag($tid, 'products'),
        $this->cacheService->tag('product', $productId->toString()),
    ], 'StockChange');
}
```

---

## Finding 4: HTTP vs Backend TTL Mismatch

**Severity:** P2

### Problem

Categories have mismatched TTLs between HTTP cache headers and backend Redis cache:

| Resource | HTTP Cache (ApiCacheSubscriber) | Backend Redis (CachedCollectionProvider) | Redis Pool (cache.yaml) |
|----------|-------------------------------|----------------------------------------|------------------------|
| Products | 300s | 300s | 300s | **MATCH** |
| Categories | **600s** | **600s** | **300s** | **MISMATCH** |
| PriceLists | 300s | 300s | 300s | **MATCH** |
| Warehouses | 600s | 600s | N/A | OK |
| Translations | 3600s | N/A | 86400s | OK (different layers) |

The `cache.catalog` pool has `default_lifetime: 300` but `CachedCollectionProvider.determineTTL()` returns 600 for categories. The pool TTL wins in Symfony's cache component when using `ItemInterface::expiresAfter()`, so this is actually OK — the CacheService's `get()` method explicitly sets TTL via `$item->expiresAfter($ttl)` which overrides the pool default.

**However**, the HTTP `max-age=600` means browsers/CDNs cache for 10 minutes while backend cache also caches for 10 minutes. If a category is updated at T=0:
1. Backend cache invalidated immediately (via domain event → `CacheInvalidationSubscriber`)
2. HTTP cache at browser/CDN **still serves stale data for up to 600 seconds**
3. No purge mechanism exists for the HTTP layer

### Fix Specification

This is by design for public cache headers (HTTP caching is inherently eventually-consistent). However:

1. Ensure `Vary: X-Tenant-ID` is set (confirmed — both subscribers do this)
2. Consider reducing category HTTP `max-age` to 300s to match products, or document the 600s staleness window as accepted trade-off
3. For admin endpoints (non-storefront), categories should use `private, no-cache` to ensure admins see immediate changes

---

## Finding 5: CachedCollectionProvider Activation Status

**Severity:** P0 (performance, not security)

### Current State

```yaml
# config/services.yaml line 21 — EXCLUDED from autowiring
exclude:
    - '../src/Shared/Infrastructure/ApiPlatform/State/CachedCollectionProvider.php'

# config/services.yaml line 78-81 — MANUALLY registered as decorator
App\Shared\Infrastructure\ApiPlatform\State\CachedCollectionProvider:
    decorates: 'api_platform.state_provider.locator'
    arguments:
        $decorated: '@.inner'
```

The exclude + manual registration pattern is correct Symfony practice. The decorator **should** be active. However, Sprint 3 benchmarks showed 0% cache hit rate, suggesting either:
- The decorator IS registered but `CacheService` falls through to the callback every time (exception path)
- Redis connection issues causing silent fallback
- The `cache.app` pool (injected into CacheService) doesn't support tags, causing `invalidateTags()` to silently fail

### Verification Steps

```bash
# Check if decorator is actually wired
php bin/console debug:container CachedCollectionProvider

# Check if CacheService gets a TagAwareCacheInterface
php bin/console debug:container cache.app

# Test Redis connectivity
php bin/console cache:pool:list
php bin/console cache:pool:prune
```

### Fix Specification

No code change needed — verify via CLI that the decorator is active. If it's not, the issue is in the container compilation, not the service definition.

---

## Summary: Files to Modify

### Domain Events (add `tenantId` / `productId`)

| File | Change |
|------|--------|
| `src/Pricing/Domain/Event/PriceListActivated.php` | Add `string $tenantId` + getter + toArray |
| `src/Pricing/Domain/Event/PriceListDeactivated.php` | Add `string $tenantId` + getter + toArray |
| `src/Inventory/Domain/Event/WarehouseUpdated.php` | Add `TenantId $tenantId` |
| `src/Inventory/Domain/Event/WarehouseActivated.php` | Add `TenantId $tenantId` |
| `src/Inventory/Domain/Event/WarehouseDeactivated.php` | Add `TenantId $tenantId` |
| `src/Inventory/Domain/Event/StockAdjusted.php` | Add `TenantId $tenantId` + `ProductId $productId` |
| `src/Inventory/Domain/Event/StockAllocated.php` | Add `TenantId $tenantId` + `ProductId $productId` |
| `src/Inventory/Domain/Event/StockReleased.php` | Add `TenantId $tenantId` + `ProductId $productId` |
| `src/Inventory/Domain/Event/StockReserved.php` | Add `TenantId $tenantId` + `ProductId $productId` |

### Aggregates (update dispatch sites)

| File | Change |
|------|--------|
| `src/Pricing/Domain/Model/PriceList.php` (or equivalent) | Pass `$this->tenantId` to PriceListActivated/Deactivated constructors |
| `src/Inventory/Domain/Model/Warehouse.php` (or equivalent) | Pass `$this->tenantId` to WarehouseUpdated/Activated/Deactivated constructors |
| `src/Inventory/Domain/Model/StockItem.php` | Pass `$this->tenantId` + `$this->productId` to StockAdjusted/Allocated/Released/Reserved constructors (lines 99, 129, 174, 199) |

### Cache Invalidation Subscribers

| File | Change |
|------|--------|
| `src/Pricing/Infrastructure/EventSubscriber/PricingCacheInvalidationSubscriber.php` | Lines 53, 58 → use `$event->tenantId()` |
| `src/Inventory/Infrastructure/EventSubscriber/InventoryCacheInvalidationSubscriber.php` | Lines 35, 41, 47 → use `$event->tenantId`; add 6 stock event handlers |

### Tests to Create/Update

| Test | Scope |
|------|-------|
| `tests/Unit/Pricing/Infrastructure/EventSubscriber/PricingCacheInvalidationSubscriberTest.php` | Verify tenant-scoped tags on all 9 events |
| `tests/Unit/Inventory/Infrastructure/EventSubscriber/InventoryCacheInvalidationSubscriberTest.php` | Verify tenant-scoped tags on all 10 events (4 warehouse + 6 stock) |
| `tests/Unit/Inventory/Domain/Model/StockItemTest.php` | Verify events carry tenantId + productId |
| Existing tests dispatching these events | Update constructor calls with new parameters |

---

## Risk Assessment

| Risk | Mitigation |
|------|-----------|
| Constructor changes break existing event dispatch sites | Search for `new PriceListActivated(`, `new WarehouseUpdated(`, etc. and update all call sites |
| Serialized events in RabbitMQ queue may lack new fields | Deploy subscriber changes AFTER draining existing queue, or use default parameter values during migration |
| Tests mocking these events will break | Update all test factories/mocks |

---

## Checklist for Codex CLI Implementation

- [ ] Add `tenantId` to `PriceListActivated`, `PriceListDeactivated`
- [ ] Add `tenantId` to `WarehouseUpdated`, `WarehouseActivated`, `WarehouseDeactivated`
- [ ] Add `tenantId` + `productId` to `StockAdjusted`, `StockAllocated`, `StockReleased`, `StockReserved`
- [ ] Update `StockItem` aggregate dispatch sites (reserve, allocate, release, adjust)
- [ ] Update PriceList aggregate dispatch sites
- [ ] Update Warehouse aggregate dispatch sites
- [ ] Fix `PricingCacheInvalidationSubscriber` lines 53, 58
- [ ] Fix `InventoryCacheInvalidationSubscriber` lines 35, 41, 47
- [ ] Add 6 stock event handlers to `InventoryCacheInvalidationSubscriber`
- [ ] Update/create unit tests for all changed files
- [ ] Run quality gates: `vendor/bin/phpstan analyse && vendor/bin/deptrac analyse && vendor/bin/phpunit`
- [ ] Verify no existing tests break from constructor changes (`grep -r "new StockAdjusted\|new StockAllocated\|new StockReleased\|new StockReserved\|new PriceListActivated\|new PriceListDeactivated\|new WarehouseUpdated\|new WarehouseActivated\|new WarehouseDeactivated"`)
