-- Database Performance Benchmark Queries
-- Run with EXPLAIN ANALYZE to measure query performance
--
-- Target Performance (PRD Section 9.1):
-- - Database query time < 50ms (p95)
-- - Search query time < 100ms (p95)
-- - Proper index usage
-- - Minimal sequential scans
--
-- Usage:
--   psql -h localhost -U ecom_admin -d ecom -f tests/Performance/database/benchmark_queries.sql

\timing on
\echo '=== Database Performance Benchmark ==='
\echo ''

-- Setup: Use a real tenant ID from the database
\set tenant_id '9d8e4f3a-1234-5678-90ab-cdef12345678'
\set customer_email 'customer@example.com'

\echo '=== 1. Product Search (Most Critical) ==='
\echo 'Expected: < 100ms, uses GIN index on name_translations'
\echo ''

EXPLAIN (ANALYZE, BUFFERS, FORMAT TEXT)
SELECT id, sku, name_translations, price_amount, price_currency, status
FROM products
WHERE tenant_id = :'tenant_id'
AND status = 'active'
AND name_translations @> '{"en_US": "laptop"}'::jsonb
ORDER BY created_at DESC
LIMIT 20;

\echo ''
\echo '=== 2. Product Listing with Pagination ==='
\echo 'Expected: < 50ms, uses composite index (tenant_id, status, created_at)'
\echo ''

EXPLAIN (ANALYZE, BUFFERS, FORMAT TEXT)
SELECT id, sku, name_translations, price_amount, price_currency, status
FROM products
WHERE tenant_id = :'tenant_id'
AND status = 'active'
ORDER BY created_at DESC
LIMIT 20 OFFSET 0;

\echo ''
\echo '=== 3. Product by ID (Single Row) ==='
\echo 'Expected: < 10ms, uses primary key index'
\echo ''

-- Note: Replace with actual product ID from your database
EXPLAIN (ANALYZE, BUFFERS, FORMAT TEXT)
SELECT * FROM products
WHERE id = (SELECT id FROM products WHERE tenant_id = :'tenant_id' LIMIT 1);

\echo ''
\echo '=== 4. Order History for Customer ==='
\echo 'Expected: < 50ms, uses composite index (tenant_id, customer_email, created_at)'
\echo ''

EXPLAIN (ANALYZE, BUFFERS, FORMAT TEXT)
SELECT id, order_number, status, total_amount, total_currency, created_at
FROM orders
WHERE tenant_id = :'tenant_id'
AND customer_email = :'customer_email'
ORDER BY created_at DESC
LIMIT 10;

\echo ''
\echo '=== 5. Active Categories (Hierarchical) ==='
\echo 'Expected: < 50ms, uses composite index (tenant_id, is_active, position)'
\echo ''

EXPLAIN (ANALYZE, BUFFERS, FORMAT TEXT)
SELECT id, slug, name_translations, parent_id, position
FROM categories
WHERE tenant_id = :'tenant_id'
AND is_active = true
ORDER BY position ASC
LIMIT 50;

\echo ''
\echo '=== 6. Category Tree (Recursive) ==='
\echo 'Expected: < 100ms, recursive CTE with proper indexes'
\echo ''

EXPLAIN (ANALYZE, BUFFERS, FORMAT TEXT)
WITH RECURSIVE category_tree AS (
    -- Base case: root categories
    SELECT id, slug, name_translations, parent_id, position, 1 as depth
    FROM categories
    WHERE tenant_id = :'tenant_id'
    AND parent_id IS NULL
    AND is_active = true

    UNION ALL

    -- Recursive case: child categories
    SELECT c.id, c.slug, c.name_translations, c.parent_id, c.position, ct.depth + 1
    FROM categories c
    INNER JOIN category_tree ct ON c.parent_id = ct.id
    WHERE c.tenant_id = :'tenant_id'
    AND c.is_active = true
    AND ct.depth < 5  -- Prevent infinite recursion
)
SELECT * FROM category_tree
ORDER BY depth, position;

