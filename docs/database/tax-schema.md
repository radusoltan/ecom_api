# Tax Database Schema

**Migration**: `Version20251128120000_CreateTaxTables.php`
**Created**: 2025-11-28
**Status**: Ready for deployment

## Overview

This migration creates the database schema for the Tax bounded context, implementing multi-tenant tax rule management and VAT validation caching.

## Tables

### 1. tax_rules

Stores tax rates per jurisdiction (country + region) and product category.

**Columns:**

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | UUID | PRIMARY KEY | Unique identifier |
| tenant_id | UUID | NOT NULL, FK → tenants(id) | Multi-tenant isolation |
| country_code | VARCHAR(2) | NOT NULL, CHECK | ISO 3166-1 alpha-2 (e.g., US, FR, DE) |
| region_code | VARCHAR(10) | NULL | Optional region/state code (e.g., CA, NY) |
| category | VARCHAR(20) | NOT NULL, CHECK | Tax category (see below) |
| rate | NUMERIC(5,2) | NOT NULL, CHECK (0-100) | Tax rate percentage (0.00-100.00) |
| name | VARCHAR(100) | NOT NULL | Human-readable rule name |
| description | TEXT | NULL | Optional detailed description |
| is_active | BOOLEAN | NOT NULL, DEFAULT true | Soft delete flag |
| valid_from | TIMESTAMPTZ | NULL | Rule validity start date |
| valid_until | TIMESTAMPTZ | NULL | Rule validity end date |
| priority | INTEGER | NOT NULL, DEFAULT 0 | Higher priority = precedence in conflicts |
| created_at | TIMESTAMPTZ | NOT NULL, DEFAULT NOW() | Creation timestamp |
| updated_at | TIMESTAMPTZ | NOT NULL, DEFAULT NOW() | Last update timestamp |

**Tax Categories:**
- `standard` - Standard VAT rate (e.g., 19% DE, 20% FR, 20% UK)
- `reduced` - Reduced VAT rate (e.g., 7% DE, 5.5% FR for food)
- `zero` - 0% VAT rate (e.g., UK zero-rated goods)
- `exempt` - VAT exempt (e.g., financial services)
- `reverse_charge` - B2B reverse charge mechanism (buyer pays VAT)

**Constraints:**

| Constraint | Type | Description |
|------------|------|-------------|
| `uq_tax_rules_jurisdiction_category` | UNIQUE | Only one active rule per (tenant_id, country_code, region_code, category) |
| `chk_tax_rules_rate_valid` | CHECK | Rate between 0.00 and 100.00 |
| `chk_tax_rules_category_valid` | CHECK | Category in allowed values |
| `chk_tax_rules_country_code_valid` | CHECK | Country code matches regex `^[A-Z]{2}$` |
| `chk_tax_rules_validity_period` | CHECK | valid_from < valid_until |

**Indexes:**

| Index Name | Columns | Type | Purpose |
|------------|---------|------|---------|
| `idx_tax_rules_tenant` | (tenant_id) | B-tree | RLS performance (CRITICAL) |
| `idx_tax_rules_jurisdiction_lookup` | (tenant_id, country_code, region_code, category, is_active) | Partial | Tax calculation lookup |
| `idx_tax_rules_country` | (country_code) | B-tree | Country-based queries |
| `idx_tax_rules_category` | (category) | B-tree | Category filtering |
| `idx_tax_rules_active` | (is_active) | Partial | Active rules only |
| `idx_tax_rules_validity` | (valid_from, valid_until) | Partial | Validity period queries |

**Row-Level Security:**

```sql
ALTER TABLE tax_rules ENABLE ROW LEVEL SECURITY;
ALTER TABLE tax_rules FORCE ROW LEVEL SECURITY;

CREATE POLICY tax_rules_tenant_isolation ON tax_rules
FOR ALL
USING (tenant_id::text = current_setting('app.tenant_id', true))
WITH CHECK (tenant_id::text = current_setting('app.tenant_id', true));
```

### 2. vat_validations

Caches VAT validation results from EU VIES (VAT Information Exchange System).

**Columns:**

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | UUID | PRIMARY KEY | Unique identifier |
| tenant_id | UUID | NOT NULL, FK → tenants(id) | Multi-tenant isolation |
| vat_number | VARCHAR(20) | NOT NULL | VAT number validated (e.g., DE123456789) |
| country_code | VARCHAR(2) | NOT NULL, CHECK | ISO 3166-1 alpha-2 |
| is_valid | BOOLEAN | NOT NULL | Validation result from VIES |
| business_name | VARCHAR(255) | NULL | Company name from VIES response |
| business_address | TEXT | NULL | Company address from VIES response |
| request_id | VARCHAR(100) | NULL | VIES request identifier |
| error_message | TEXT | NULL | Error details if validation failed |
| validated_at | TIMESTAMPTZ | NOT NULL | Validation timestamp |
| expires_at | TIMESTAMPTZ | NOT NULL | Cache expiry (typically +24h) |
| created_at | TIMESTAMPTZ | NOT NULL, DEFAULT NOW() | Creation timestamp |

