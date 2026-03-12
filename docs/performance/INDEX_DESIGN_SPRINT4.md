# Composite Index Design — Sprint 4

**Date**: 2026-03-10
**Task**: TSK-31
**Author**: Claude Opus (cross-referenced with pg_stat_user_tables + codebase query patterns)

---

## 0. CRITICAL FINDING: RLS `::text` Cast Prevents ALL Index Usage

**Root Cause of Products 430ms / Orders 281ms**: The RLS policies use `tenant_id::text = current_setting(...)` which applies a `::text` cast on the `uuid` column. PostgreSQL cannot use a standard B-tree index on `tenant_id` (uuid) when the query filters on `tenant_id::text` (text). **Every existing `tenant_id` index is effectively dead for RLS-filtered queries.**

### Proof (EXPLAIN ANALYZE on catalog_products)

**Before** (standard `tenant_id` index):
```
Seq Scan on catalog_products  (cost=0.00..674.25 rows=1)  actual time=137.821ms
  Filter: ((tenant_id)::text = current_setting('app.tenant_id', true))
  Rows Removed by Filter: 1008
  Buffers: shared hit=648
```

**After** (expression index on `(tenant_id::text)`):
```
Bitmap Index Scan on idx_test  (cost=0.00..4.33 rows=5)  actual time=0.016ms
  Index Cond: ((tenant_id)::text = current_setting('app.tenant_id', true))
  Buffers: shared hit=2
Execution Time: 0.093ms
```

**Result: 1,480x speedup** (137ms → 0.09ms) just from matching the index expression to the RLS cast pattern.

### Solution

All new composite indexes MUST use `(tenant_id::text)` as the leading expression column to match the RLS policy predicate. This is a **non-negotiable requirement** — without it, indexes are ignored.

> **Alternative (better long-term)**: Change RLS policies to compare `tenant_id = current_setting('app.tenant_id', true)::uuid`. This would allow standard B-tree indexes on `tenant_id` to work. However, this requires migrating ALL RLS policies across ALL tables and thorough testing. Recommended as a separate task.

---

## 1. Current State Summary

### Database Statistics (pg_stat_user_tables)

| Table | seq_scan | idx_scan | idx% | Rows | Verdict |
|---|---|---|---|---|---|
| catalog_products | 11,226 | 49,294 | 81.5% | 975 | HIGH seq_scan — critical path |
| catalog_categories | 720 | 2,635 | 78.5% | 54 | Moderate |
| catalog_product_option_values | 150 | 0 | 0% | 1,080 | Zero index usage |
| orders | 150 | 11,671 | 98.7% | 456 | Good, but still 150 seq |
| catalog_product_variants | 135 | 24,396 | 99.4% | 1,080 | Good |
| product_reviews | 87 | 0 | 0% | 0 | No indexes used |
| audit_log | 84 | 1 | 1.2% | 1,491 | Only single-col indexes |
| media_images | — | — | — | 0 | No indexes besides PK |
| carts | 102 | 4 | 3.8% | 0 | Nearly zero idx usage |
| price_lists | 90 | 0 | 0% | 0 | Zero index usage |

### Most-Used Indexes (All Inefficient)

| Index | Scans | Tuples Read | Note |
|---|---|---|---|
| `idx_products_tenant_id` | 14,578 | 4,998,534 | 343 tuples/scan on 975-row table = FULL TABLE SCAN per call |
| `idx_62534e219033212a` (customers) | 11,449 | 1,258,876 | 110 tuples/scan on 216-row table |
| `idx_orders_tenant_id` | 4,315 | 915,625 | 212 tuples/scan on 456-row table |

**Diagnosis**: Indexes with leading `tenant_id` (uuid) ARE used when app code explicitly adds `WHERE tenant_id = :id` with a uuid parameter. But the RLS-injected `tenant_id::text = current_setting(...)` CANNOT use these indexes. Since most queries go through RLS, the planner falls back to Seq Scan for the RLS predicate, then applies the rest of the WHERE clause as a filter.

---

## 2. Index Design

### Methodology

