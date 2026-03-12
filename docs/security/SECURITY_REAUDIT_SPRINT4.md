# Security Re-Audit: Sprint 4 P0 Fix Verification

**Date**: 2026-03-10
**Auditor**: Claude Opus 4.6 (automated security re-audit)
**Scope**: Verify all P0 security fixes from Sprint 4
**Method**: Code review + live database testing

---

## Executive Summary

| Finding | TSK | Verdict | Severity |
|---------|-----|---------|----------|
| SQL Injection in TenantConnectionSubscriber | TSK-27 | **VERIFIED FIXED** | P0→Resolved |
| COALESCE RLS bypass on 5+ tables | TSK-28 | **VERIFIED FIXED** | P0→Resolved |
| Tenant context loss during checkout | TSK-29 | **VERIFIED FIXED** | P0→Resolved |
| Cross-tenant cache leakage | TSK-33 | **VERIFIED FIXED** | P0→Resolved |

**Result: All 4 P0 vulnerabilities are confirmed fixed.**

---

## TSK-27: SQL Injection in TenantConnectionSubscriber

### Original Vulnerability
String concatenation in `SET app.tenant_id = '{$id}'` allowed SQL injection via crafted tenant ID headers.

### Fix Verification

**File**: `src/Shared/Infrastructure/Doctrine/TenantConnectionSubscriber.php` (lines 57-61)

```php
$stmt = $connection->prepare(
    "SELECT set_config('app.tenant_id', ?, false)"
);
$stmt->bindValue(1, $tenantId->toString(), ParameterType::STRING);
$stmt->execute();
```

**All locations setting tenant context** (verified parameterized):

| File | Line | Pattern | Status |
|------|------|---------|--------|
| `TenantConnectionSubscriber.php` | 57-61 | `prepare()` + `bindValue()` | SAFE |
| `TenantContext.php` | 33-36 | `executeStatement()` with `:tenantId` param | SAFE |
| `DashboardStatsProvider.php` | 49 | `set_config()` with `:tenantId` param | SAFE |
| `InventoryStatsProvider.php` | 30 | `set_config()` with `:tenantId` param | SAFE |
| `EUVatRatesFixture.php` | 91 | `set_config()` with `:tenantId` param (fixture) | SAFE |
| `ReviewFixtures.php` | 54 | `set_config()` with `:tenantId` param (fixture) | SAFE |
| `EULaunchPromotionsFixture.php` | 57 | `set_config()` with `:tenantId` param (fixture) | SAFE |

**No string concatenation or interpolation found in any SQL query involving tenant context.**

### Verdict: **VERIFIED FIXED**

---

## TSK-28: COALESCE RLS Policy Bypass

### Original Vulnerability
5 tables used COALESCE pattern in RLS policies:
```sql
tenant_id = COALESCE(NULLIF(current_setting('app.tenant_id', true), '')::UUID, tenant_id)
```
When `app.tenant_id` is empty/unset: `COALESCE(NULL, tenant_id) = tenant_id` → always true → ALL rows returned.

### Fix Verification

**Migration applied**: `Version20260310120000_FixCoalesceRLSPolicies.php`

**All 48 RLS policies verified** via `pg_policies`. Every policy uses the safe pattern:
```sql
(tenant_id)::text = current_setting('app.tenant_id'::text, true)
```

**Zero COALESCE patterns found in active policies.** COALESCE only exists in historical migrations (the `down()` methods and the old migration files that created the original policies).

**43 tenant-scoped tables** — ALL have:
- RLS enabled (`rowsecurity = true`)
- Correct policy using `::text = current_setting()` pattern

**Live bypass tests** (all returned 0 rows as expected):

| Test | Table | Result |
|------|-------|--------|
| `SET LOCAL app.tenant_id = ''` | `catalog_products` | 0 rows |
| `RESET app.tenant_id` | `catalog_products` | 0 rows |
| `SET LOCAL app.tenant_id = ''` | `orders` | 0 rows |
| `SET LOCAL app.tenant_id = ''` | `invoices` | 0 rows |
| `SET LOCAL app.tenant_id = ''` | `customers` | 0 rows |
| `SET LOCAL app.tenant_id = ''` | `payments` | 0 rows |
| `SET LOCAL app.tenant_id = ''` | `wishlists` | 0 rows |

**Special policies reviewed**:
- `tenant_feature_flags`: Has `feature_flags_admin_bypass` (qual: `true`) — this is intentional for admin access
- `tenants`: Self-isolation with `app.bypass_rls` check — intentional for tenant management
- `consent_history`: Superadmin policy with `app.is_superadmin` check — intentional for compliance

### Verdict: **VERIFIED FIXED**