\echo ''
\echo '=== 7. Translation Lookup ==='
\echo 'Expected: < 20ms, uses composite index (tenant_id, domain, locale)'
\echo ''

EXPLAIN (ANALYZE, BUFFERS, FORMAT TEXT)
SELECT domain, key, value
FROM translations
WHERE tenant_id = :'tenant_id'
AND domain = 'messages'
AND locale = 'en_US'
LIMIT 100;

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
SELECT id, name, code, is_active, created_at
FROM price_lists
WHERE tenant_id = :'tenant_id'
AND is_active = true
ORDER BY created_at DESC;

\echo ''
\echo '=== 10. Recent Orders by Status ==='
\echo 'Expected: < 50ms, uses composite index (tenant_id, status, created_at)'
\echo ''

EXPLAIN (ANALYZE, BUFFERS, FORMAT TEXT)
SELECT id, order_number, status, customer_email, total_amount, created_at
FROM orders
WHERE tenant_id = :'tenant_id'
AND status IN ('pending', 'processing', 'confirmed')
ORDER BY created_at DESC
LIMIT 50;

\echo ''
\echo '=== 11. Product Search by SKU ==='
\echo 'Expected: < 10ms, uses composite index (tenant_id, sku)'
\echo ''

EXPLAIN (ANALYZE, BUFFERS, FORMAT TEXT)
SELECT id, sku, name_translations, price_amount, price_currency
FROM products
WHERE tenant_id = :'tenant_id'
AND sku LIKE 'PROD-%'
LIMIT 10;

\echo ''
\echo '=== 12. Count Products by Status ==='
\echo 'Expected: < 50ms, uses index for WHERE clause'
\echo ''

EXPLAIN (ANALYZE, BUFFERS, FORMAT TEXT)
SELECT status, COUNT(*) as count
FROM products
WHERE tenant_id = :'tenant_id'
GROUP BY status;

\echo ''
\echo '=== Index Usage Summary ==='
\echo 'Check which indexes are being used'
\echo ''

-- Show index usage statistics
SELECT
    schemaname,
    tablename,
    indexname,
    idx_scan as index_scans,
    idx_tup_read as tuples_read,
    idx_tup_fetch as tuples_fetched
FROM pg_stat_user_indexes
WHERE schemaname = 'public'
AND tablename IN ('products', 'orders', 'categories', 'translations', 'warehouses', 'price_lists')
ORDER BY tablename, idx_scan DESC;

\echo ''
\echo '=== Table Statistics ==='
\echo 'Current row counts and sizes'
\echo ''

SELECT
    schemaname,
    tablename,
    n_live_tup as row_count,
    pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) as total_size,
    pg_size_pretty(pg_relation_size(schemaname||'.'||tablename)) as table_size,
    pg_size_pretty(pg_indexes_size(schemaname||'.'||tablename)) as indexes_size
FROM pg_stat_user_tables
WHERE schemaname = 'public'
AND tablename IN ('products', 'orders', 'categories', 'translations', 'warehouses', 'price_lists', 'tenants')
ORDER BY pg_total_relation_size(schemaname||'.'||tablename) DESC;

\echo ''
\echo '=== Sequential Scans (Should be minimal) ==='
\echo 'Tables with high seq_scan indicate missing indexes'
\echo ''

SELECT
    schemaname,
    tablename,
    seq_scan,
    seq_tup_read,
    idx_scan,
    CASE
        WHEN seq_scan + idx_scan = 0 THEN 0
        ELSE ROUND((seq_scan::numeric / (seq_scan + idx_scan)) * 100, 2)
    END as seq_scan_pct
FROM pg_stat_user_tables
WHERE schemaname = 'public'
AND tablename IN ('products', 'orders', 'categories', 'translations', 'warehouses', 'price_lists')
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
\echo 'Review the EXPLAIN ANALYZE output above:'
\echo '  - Execution Time should be < 100ms for all queries'
\echo '  - Index Scan should be used (not Seq Scan)'
\echo '  - Buffers: shared hit should be high (cached)'
\echo '  - Planning Time should be < 1ms'
\echo ''