1. Cross-referenced `pg_stat_user_tables` with all Doctrine repository queries and DBAL raw queries
2. **Expression indexes**: All indexes use `(tenant_id::text)` to match the RLS cast pattern
3. Composite indexes follow the **Equality → Inequality → Sort** ordering principle
4. All indexes use `CONCURRENTLY` to avoid table locks

---

### TIER 1: Critical Path (Products & Orders — Sprint 3 FAIL endpoints)

These indexes directly address the Products (430ms) and Orders (281ms) performance failures.

#### IDX-01: `catalog_products((tenant_id::text), category_id, active, created_at DESC)`
```sql
CREATE INDEX CONCURRENTLY idx_products_tenant_cat_active_created
ON catalog_products ((tenant_id::text), category_id, active, created_at DESC);
```
**Query**: `ProductListingReadRepository::findForStorefront()` — category-filtered product listings
**Replaces**: `idx_products_category_id` (single-column, no tenant prefix)
**Impact**: Eliminates seq_scan for the most common storefront query pattern

#### IDX-02: `catalog_products((tenant_id::text), is_featured, active, created_at DESC)`
```sql
CREATE INDEX CONCURRENTLY idx_products_tenant_featured
ON catalog_products ((tenant_id::text), is_featured, active, created_at DESC);
```
**Query**: `FeaturedProductsProvider` — featured products on homepage
**Impact**: Currently causes full table scan with is_featured filter

#### IDX-03: `catalog_products((tenant_id::text), active, price_amount)`
```sql
CREATE INDEX CONCURRENTLY idx_products_tenant_active_price
ON catalog_products ((tenant_id::text), active, price_amount);
```
**Query**: `ProductListingReadRepository::findForStorefront()` — price range filtering
**Replaces**: `idx_products_price_amount` (single-column, no tenant prefix)

#### IDX-04: `catalog_products((tenant_id::text), active, created_at DESC)`
```sql
CREATE INDEX CONCURRENTLY idx_products_tenant_text_active_created
ON catalog_products ((tenant_id::text), active, created_at DESC);
```
**Query**: Default product listing (no category/price filter) — the most common query
**Impact**: This is the PRIMARY index for the 430ms products endpoint. Replaces the existing `idx_products_tenant_active_created` which uses uuid (broken with RLS).

#### IDX-05: `catalog_categories((tenant_id::text), slug)`
```sql
CREATE INDEX CONCURRENTLY idx_categories_tenant_slug
ON catalog_categories ((tenant_id::text), slug);
```
**Query**: `DoctrineCategoryRepository::findBySlug(TenantId, Slug)`
**Impact**: Direct lookup for category pages

#### IDX-06: `catalog_categories((tenant_id::text), parent_id, position)`
```sql
CREATE INDEX CONCURRENTLY idx_categories_tenant_parent_position
ON catalog_categories ((tenant_id::text), parent_id, position);
```
**Query**: `DoctrineCategoryRepository::findByParent()` — navigation tree
**Replaces**: `idx_categories_parent_position` (no tenant prefix)

#### IDX-07: `catalog_categories((tenant_id::text), show_on_front, created_at DESC)`
```sql
CREATE INDEX CONCURRENTLY idx_categories_tenant_front
ON catalog_categories ((tenant_id::text), show_on_front, created_at DESC);
```
**Query**: `FrontCategoriesProvider` — homepage categories

#### IDX-08: `orders((tenant_id::text), created_at DESC)`
```sql
CREATE INDEX CONCURRENTLY idx_orders_tenant_text_created_desc
ON orders ((tenant_id::text), created_at DESC);
```
**Query**: `DoctrineORMOrderRepository::findAllByTenant()`, `OrderListingReadRepository::findForAdmin()`
**Impact**: Most common order listing pattern. Directly fixes the 281ms endpoint.

#### IDX-09: `orders((tenant_id::text), status, created_at DESC)`
```sql
CREATE INDEX CONCURRENTLY idx_orders_tenant_text_status_created
ON orders ((tenant_id::text), status, created_at DESC);
```
**Query**: `OrderListingReadRepository::findForAdmin()` with status filter
**Replaces**: `idx_orders_tenant_status_created` (uses uuid, broken with RLS)