---

## TSK-29: Tenant Context Loss During Checkout Flow

### Original Vulnerability
Tenant context could be cleared during `Cart → Checkout → Payment → Invoice` flow when sync message handlers cleared context in `finally` block, creating a cross-tenant exposure window (amplified by COALESCE RLS).

### Fix Verification

#### 1. TenantContextMiddleware (src/Shared/Infrastructure/Messenger/TenantContextMiddleware.php)

**Key fix** (lines 46-71): The middleware now **saves and restores previous tenant context** instead of clearing it:

```php
// Save previous tenant context to restore after handling.
$previousTenantId = $this->tenantContext->getCurrentTenantId();
// ... handle message ...
try {
    return $stack->next()->handle($envelope, $stack);
} finally {
    // Restore previous tenant context instead of clearing.
    if (null !== $previousTenantId) {
        $this->tenantContext->setCurrentTenant($previousTenantId);
    } else {
        $this->tenantContext->clearCurrentTenant();
    }
}
```

This is the correct pattern — nested sync message dispatches (e.g., order creation dispatching domain events via sync transport) no longer destroy the parent request's tenant context.

#### 2. Middleware Registration (config/packages/messenger.yaml)

TenantContextMiddleware is registered on ALL three buses:
- `command.bus` (line 9) — before `doctrine_transaction`
- `query.bus` (line 13) — before `doctrine_transaction`
- `event.bus` (line 18) — before `doctrine_transaction`

#### 3. CheckoutProcessor Defensive Check (src/Cart/Presentation/Api/Processor/CheckoutProcessor.php)

**Lines 146-152**: Additional safety net — verifies tenant context survived the dispatch chain:

```php
if (!$this->tenantContext->hasCurrentTenant()) {
    $this->logger->warning('Tenant context lost during order dispatch, restoring', [...]);
    $this->tenantContext->setCurrentTenant($tenantId);
}
```

This defensive check catches any edge case where a misconfigured subscriber might still clear context.

#### 4. TenantStamp Propagation (src/Shared/Infrastructure/Messenger/TenantStamp.php)

Immutable stamp carrying tenant ID across async boundaries. Producer stamps on dispatch, consumer restores on receive.

#### 5. Flow Trace: Cart → Checkout → Order

1. **Request arrives** with `X-Tenant-ID` header → `TenantContext::setCurrentTenant()` sets DB session var
2. **CheckoutProcessor** validates tenant (line 64-67), verifies cart belongs to tenant (line 80)
3. **Command dispatch** → `TenantContextMiddleware` stamps `PlaceOrderCommand` with `TenantStamp`
4. **Handler executes** with tenant context preserved (sync transport: middleware saves/restores parent context)
5. **Post-dispatch**: CheckoutProcessor verifies context survived (defensive check)
6. **Cart marked as converted** with tenant context still active

#### 6. Test Coverage

- `tests/Unit/Shared/Infrastructure/Messenger/TenantContextMiddlewareTest.php` — covers save/restore behavior
- `tests/Unit/Shared/Infrastructure/Messenger/TenantStampTest.php` — covers stamp serialization

### Verdict: **VERIFIED FIXED**

---

## TSK-33: Cross-Tenant Cache Leakage

### Original Vulnerability
1. `PricingCacheInvalidationSubscriber` and `InventoryCacheInvalidationSubscriber` did not include `tenant_id` in cache tags → invalidating Tenant A's cache would miss Tenant B's stale entries (or vice versa)
2. `$item` variable undefined bug in `ProductListingProvider:155`, `FeaturedProductsProvider:87`, `FrontCategoriesProvider:87`

### Fix Verification

#### 1. PricingCacheInvalidationSubscriber (src/Pricing/Infrastructure/EventSubscriber/)

**All 9 event handlers** use tenant-scoped tags via `tenantResourceTags()` helper:

```php
private function tenantResourceTags(string $tenantId, string $resource, ?string $itemTag = null): array
{
    $tags = [$this->cacheService->tenantResourceTag($tenantId, $resource)];
    // ...
}
```

Example: `onPriceListCreated` → `$this->tenantResourceTags($event->tenantId(), 'price_lists')` generates tag like `tenant.{uuid}.price_lists`.

Cross-reference: `CacheService::tenantResourceTag()` produces `tenant.{tenantId}.{resource}` — tenant-scoped by design.

**All events verified**: PriceListCreated, PriceListActivated, PriceListDeactivated, PromotionCreated, PromotionUpdated, PromotionActivated, PromotionDeactivated, FlashSaleActivated, FlashSaleEnded.

#### 2. InventoryCacheInvalidationSubscriber (src/Inventory/Infrastructure/EventSubscriber/)