**Constraints:**

| Constraint | Type | Description |
|------------|------|-------------|
| `chk_vat_validations_country_code_valid` | CHECK | Country code matches regex `^[A-Z]{2}$` |
| `chk_vat_validations_expiry_valid` | CHECK | expires_at > validated_at |

**Indexes:**

| Index Name | Columns | Type | Purpose |
|------------|---------|------|---------|
| `idx_vat_validations_tenant` | (tenant_id) | B-tree | RLS performance (CRITICAL) |
| `idx_vat_validations_lookup` | (tenant_id, vat_number, expires_at) | B-tree | Cache hit lookup |
| `idx_vat_validations_number` | (vat_number) | B-tree | VAT number queries |
| `idx_vat_validations_expires` | (expires_at) | B-tree | Cleanup expired entries |

**Row-Level Security:**

```sql
ALTER TABLE vat_validations ENABLE ROW LEVEL SECURITY;
ALTER TABLE vat_validations FORCE ROW LEVEL SECURITY;

CREATE POLICY vat_validations_tenant_isolation ON vat_validations
FOR ALL
USING (tenant_id::text = current_setting('app.tenant_id', true))
WITH CHECK (tenant_id::text = current_setting('app.tenant_id', true));
```

## Example Data

### Tax Rules Examples

```sql
-- German Standard VAT (19%)
INSERT INTO tax_rules (tenant_id, country_code, category, rate, name, is_active)
VALUES ('tenant-uuid', 'DE', 'standard', 19.00, 'German Standard VAT', true);

-- German Reduced VAT (7%)
INSERT INTO tax_rules (tenant_id, country_code, category, rate, name, is_active)
VALUES ('tenant-uuid', 'DE', 'reduced', 7.00, 'German Reduced VAT', true);

-- French Standard VAT (20%)
INSERT INTO tax_rules (tenant_id, country_code, category, rate, name, is_active)
VALUES ('tenant-uuid', 'FR', 'standard', 20.00, 'French Standard VAT', true);

-- US California Sales Tax (7.25%)
INSERT INTO tax_rules (tenant_id, country_code, region_code, category, rate, name, is_active)
VALUES ('tenant-uuid', 'US', 'CA', 'standard', 7.25, 'California Sales Tax', true);

-- B2B Reverse Charge (0%)
INSERT INTO tax_rules (tenant_id, country_code, category, rate, name, is_active)
VALUES ('tenant-uuid', 'DE', 'reverse_charge', 0.00, 'B2B Reverse Charge', true);
```

### VAT Validation Cache Example

```sql
-- Valid German VAT number
INSERT INTO vat_validations (
    tenant_id, vat_number, country_code, is_valid,
    business_name, validated_at, expires_at
)
VALUES (
    'tenant-uuid', 'DE123456789', 'DE', true,
    'ACME GmbH', NOW(), NOW() + INTERVAL '24 hours'
);

-- Invalid French VAT number
INSERT INTO vat_validations (
    tenant_id, vat_number, country_code, is_valid,
    error_message, validated_at, expires_at
)
VALUES (
    'tenant-uuid', 'FR98765432109', 'FR', false,
    'Invalid VAT number format', NOW(), NOW() + INTERVAL '24 hours'
);
```

## Query Performance

### Tax Calculation Lookup (Most Frequent)

```sql
-- Find applicable tax rate for a product in a jurisdiction
SELECT rate, name, priority
FROM tax_rules
WHERE tenant_id = :tenant_id
  AND country_code = :country_code
  AND (region_code = :region_code OR region_code IS NULL)
  AND category = :product_category
  AND is_active = true
  AND (valid_from IS NULL OR valid_from <= NOW())
  AND (valid_until IS NULL OR valid_until >= NOW())
ORDER BY
  region_code NULLS LAST,  -- Region-specific rules first
  priority DESC,            -- Higher priority first
  valid_from DESC           -- Newer rules first
LIMIT 1;

-- Uses index: idx_tax_rules_jurisdiction_lookup
-- Expected performance: <5ms
```

### VAT Validation Cache Lookup

```sql
-- Check if VAT validation exists and is not expired
SELECT is_valid, business_name, business_address
FROM vat_validations
WHERE tenant_id = :tenant_id
  AND vat_number = :vat_number
  AND expires_at > NOW()
ORDER BY validated_at DESC
LIMIT 1;

-- Uses index: idx_vat_validations_lookup
-- Expected performance: <2ms
```

### Cleanup Expired VAT Validations (Background Job)