#### IDX-10: `catalog_product_option_values((tenant_id::text), option_id)`
```sql
CREATE INDEX CONCURRENTLY idx_option_values_tenant_text_option
ON catalog_product_option_values ((tenant_id::text), option_id);
```
**Query**: Variant option lookups (JOIN from product_options → option_values)
**Impact**: 150 seq_scans / 0 idx_scans on 1,080 rows — 100% sequential

---

### TIER 2: High-Value Composites (Common Operations)

#### IDX-11: `product_reviews((tenant_id::text), product_id, status, created_at DESC)`
```sql
CREATE INDEX CONCURRENTLY idx_reviews_tenant_text_product_status
ON product_reviews ((tenant_id::text), product_id, status, created_at DESC);
```
**Queries**: `findApprovedByProductId()`, `getAverageRating()`, `getReviewCount()`
**Replaces**: `idx_reviews_product_status` (no tenant prefix)

#### IDX-12: `media_images((tenant_id::text), owner_type, owner_id, uploaded_at DESC)`
```sql
CREATE INDEX CONCURRENTLY idx_images_tenant_text_owner
ON media_images ((tenant_id::text), owner_type, owner_id, uploaded_at DESC);
```
**Query**: `DoctrineImageRepository::findByOwner()` — product image galleries
**Impact**: Only PK exists — zero coverage for the main query pattern

#### IDX-13: `audit_log(tenant_id, occurred_at DESC)`
```sql
CREATE INDEX CONCURRENTLY idx_audit_tenant_occurred
ON audit_log (tenant_id, occurred_at DESC);
```
**Query**: `DoctrineAuditLogRepository::findByTenant()` — main audit trail listing
**Note**: `audit_log.tenant_id` is `varchar` type, not uuid — no cast issue. Standard composite is correct.

#### IDX-14: `audit_log(tenant_id, resource_type, resource_id, occurred_at DESC)`
```sql
CREATE INDEX CONCURRENTLY idx_audit_tenant_resource
ON audit_log (tenant_id, resource_type, resource_id, occurred_at DESC);
```
**Query**: `findByResource()` — resource audit trail
**Replaces**: `idx_audit_log_resource_type` + `idx_audit_log_resource_id`

#### IDX-15: `carts((tenant_id::text), customer_id, status)`
```sql
CREATE INDEX CONCURRENTLY idx_carts_tenant_text_customer_status
ON carts ((tenant_id::text), customer_id, status);
```
**Query**: `DoctrineCartRepository::findByCustomerId()` — always filters status='active'
**Replaces**: `idx_carts_tenant_customer`

#### IDX-16: `carts((tenant_id::text), session_id, status)`
```sql
CREATE INDEX CONCURRENTLY idx_carts_tenant_text_session_status
ON carts ((tenant_id::text), session_id, status);
```
**Query**: `DoctrineCartRepository::findBySessionId()` — always filters status='active'
**Replaces**: `idx_carts_tenant_session`

#### IDX-17: `carts(status, updated_at)`
```sql
CREATE INDEX CONCURRENTLY idx_carts_status_updated
ON carts (status, updated_at);
```
**Queries**: `findExpired()`, `findAbandonedCartsForEmail()` — batch cleanup (no tenant filter)
**Replaces**: `idx_carts_status` + `idx_carts_updated_at`

#### IDX-18: `price_lists((tenant_id::text), is_active, priority DESC, valid_from, valid_to)`
```sql
CREATE INDEX CONCURRENTLY idx_price_lists_tenant_text_active_priority
ON price_lists ((tenant_id::text), is_active, priority DESC, valid_from, valid_to);
```
**Query**: `findValidForTenant()`, `findActiveByTenantId()` — date-ranged price lists
**Replaces**: `idx_price_lists_tenant_active`

#### IDX-19: `promotions((tenant_id::text), is_active, priority DESC, valid_from, valid_to)`
```sql
CREATE INDEX CONCURRENTLY idx_promotions_tenant_text_active_priority
ON promotions ((tenant_id::text), is_active, priority DESC, valid_from, valid_to);
```
**Query**: `findActiveByTenantId()` — active promotions with priority ordering
**Replaces**: `idx_promotions_tenant_active`