**All 10 event handlers** use tenant-scoped tags:
- Warehouse events: `tenantResourceTag($tenantId->toString(), 'warehouses')`
- Stock events: `tenantResourceTag($tenant, 'products')` + `tag('product', $productId->toString())`
- StockTransferred: Both products and warehouses tags, tenant-scoped

**All events verified**: WarehouseCreated, WarehouseUpdated, WarehouseActivated, WarehouseDeactivated, StockAdjusted, StockAllocated, StockReleased, StockReserved, StockDepleted, StockTransferred.

#### 3. CacheInvalidationSubscriber (src/Shared/Infrastructure/EventSubscriber/)

Central subscriber for catalog/translation events. **All 11 handlers** use tenant-scoped tags via `tenantResourceTags()` helper — same pattern as pricing/inventory subscribers.

#### 4. $item Variable Bug — Resolved

The three providers (`ProductListingProvider`, `FeaturedProductsProvider`, `FrontCategoriesProvider`) no longer contain inline caching code. They query directly and delegate caching to `CachedCollectionProvider` which decorates the state provider locator.

- `ProductListingProvider` (95 lines) — no cache code
- `FeaturedProductsProvider` (73 lines) — no cache code
- `FrontCategoriesProvider` (104 lines) — no cache code

The `$item` bug was in older versions with inline caching; it's been eliminated by architectural separation.

#### 5. CachedCollectionProvider (src/Shared/Infrastructure/ApiPlatform/State/)

Now **ENABLED** in `config/services.yaml` (line 78) — decorates `api_platform.state_provider.locator`.

Cache keys include tenant ID:
```php
$cacheKey = $this->cacheService->tenantQueryKey($tenantId, 'api', $resource, [...]);
// → tenant:{tenantId}:api:{resource}:{hash}
```

Cache tags include tenant scoping:
```php
private function generateTags(string $tenantId, string $resource): array
{
    return [
        $this->cacheService->tag('api'),
        ...$this->cacheService->tenantScopedTags($tenantId, $resource),
    ];
}
```

`tenantScopedTags()` returns: `[tenant.{id}, {resource}, tenant.{id}.{resource}]` — fully isolated per tenant.

### Verdict: **VERIFIED FIXED**

---

## Residual Observations (Not P0, for tracking)

### P3: Test Code Uses Unsafe Pattern (TSK-27 related)
`TenantTestTrait.php:44-46` and ~26 test files use `sprintf("SET app.tenant_id = '%s'", $tenantId)` instead of parameterized `set_config()`. While test-only and values are hardcoded UUIDs (no injection risk), this establishes a pattern developers may copy. **Recommended**: Update `TenantTestTrait` to use `set_config()` with named parameters.

### P3: Outdated Documentation
`migrations/INVOICE_MIGRATION_README.md:160` contains the old vulnerable pattern `SET app.tenant_id = '{$tenantId}'` as example code. Should be updated to prevent copy-paste errors.

### P3: Bare Product Tag in InventoryCacheInvalidationSubscriber
`tag('product', $productId->toString())` at lines 92/111 creates a tag without tenant prefix. Safe because UUIDs are globally unique, but stylistically inconsistent. Consider `tenantResourceTag($tenantId, 'product:'.$productId)` for consistency.

### Informational
1. **`tenant_feature_flags` admin bypass policy** (`qual: true`): Intentional but should be documented — allows any DB user to read all feature flags without tenant context.
2. **HTTP Vary headers**: Both `ApiCacheSubscriber` and `StorefrontCacheSubscriber` correctly include `X-Tenant-ID` in Vary headers, preventing CDN/proxy cross-tenant serving.
3. **CachedCollectionProvider `X-No-Cache` bypass**: Should be rate-limited or restricted to authenticated admin users to prevent cache-busting DoS.
4. **CachedCollectionProvider tenant fallback**: Missing `X-Tenant-ID` header falls back to `'default'` cache key. Safe because providers return `[]` for missing tenant, but should be monitored if behavior changes.
5. **PriceList events type inconsistency**: `PriceListCreated::tenantId()` returns `string` while `PromotionCreated::tenantId()` returns `TenantId`. Not a security issue but increases maintenance risk.

---

## Conclusion

All 4 P0 security vulnerabilities identified in Sprint 3 cross-audit have been **verified as fixed** through:
- Static code analysis of all relevant source files
- Live database testing of RLS bypass scenarios
- Tracing of request flow through middleware chain
- Verification of cache tag isolation patterns

The multi-tenant isolation boundary is now properly enforced at all layers: database (RLS), application (tenant context middleware), and caching (tenant-scoped keys/tags).