```sql
-- Delete expired validations (run daily)
DELETE FROM vat_validations
WHERE expires_at < NOW() - INTERVAL '7 days';

-- Uses index: idx_vat_validations_expires
-- Expected performance: <100ms for 10k rows
```

## Business Rules Enforced at Database Level

1. **Unique Active Rule per Jurisdiction + Category**
   - Only one active tax rule allowed per (tenant_id, country_code, region_code, category)
   - Prevents ambiguous tax calculations
   - Enforced by: `uq_tax_rules_jurisdiction_category` constraint

2. **Valid Tax Rate Range**
   - Rate must be between 0.00% and 100.00%
   - Enforced by: `chk_tax_rules_rate_valid` constraint

3. **Valid Tax Categories**
   - Only allowed values: standard, reduced, zero, exempt, reverse_charge
   - Enforced by: `chk_tax_rules_category_valid` constraint

4. **ISO Country Codes**
   - Country codes must be 2 uppercase letters
   - Enforced by: `chk_tax_rules_country_code_valid` and `chk_vat_validations_country_code_valid` constraints

5. **Valid Validity Periods**
   - If both valid_from and valid_until are set, valid_from must be earlier
   - Enforced by: `chk_tax_rules_validity_period` constraint

6. **Cache Expiry Logic**
   - expires_at must be after validated_at
   - Enforced by: `chk_vat_validations_expiry_valid` constraint

## Multi-Tenancy Security

Both tables are protected by **PostgreSQL Row-Level Security (RLS)**:

- RLS policies enforce `tenant_id = current_setting('app.tenant_id')`
- Complete tenant isolation at database level
- Defense-in-depth against SQL injection
- Required for GDPR, SOC 2, ISO 27001 compliance

**Setting tenant context:**

```php
// In Symfony application
$this->connection->executeStatement(
    "SELECT set_config('app.tenant_id', :tenant_id, false)",
    ['tenant_id' => $tenantId->toString()]
);
```

## Deployment Checklist

- [ ] Review migration file: `migrations/Version20251128120000_CreateTaxTables.php`
- [ ] Test on development database: `APP_ENV=dev symfony console doctrine:migrations:migrate`
- [ ] Verify RLS policies: Check `pg_policies` table
- [ ] Verify indexes: Check `pg_indexes` table
- [ ] Test tenant isolation: Insert/query with different tenant contexts
- [ ] Performance test: Tax calculation queries under load
- [ ] Backup production database before deployment
- [ ] Run on staging: `APP_ENV=staging symfony console doctrine:migrations:migrate`
- [ ] Run on production: `APP_ENV=prod symfony console doctrine:migrations:migrate`
- [ ] Verify no downtime during migration (indexes created with `CONCURRENTLY`)
- [ ] Monitor slow query log for first 24h after deployment

## Rollback Plan

If issues are detected after deployment:

```bash
# Rollback migration
symfony console doctrine:migrations:migrate prev --no-interaction

# Or manual rollback
PGPASSWORD=sr324395 psql -h 127.0.0.1 -U ecom_admin -d ecom
DROP POLICY IF EXISTS vat_validations_tenant_isolation ON vat_validations;
DROP POLICY IF EXISTS tax_rules_tenant_isolation ON tax_rules;
DROP TABLE IF EXISTS vat_validations;
DROP TABLE IF EXISTS tax_rules;
```

## Monitoring Queries

### Check RLS Status

```sql
SELECT tablename, rowsecurity
FROM pg_tables
WHERE schemaname = 'public'
  AND tablename IN ('tax_rules', 'vat_validations');

-- Both should return rowsecurity = true
```

### Check Index Usage

```sql
SELECT
    schemaname,
    tablename,
    indexname,
    idx_scan AS scans,
    idx_tup_read AS tuples_read,
    idx_tup_fetch AS tuples_fetched
FROM pg_stat_user_indexes
WHERE tablename IN ('tax_rules', 'vat_validations')
ORDER BY idx_scan DESC;
```

### Check Table Statistics

```sql
SELECT
    relname AS table_name,
    n_live_tup AS live_rows,
    n_dead_tup AS dead_rows,
    last_vacuum,
    last_autovacuum,
    last_analyze
FROM pg_stat_user_tables
WHERE relname IN ('tax_rules', 'vat_validations');
```

## Related Documentation

- **Business Requirements**: `docs/business/ECOM_PRD_v5.1.md` - Section 6.1 Tax Management
- **Domain Model**: `src/Tax/Domain/Model/TaxRule.php` (to be created)
- **Entity Mapping**: `src/Tax/Infrastructure/Persistence/Doctrine/Entity/TaxRuleEntity.php` (to be created)
- **VAT Service**: `src/Tax/Domain/Service/VatValidationService.php` (to be created)
- **Multi-tenancy Guide**: `docs/guides/multi-tenancy.md`

---

**Version**: 1.0
**Last Updated**: 2025-11-28
**Maintainer**: Database Team