#### IDX-20: `fulfillments((tenant_id::text), order_id)`
```sql
CREATE INDEX CONCURRENTLY idx_fulfillments_tenant_text_order
ON fulfillments ((tenant_id::text), order_id);
```
**Query**: `findByOrderId()` (with RLS adding tenant_id check)
**Replaces**: `idx_fulfillments_order_id`

#### IDX-21: `warehouses((tenant_id::text), is_active, priority, name)`
```sql
CREATE INDEX CONCURRENTLY idx_warehouses_tenant_text_active_priority
ON warehouses ((tenant_id::text), is_active, priority, name);
```
**Query**: `findActiveByTenant()` — ORDER BY (priority ASC, name ASC)

#### IDX-22: `stock_items((tenant_id::text), product_id, warehouse_id)`
```sql
CREATE INDEX CONCURRENTLY idx_stock_tenant_text_product_warehouse
ON stock_items ((tenant_id::text), product_id, warehouse_id);
```
**Query**: `findByProductAndWarehouse()`, `findByProduct()` — availability checks
**Replaces**: `idx_stock_tenant_product`

#### IDX-23: `customers((tenant_id::text), email_blind_index)`
```sql
CREATE INDEX CONCURRENTLY idx_customers_tenant_text_email
ON customers ((tenant_id::text), email_blind_index);
```
**Query**: `findByEmail(Email, TenantId)` — customer lookup
**Impact**: `idx_62534e219033212a` (tenant_id uuid) reads 1.3M tuples — broken with RLS

#### IDX-24: `catalog_product_variants((tenant_id::text), configurable_product_id, is_active)`
```sql
CREATE INDEX CONCURRENTLY idx_variants_tenant_text_product_active
ON catalog_product_variants ((tenant_id::text), configurable_product_id, is_active);
```
**Query**: Variant lookups by configurable product
**Replaces**: `idx_product_variants_product_active` (uuid tenant, broken with RLS)

---

### TIER 3: Redundant Indexes to Drop

These indexes are superseded by new expression-based composites. Drop **after** validating new indexes with EXPLAIN ANALYZE.

**Category A: Single-column `tenant_id` indexes (redundant with composites)**

| Drop Index | Table | Covered By |
|---|---|---|
| `idx_products_tenant_id` | catalog_products | IDX-01,02,03,04 |
| `idx_products_tenant_active` | catalog_products | IDX-04 (expression version) |
| `idx_products_tenant_active_created` | catalog_products | IDX-04 (expression version) |
| `idx_categories_tenant_id` | catalog_categories | IDX-05,06,07 |
| `idx_categories_tenant_active` | catalog_categories | IDX-07 subsumes |
| `idx_orders_tenant_id` | orders | IDX-08,09 |
| `idx_orders_tenant_status_created` | orders | IDX-09 (expression version) |
| `idx_fulfillments_tenant_id` | fulfillments | IDX-20 |
| `idx_invoices_tenant_id` | invoices | future expression index |
| `idx_promotions_tenant_id` | promotions | IDX-19 |
| `idx_promotions_tenant_active` | promotions | IDX-19 (expression version) |
| `idx_carts_tenant_id` | carts | IDX-15,16 |
| `idx_carts_tenant_customer` | carts | IDX-15 |
| `idx_carts_tenant_session` | carts | IDX-16 |
| `idx_price_lists_tenant` | price_lists | IDX-18 |
| `idx_price_lists_tenant_active` | price_lists | IDX-18 (expression version) |
| `idx_stock_tenant_product` | stock_items | IDX-22 |
| `idx_stock_tenant_warehouse` | stock_items | IDX-22 can serve warehouse queries |
| `idx_option_values_tenant` | option_values | IDX-10 |
| `idx_product_variants_tenant` | variants | IDX-24 |
| `idx_product_variants_product_active` | variants | IDX-24 (expression version) |
| `idx_reviews_tenant` | reviews | IDX-11 |

**Category B: Single-column non-tenant indexes (redundant with composites)**

