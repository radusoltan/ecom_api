-- Database Performance Benchmark Queries v2 (Actual Schema)
-- Matches the real database schema with catalog_products, catalog_categories, etc.

\timing on
\echo '=== Database Performance Benchmark (Actual Schema) ==='
\echo ''

\set tenant_id '9d8e4f3a-1234-5678-90ab-cdef12345678'
\set customer_email 'customer@example.com'

\echo '=== 1. Product Listing with Pagination ==='
\echo 'Expected: < 50ms, uses composite index (tenant_id, active, created_at)'
\echo ''

EXPLAIN (ANALYZE, BUFFERS, FORMAT TEXT)
SELECT id, sku, name, price_amount, price_currency, active
FROM catalog_products
WHERE tenant_id = :'tenant_id'
AND active = true
ORDER BY created_at DESC
LIMIT 20 OFFSET 0;

\echo ''
\echo '=== 2. Product by ID (Single Row) ==='
\echo 'Expected: < 10ms, uses primary key index'
\echo ''

EXPLAIN (ANALYZE, BUFFERS, FORMAT TEXT)
SELECT * FROM catalog_products
WHERE id = (SELECT id FROM catalog_products WHERE tenant_id = :'tenant_id' LIMIT 1);

\echo ''
\echo '=== 3. Product by SKU ==='
\echo 'Expected: < 10ms, uses composite index (tenant_id, sku)'
\echo ''

EXPLAIN (ANALYZE, BUFFERS, FORMAT TEXT)
SELECT id, sku, name, price_amount
FROM catalog_products
WHERE tenant_id = :'tenant_id'
AND sku LIKE 'PROD-%'
LIMIT 10;

\echo ''
\echo '=== 4. Products by Price Range ==='
\echo 'Expected: < 50ms, uses price index'
\echo ''

EXPLAIN (ANALYZE, BUFFERS, FORMAT TEXT)
SELECT id, sku, name, price_amount, price_currency
FROM catalog_products
WHERE tenant_id = :'tenant_id'
AND active = true
AND price_amount BETWEEN 10000 AND 50000
ORDER BY price_amount ASC
LIMIT 20;

\echo ''
\echo '=== 5. Order History for Customer ==='
\echo 'Expected: < 50ms, uses composite index (tenant_id, customer_email, created_at)'
\echo ''

EXPLAIN (ANALYZE, BUFFERS, FORMAT TEXT)
SELECT id, customer_email, status, created_at
FROM orders
WHERE tenant_id = :'tenant_id'
AND customer_email = :'customer_email'
ORDER BY created_at DESC
LIMIT 10;

\echo ''
\echo '=== 6. Active Categories (Hierarchical) ==='
\echo 'Expected: < 50ms, uses composite index (tenant_id, active, position)'
\echo ''

EXPLAIN (ANALYZE, BUFFERS, FORMAT TEXT)
SELECT id, slug, name, parent_id, position
FROM catalog_categories
WHERE tenant_id = :'tenant_id'
AND active = true
ORDER BY position ASC
LIMIT 50;

\echo ''
\echo '=== 7. Category Tree (Recursive) ==='
\echo 'Expected: < 100ms, recursive CTE with proper indexes'
\echo ''

EXPLAIN (ANALYZE, BUFFERS, FORMAT TEXT)
WITH RECURSIVE category_tree AS (
    -- Base case: root categories
    SELECT id, slug, name, parent_id, position, 1 as depth
    FROM catalog_categories
    WHERE tenant_id = :'tenant_id'
    AND parent_id IS NULL
    AND active = true

    UNION ALL

    -- Recursive case: child categories
    SELECT c.id, c.slug, c.name, c.parent_id, c.position, ct.depth + 1
    FROM catalog_categories c
    INNER JOIN category_tree ct ON c.parent_id = ct.id
    WHERE c.tenant_id = :'tenant_id'
    AND c.active = true
    AND ct.depth < 5
)
SELECT * FROM category_tree
ORDER BY depth, position;

\echo ''
\echo '=== 8. Active Warehouses ==='
\echo 'Expected: < 20ms, uses composite index (tenant_id, is_active)'
\echo ''

EXPLAIN (ANALYZE, BUFFERS, FORMAT TEXT)
SELECT id, name, code, address, priority
FROM warehouses
WHERE tenant_id = :'tenant_id'
AND is_active = true
ORDER BY priority DESC;

