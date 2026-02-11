-- Database Audit Script for E-Commerce Platform
-- PRD v5.2 Compliance Check

-- 1. List all tables
\echo '===== ALL TABLES ====='
\dt

-- 2. Tables with tenant_id column
\echo ''
\echo '===== TABLES WITH TENANT_ID ====='
SELECT table_name, column_name, data_type
FROM information_schema.columns
WHERE column_name = 'tenant_id' AND table_schema = 'public'
ORDER BY table_name;

-- 3. RLS Status
\echo ''
\echo '===== RLS STATUS ON ALL TABLES ====='
SELECT schemaname, tablename, rowsecurity
FROM pg_tables
WHERE schemaname = 'public'
ORDER BY tablename;

-- 4. RLS Policies
\echo ''
\echo '===== RLS POLICIES ====='
SELECT tablename, policyname, permissive, roles, cmd
FROM pg_policies
ORDER BY tablename;

-- 5. Tables with tenant_id but WITHOUT RLS
\echo ''
\echo '===== CRITICAL: TABLES WITH TENANT_ID BUT NO RLS ====='
SELECT pt.tablename
FROM pg_tables pt
WHERE pt.schemaname = 'public'
  AND EXISTS (
    SELECT 1 FROM information_schema.columns
    WHERE table_name = pt.tablename
    AND column_name = 'tenant_id'
  )
  AND pt.rowsecurity = false
ORDER BY pt.tablename;

-- 6. Indexes (tenant-related)
\echo ''
\echo '===== TENANT-RELATED INDEXES ====='
SELECT schemaname, tablename, indexname, indexdef
FROM pg_indexes
WHERE schemaname = 'public'
AND (indexname LIKE '%tenant%' OR indexdef LIKE '%tenant_id%')
ORDER BY tablename, indexname;

-- 7. All indexes summary
\echo ''
\echo '===== INDEX COVERAGE SUMMARY ====='
SELECT
    schemaname,
    tablename,
    COUNT(*) as index_count
FROM pg_indexes
WHERE schemaname = 'public'
GROUP BY schemaname, tablename
ORDER BY index_count DESC, tablename;

-- 8. Table sizes
\echo ''
\echo '===== TOP 10 TABLES BY SIZE ====='
SELECT
    relname AS table_name,
    pg_size_pretty(pg_total_relation_size(relid)) AS total_size,
    pg_size_pretty(pg_relation_size(relid)) AS data_size,
    pg_size_pretty(pg_indexes_size(relid)) AS index_size,
    n_live_tup AS row_count
FROM pg_catalog.pg_statio_user_tables
JOIN pg_stat_user_tables USING (relname)
ORDER BY pg_total_relation_size(relid) DESC
LIMIT 10;

-- 9. Primary keys check
\echo ''
\echo '===== PRIMARY KEY TYPES ====='
SELECT
    tc.table_name,
    kcu.column_name,
    c.data_type
FROM information_schema.table_constraints tc
JOIN information_schema.key_column_usage kcu
    ON tc.constraint_name = kcu.constraint_name
JOIN information_schema.columns c
    ON c.table_name = tc.table_name AND c.column_name = kcu.column_name
WHERE tc.constraint_type = 'PRIMARY KEY'
AND tc.table_schema = 'public'
ORDER BY tc.table_name;

-- 10. JSONB columns (for flexible data)
\echo ''
\echo '===== JSONB COLUMNS ====='
SELECT table_name, column_name, data_type
FROM information_schema.columns
WHERE table_schema = 'public'
AND data_type = 'jsonb'
ORDER BY table_name, column_name;

-- 11. Missing indexes on foreign keys
\echo ''
\echo '===== FOREIGN KEYS WITHOUT INDEXES (POTENTIAL PERFORMANCE ISSUE) ====='
SELECT DISTINCT
    tc.table_name,
    kcu.column_name
FROM information_schema.table_constraints tc
JOIN information_schema.key_column_usage kcu
    ON tc.constraint_name = kcu.constraint_name
WHERE tc.constraint_type = 'FOREIGN KEY'
AND tc.table_schema = 'public'
AND NOT EXISTS (
    SELECT 1
    FROM pg_indexes pi
    WHERE pi.tablename = tc.table_name
    AND pi.indexdef LIKE '%' || kcu.column_name || '%'
)
ORDER BY tc.table_name, kcu.column_name;

-- 12. Translatable entities (ext_translations usage)
\echo ''
\echo '===== TRANSLATABLE ENTITIES (ext_translations) ====='
SELECT
    object_class,
    field,
    COUNT(DISTINCT locale) as locale_count,
    COUNT(*) as translation_count
FROM ext_translations
GROUP BY object_class, field
ORDER BY object_class, field;

-- 13. Database connection stats
\echo ''
\echo '===== DATABASE CONNECTION STATISTICS ====='
SELECT
    datname,
    numbackends as active_connections,
    xact_commit as transactions_committed,
    xact_rollback as transactions_rolled_back,
    blks_read as disk_blocks_read,
    blks_hit as buffer_cache_hits,
    ROUND(100.0 * blks_hit / NULLIF(blks_hit + blks_read, 0), 2) as cache_hit_ratio
FROM pg_stat_database
WHERE datname = current_database();

-- 14. Table bloat check (approximate)
\echo ''
\echo '===== TABLE BLOAT CHECK ====='
SELECT
    schemaname,
    tablename,
    pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) AS size,
    n_dead_tup,
    ROUND(100 * n_dead_tup / NULLIF(n_live_tup + n_dead_tup, 0), 2) AS dead_tuple_percent
FROM pg_stat_user_tables
WHERE n_dead_tup > 0
ORDER BY n_dead_tup DESC
LIMIT 10;