| Drop Index | Table | Covered By |
|---|---|---|
| `idx_products_category_id` | catalog_products | IDX-01 |
| `idx_products_price_amount` | catalog_products | IDX-03 |
| `idx_products_created_at` | catalog_products | IDX-04 |
| `idx_categories_parent_position` | catalog_categories | IDX-06 |
| `idx_categories_slug` | catalog_categories | IDX-05 + unique slug |
| `idx_orders_created_at` | orders | IDX-08 |
| `idx_fulfillments_order_id` | fulfillments | IDX-20 |
| `idx_promotions_is_active` | promotions | IDX-19 |
| `idx_promotions_priority` | promotions | IDX-19 |
| `idx_reviews_product` | reviews | IDX-11 or product_status |
| `idx_carts_status` | carts | IDX-17 |
| `idx_carts_updated_at` | carts | IDX-17 |
| `idx_price_lists_active` | price_lists | IDX-18 |
| `idx_audit_log_tenant_id` | audit_log | IDX-13 |
| `idx_audit_log_occurred_at` | audit_log | IDX-13 |
| `idx_audit_log_resource_type` | audit_log | IDX-14 |
| `idx_audit_log_resource_id` | audit_log | IDX-14 |

**Total**: ~39 redundant indexes to drop

---

## 3. Implementation Plan

### Phase 1: Create New Expression Indexes (Migration)

Create all 24 new indexes using `CREATE INDEX CONCURRENTLY`. Migration must use `isTransactional()` returning `false`.

### Phase 2: Validate with EXPLAIN ANALYZE

```sql
SET app.tenant_id = '00000000-0000-4000-8000-000000000001';

-- Products (target: <5ms query time)
EXPLAIN ANALYZE SELECT * FROM catalog_products
WHERE tenant_id::text = current_setting('app.tenant_id', true)
AND active = true ORDER BY created_at DESC LIMIT 10;

-- Orders (target: <5ms query time)
EXPLAIN ANALYZE SELECT * FROM orders
WHERE tenant_id::text = current_setting('app.tenant_id', true)
ORDER BY created_at DESC LIMIT 10;

-- Customers (verify expression index)
EXPLAIN ANALYZE SELECT * FROM customers
WHERE tenant_id::text = current_setting('app.tenant_id', true);
```

### Phase 3: Drop Redundant Indexes (Separate Migration)

Only after Phase 2 confirms new indexes are used. Separate migration for safety + easy rollback.

---

## 4. Expected Impact

| Endpoint | Current p95 | After Indexes | After Cache |
|---|---|---|---|
| GET /api/v1/products | 483ms | **<10ms** | <5ms |
| GET /api/v1/orders | 327ms | **<10ms** | <5ms |
| GET /api/v1/categories | 37ms | **<5ms** | <2ms |
| GET /api/v1/customers | 69ms | **<5ms** | <2ms |

The 1,480x improvement seen in EXPLAIN ANALYZE testing means query-level latency drops from ~138ms to ~0.1ms. The remaining endpoint latency will be dominated by PHP serialization, not database I/O.

### Write Overhead

- 24 new indexes added, ~39 old indexes dropped = net -15 indexes
- Write performance **improves** due to fewer indexes to maintain
- Expression indexes have identical write overhead to standard indexes

### Storage

- Net reduction in index count → slight storage savings
- Expression indexes on `(tenant_id::text)` store text values (36 bytes) vs uuid (16 bytes) — marginal increase per index entry, offset by dropping redundant indexes

---

## 5. Migration SQL

### Migration 1: Create Expression Indexes