\echo ''
\echo '=== 9. Active Price Lists ==='
\echo 'Expected: < 30ms, uses composite index (tenant_id, is_active)'
\echo ''

EXPLAIN (ANALYZE, BUFFERS, FORMAT TEXT)
SELECT id, name, is_active, created_at
FROM price_lists
WHERE tenant_id = :'tenant_id'
AND is_active = true
ORDER BY created_at DESC;

\echo ''
\echo '=== 10. Recent Orders by Status ==='
\echo 'Expected: < 50ms, uses composite index (tenant_id, status, created_at)'
\echo ''

EXPLAIN (ANALYZE, BUFFERS, FORMAT TEXT)
SELECT id, customer_email, status, created_at
FROM orders
WHERE tenant_id = :'tenant_id'
AND status IN ('pending', 'processing', 'confirmed')
ORDER BY created_at DESC
LIMIT 50;

\echo ''
\echo '=== 11. Count Products by Status ==='
\echo 'Expected: < 50ms, uses index for WHERE clause'
\echo ''

EXPLAIN (ANALYZE, BUFFERS, FORMAT TEXT)
SELECT active, COUNT(*) as count
FROM catalog_products
WHERE tenant_id = :'tenant_id'
GROUP BY active;

\echo ''
\echo '=== 12. Products in Category ==='
\echo 'Expected: < 50ms, uses category_id index'
\echo ''

EXPLAIN (ANALYZE, BUFFERS, FORMAT TEXT)
SELECT p.id, p.sku, p.name, p.price_amount
FROM catalog_products p
WHERE p.tenant_id = :'tenant_id'
AND p.active = true
AND p.category_id IN (
    SELECT id FROM catalog_categories WHERE tenant_id = :'tenant_id' AND active = true LIMIT 5
)
ORDER BY p.created_at DESC
LIMIT 20;

\echo ''
\echo '=== Index Usage Summary ==='
\echo ''

SELECT
    schemaname,
    relname as tablename,
    indexrelname as indexname,
    idx_scan as index_scans,
    idx_tup_read as tuples_read,
    idx_tup_fetch as tuples_fetched
FROM pg_stat_user_indexes
WHERE schemaname = 'public'
AND relname IN ('catalog_products', 'catalog_categories', 'orders', 'warehouses', 'price_lists')
ORDER BY relname, idx_scan DESC;

\echo ''
\echo '=== Table Statistics ==='
\echo ''

SELECT
    schemaname,
    relname as tablename,
    n_live_tup as row_count,
    pg_size_pretty(pg_total_relation_size(schemaname||'.'||relname)) as total_size,
    pg_size_pretty(pg_relation_size(schemaname||'.'||relname)) as table_size,
    pg_size_pretty(pg_indexes_size(schemaname||'.'||relname)) as indexes_size
FROM pg_stat_user_tables
WHERE schemaname = 'public'
AND relname IN ('catalog_products', 'catalog_categories', 'orders', 'warehouses', 'price_lists', 'tenants')
ORDER BY pg_total_relation_size(schemaname||'.'||relname) DESC;

\echo ''
\echo '=== Sequential Scans (Should be minimal) ==='
\echo ''

SELECT
    schemaname,
    relname as tablename,
    seq_scan,
    seq_tup_read,
    idx_scan,
    CASE
        WHEN seq_scan + idx_scan = 0 THEN 0
        ELSE ROUND((seq_scan::numeric / (seq_scan + idx_scan)) * 100, 2)
    END as seq_scan_pct
FROM pg_stat_user_tables
WHERE schemaname = 'public'
AND relname IN ('catalog_products', 'catalog_categories', 'orders', 'warehouses', 'price_lists')
ORDER BY seq_scan DESC;

\echo ''
\echo '=== Cache Hit Ratio (Should be > 99%) ==='
\echo ''

SELECT
    ROUND(
        100.0 * sum(blks_hit) / NULLIF(sum(blks_hit) + sum(blks_read), 0),
        2
    ) as cache_hit_ratio_pct
FROM pg_stat_database
WHERE datname = current_database();

\echo ''
\echo '=== Benchmark Complete ==='
\echo ''
\echo 'Summary:'
\echo '  ✓ All queries should execute in < 100ms'
\echo '  ✓ Index Scan should be used (not Seq Scan)'
\echo '  ✓ Cache hit ratio should be > 99%'
\echo '  ✓ Buffers: "shared hit" should be high'
\echo ''