```sql
-- Must run outside transaction for CONCURRENTLY support
-- Doctrine migration: isTransactional() returns false

-- TIER 1: Critical Path (Products & Orders)
CREATE INDEX CONCURRENTLY idx_products_tenant_text_active_created ON catalog_products ((tenant_id::text), active, created_at DESC);
CREATE INDEX CONCURRENTLY idx_products_tenant_text_cat_active ON catalog_products ((tenant_id::text), category_id, active, created_at DESC);
CREATE INDEX CONCURRENTLY idx_products_tenant_text_featured ON catalog_products ((tenant_id::text), is_featured, active, created_at DESC);
CREATE INDEX CONCURRENTLY idx_products_tenant_text_active_price ON catalog_products ((tenant_id::text), active, price_amount);
CREATE INDEX CONCURRENTLY idx_categories_tenant_text_slug ON catalog_categories ((tenant_id::text), slug);
CREATE INDEX CONCURRENTLY idx_categories_tenant_text_parent_pos ON catalog_categories ((tenant_id::text), parent_id, position);
CREATE INDEX CONCURRENTLY idx_categories_tenant_text_front ON catalog_categories ((tenant_id::text), show_on_front, created_at DESC);
CREATE INDEX CONCURRENTLY idx_orders_tenant_text_created ON orders ((tenant_id::text), created_at DESC);
CREATE INDEX CONCURRENTLY idx_orders_tenant_text_status_created ON orders ((tenant_id::text), status, created_at DESC);
CREATE INDEX CONCURRENTLY idx_option_values_tenant_text_option ON catalog_product_option_values ((tenant_id::text), option_id);

-- TIER 2: High-Value Composites
CREATE INDEX CONCURRENTLY idx_reviews_tenant_text_product_status ON product_reviews ((tenant_id::text), product_id, status, created_at DESC);
CREATE INDEX CONCURRENTLY idx_images_tenant_text_owner ON media_images ((tenant_id::text), owner_type, owner_id, uploaded_at DESC);
CREATE INDEX CONCURRENTLY idx_audit_tenant_occurred ON audit_log (tenant_id, occurred_at DESC);
CREATE INDEX CONCURRENTLY idx_audit_tenant_resource ON audit_log (tenant_id, resource_type, resource_id, occurred_at DESC);
CREATE INDEX CONCURRENTLY idx_carts_tenant_text_customer_status ON carts ((tenant_id::text), customer_id, status);
CREATE INDEX CONCURRENTLY idx_carts_tenant_text_session_status ON carts ((tenant_id::text), session_id, status);
CREATE INDEX CONCURRENTLY idx_carts_status_updated ON carts (status, updated_at);
CREATE INDEX CONCURRENTLY idx_price_lists_tenant_text_active_pri ON price_lists ((tenant_id::text), is_active, priority DESC, valid_from, valid_to);
CREATE INDEX CONCURRENTLY idx_promotions_tenant_text_active_pri ON promotions ((tenant_id::text), is_active, priority DESC, valid_from, valid_to);
CREATE INDEX CONCURRENTLY idx_fulfillments_tenant_text_order ON fulfillments ((tenant_id::text), order_id);
CREATE INDEX CONCURRENTLY idx_warehouses_tenant_text_active_pri ON warehouses ((tenant_id::text), is_active, priority, name);
CREATE INDEX CONCURRENTLY idx_stock_tenant_text_product_wh ON stock_items ((tenant_id::text), product_id, warehouse_id);
CREATE INDEX CONCURRENTLY idx_customers_tenant_text_email ON customers ((tenant_id::text), email_blind_index);
CREATE INDEX CONCURRENTLY idx_variants_tenant_text_product ON catalog_product_variants ((tenant_id::text), configurable_product_id, is_active);
```

### Migration 2: Drop Redundant Indexes (after validation)

```sql
-- Category A: Old uuid-based tenant composites (replaced by expression indexes)
DROP INDEX CONCURRENTLY IF EXISTS idx_products_tenant_id;
DROP INDEX CONCURRENTLY IF EXISTS idx_products_tenant_active;
DROP INDEX CONCURRENTLY IF EXISTS idx_products_tenant_active_created;
DROP INDEX CONCURRENTLY IF EXISTS idx_categories_tenant_id;
DROP INDEX CONCURRENTLY IF EXISTS idx_categories_tenant_active;
DROP INDEX CONCURRENTLY IF EXISTS idx_orders_tenant_id;
DROP INDEX CONCURRENTLY IF EXISTS idx_orders_tenant_status_created;
DROP INDEX CONCURRENTLY IF EXISTS idx_fulfillments_tenant_id;
DROP INDEX CONCURRENTLY IF EXISTS idx_promotions_tenant_id;
DROP INDEX CONCURRENTLY IF EXISTS idx_promotions_tenant_active;
DROP INDEX CONCURRENTLY IF EXISTS idx_carts_tenant_id;
DROP INDEX CONCURRENTLY IF EXISTS idx_carts_tenant_customer;
DROP INDEX CONCURRENTLY IF EXISTS idx_carts_tenant_session;
DROP INDEX CONCURRENTLY IF EXISTS idx_price_lists_tenant;
DROP INDEX CONCURRENTLY IF EXISTS idx_price_lists_tenant_active;
DROP INDEX CONCURRENTLY IF EXISTS idx_stock_tenant_product;
DROP INDEX CONCURRENTLY IF EXISTS idx_stock_tenant_warehouse;
DROP INDEX CONCURRENTLY IF EXISTS idx_option_values_tenant;
DROP INDEX CONCURRENTLY IF EXISTS idx_product_variants_tenant;
DROP INDEX CONCURRENTLY IF EXISTS idx_product_variants_product_active;
DROP INDEX CONCURRENTLY IF EXISTS idx_reviews_tenant;

-- Category B: Single-column indexes subsumed by new composites
DROP INDEX CONCURRENTLY IF EXISTS idx_products_category_id;
DROP INDEX CONCURRENTLY IF EXISTS idx_products_price_amount;
DROP INDEX CONCURRENTLY IF EXISTS idx_products_created_at;
DROP INDEX CONCURRENTLY IF EXISTS idx_categories_parent_position;
DROP INDEX CONCURRENTLY IF EXISTS idx_categories_slug;
DROP INDEX CONCURRENTLY IF EXISTS idx_orders_created_at;
DROP INDEX CONCURRENTLY IF EXISTS idx_fulfillments_order_id;
DROP INDEX CONCURRENTLY IF EXISTS idx_promotions_is_active;
DROP INDEX CONCURRENTLY IF EXISTS idx_promotions_priority;
DROP INDEX CONCURRENTLY IF EXISTS idx_reviews_product;
DROP INDEX CONCURRENTLY IF EXISTS idx_carts_status;
DROP INDEX CONCURRENTLY IF EXISTS idx_carts_updated_at;
DROP INDEX CONCURRENTLY IF EXISTS idx_price_lists_active;
DROP INDEX CONCURRENTLY IF EXISTS idx_audit_log_tenant_id;
DROP INDEX CONCURRENTLY IF EXISTS idx_audit_log_occurred_at;
DROP INDEX CONCURRENTLY IF EXISTS idx_audit_log_resource_type;
DROP INDEX CONCURRENTLY IF EXISTS idx_audit_log_resource_id;
```

---

## 6. RLS Compatibility Note

All new composite indexes use `(tenant_id::text)` as the **leading expression**, exactly matching the RLS policy predicate:
```sql
(tenant_id)::text = current_setting('app.tenant_id'::text, true)
```

This is verified by EXPLAIN ANALYZE testing (Section 0). The planner recognizes expression indexes when the expression in the WHERE clause matches exactly.

### Future Improvement

The ideal solution is to change RLS policies from:
```sql
tenant_id::text = current_setting('app.tenant_id', true)
```
to:
```sql
tenant_id = current_setting('app.tenant_id', true)::uuid
```

This would allow standard B-tree indexes on `tenant_id` (uuid) to work, eliminating the need for expression indexes. This should be a separate task as it requires updating ~30+ RLS policies and thorough regression testing.

### Tables with varchar tenant_id (no cast needed)

The following tables use `varchar` for `tenant_id`, not `uuid`. The `::text` cast is a no-op for varchar, so standard indexes work. However, expression indexes are still compatible:
- `audit_log` (tenant_id varchar) — IDX-13, IDX-14 use standard composite
- `flash_sales` (tenant_id varchar) — existing `idx_flash_sales_tenant_status` works
- `fulfillments` (tenant_id uuid) — needs expression index
- `notifications` (tenant_id uuid) — existing `idx_notifications_tenant_status` needs expression version
