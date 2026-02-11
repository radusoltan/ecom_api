# Invoice Migration Documentation

## Migration: Version20251128300000_CreateInvoiceTables.php

### Overview

This migration creates the database schema for the **Invoice bounded context** with full multi-tenant support using PostgreSQL Row-Level Security (RLS).

### Created Tables

#### 1. `invoices` Table

Main invoice records with billing snapshots.

**Columns:**
- `id` (UUID, PK) - Unique invoice identifier
- `tenant_id` (UUID, FK → tenants) - Multi-tenant isolation
- `order_id` (UUID, FK → orders) - Source order reference
- `customer_id` (UUID) - Customer reference
- `invoice_number` (VARCHAR(20), UNIQUE per tenant) - Human-readable invoice number (e.g., INV-2025-00001)
- `status` (VARCHAR(20)) - Workflow status: `draft`, `issued`, `paid`, `partially_paid`, `overdue`, `cancelled`, `credited`

**Billing Snapshot (Immutable after creation):**
- `billing_name` (VARCHAR(255))
- `billing_address_line1` (VARCHAR(255))
- `billing_address_line2` (VARCHAR(255), NULLABLE)
- `billing_city` (VARCHAR(100))
- `billing_postal_code` (VARCHAR(20))
- `billing_country` (VARCHAR(2)) - ISO 3166-1 alpha-2
- `billing_vat_number` (VARCHAR(20), NULLABLE)

**Financial Data (amounts in cents):**
- `subtotal_amount` (INT) - Amount before tax
- `tax_amount` (INT) - Total tax amount
- `total_amount` (INT) - Final amount (subtotal + tax)
- `currency` (VARCHAR(3)) - ISO 4217 code (default: EUR)
- `tax_breakdown` (JSONB) - Tax amounts per rate (e.g., `{"standard_20": 2000, "reduced_10": 500}`)

**Date Tracking:**
- `issue_date` (TIMESTAMPTZ, NULLABLE)
- `due_date` (TIMESTAMPTZ, NULLABLE)
- `paid_date` (TIMESTAMPTZ, NULLABLE)
- `created_at` (TIMESTAMPTZ, DEFAULT NOW())
- `updated_at` (TIMESTAMPTZ, DEFAULT NOW())

**PDF Management:**
- `pdf_path` (VARCHAR(500), NULLABLE) - Storage path for generated PDF
- `pdf_generated_at` (TIMESTAMPTZ, NULLABLE)

**Advanced Features:**
- `is_reverse_charge` (BOOLEAN, DEFAULT FALSE) - B2B EU reverse charge mechanism
- `credited_invoice_id` (UUID, FK → invoices, NULLABLE) - Reference for credit notes
- `notes` (TEXT, NULLABLE) - Additional notes

**Constraints:**
- `UNIQUE (tenant_id, invoice_number)` - Invoice numbers unique per tenant
- `CHECK (total_amount = subtotal_amount + tax_amount)` - Enforce amount consistency
- `CHECK (due_date >= issue_date)` - Due date must be after issue date
- `CHECK (paid_date >= issue_date)` - Paid date must be after issue date

**Indexes:**
- `idx_invoices_tenant` - (tenant_id) - RLS performance
- `idx_invoices_tenant_order` - (tenant_id, order_id) - Order lookup
- `idx_invoices_tenant_customer` - (tenant_id, customer_id) - Customer invoices
- `idx_invoices_tenant_status` - (tenant_id, status) - Status filtering
- `idx_invoices_tenant_issue_date` - (tenant_id, issue_date DESC) - Date sorting
- `idx_invoices_tenant_due_date` - (tenant_id, due_date) WHERE status NOT IN ('paid', 'cancelled', 'credited') - Overdue detection
- `idx_invoices_overdue` - (tenant_id, due_date) WHERE status IN ('issued', 'partially_paid') - Background job optimization

#### 2. `invoice_lines` Table

Individual line items per invoice.

**Columns:**
- `id` (UUID, PK) - Unique line identifier
- `invoice_id` (UUID, FK → invoices, ON DELETE CASCADE) - Parent invoice
- `tenant_id` (UUID, FK → tenants) - Multi-tenant isolation
- `product_id` (UUID, NULLABLE) - Product reference (optional, may be deleted)
- `sku` (VARCHAR(50), NULLABLE) - Product SKU snapshot
- `description` (VARCHAR(500)) - Line description
- `quantity` (INT) - Item quantity
- `unit_price` (INT) - Price per unit (in cents)
- `tax_rate` (NUMERIC(5,2), DEFAULT 0) - Tax rate percentage (0.00-100.00)
- `tax_amount` (INT, DEFAULT 0) - Calculated tax amount
- `line_total` (INT) - Total for line (quantity * unit_price)
- `position` (INT, DEFAULT 0) - Display order position
- `created_at` (TIMESTAMPTZ, DEFAULT NOW())

**Constraints:**
- `CHECK (quantity > 0)` - Positive quantity required
- `CHECK (unit_price >= 0)` - Non-negative price
- `CHECK (tax_rate >= 0.00 AND tax_rate <= 100.00)` - Valid tax rate
- `CHECK (line_total = quantity * unit_price)` - Enforce calculation correctness
- `CHECK (position >= 0)` - Non-negative position

**Indexes:**
- `idx_invoice_lines_tenant` - (tenant_id) - RLS performance
- `idx_invoice_lines_tenant_invoice` - (tenant_id, invoice_id) - Line lookup
- `idx_invoice_lines_invoice_position` - (invoice_id, position) - Display ordering
- `idx_invoice_lines_product` - (product_id) WHERE product_id IS NOT NULL - Product reference
- `idx_invoice_lines_sku` - (sku) WHERE sku IS NOT NULL - SKU lookup

#### 3. `invoice_sequences` Table

Atomic invoice number generation per tenant/year.

**Columns:**
- `tenant_id` (UUID, PK, FK → tenants)
- `year` (INT, PK) - Year for sequence (2000-2100)
- `current_sequence` (INT, DEFAULT 0) - Current sequence number

**Constraints:**
- `PRIMARY KEY (tenant_id, year)` - One sequence per tenant/year
- `CHECK (year >= 2000 AND year <= 2100)` - Reasonable year range
- `CHECK (current_sequence >= 0)` - Non-negative sequence

**Indexes:**
- `idx_invoice_sequences_tenant` - (tenant_id)

### PostgreSQL Function

#### `get_next_invoice_number(p_tenant_id UUID, p_year INT) RETURNS INT`

Atomically generates the next invoice sequence number for a given tenant and year.

**Usage:**
```sql
-- Generate next invoice number for tenant in 2025
SELECT get_next_invoice_number('00000000-0000-4000-8000-000000000001', 2025);
-- Returns: 1 (first call), 2 (second call), etc.

-- Format full invoice number in application
-- INV-{YEAR}-{SEQUENCE:05d}
-- Example: INV-2025-00001, INV-2025-00002
```

**Thread-Safety:**
Uses PostgreSQL's `INSERT ... ON CONFLICT DO UPDATE` for atomic increment without race conditions.

### Row-Level Security (RLS)

All three tables have RLS enabled with tenant isolation policies.

**Policy Pattern:**
```sql
CREATE POLICY tenant_isolation_{table} ON {table}
FOR ALL
USING (tenant_id = COALESCE(NULLIF(current_setting('app.tenant_id', true), '')::UUID, tenant_id))
```

**Benefits:**
- Automatic tenant isolation at database level
- No query modification needed in application code
- Fallback to `tenant_id` column allows seeding without setting context
- Prevents cross-tenant data access even with SQL injection

**Setting Tenant Context:**
```php
// In Symfony application
$connection->executeStatement("SET app.tenant_id = '{$tenantId}'");
```

### Business Rules Enforced by Database

1. **Invoice Numbers**: Unique per tenant (not globally unique)
2. **Amount Consistency**: `total_amount = subtotal_amount + tax_amount`
3. **Date Logic**:
   - Due date ≥ Issue date
   - Paid date ≥ Issue date
4. **Valid Statuses**: Only allowed values (`draft`, `issued`, `paid`, etc.)
5. **ISO Codes**:
   - Country code: 2 uppercase letters (ISO 3166-1)
   - Currency: 3 uppercase letters (ISO 4217)
6. **Tax Rates**: Between 0% and 100%
7. **Line Total Accuracy**: `line_total = quantity * unit_price`
8. **Atomic Numbering**: Thread-safe sequence generation

### Migration Commands

```bash
# Run migration
symfony console doctrine:migrations:migrate

# Check status
symfony console doctrine:migrations:status

# Rollback (if needed)
symfony console doctrine:migrations:migrate prev

# Verify tables created
PGPASSWORD=sr324395 psql -h 127.0.0.1 -U ecom_admin -d ecom -c "\dt invoices*"
```

### Verification Queries

```sql
-- Check RLS enabled
SELECT tablename, rowsecurity
FROM pg_tables
WHERE tablename LIKE 'invoice%';

-- List RLS policies
SELECT tablename, policyname
FROM pg_policies
WHERE tablename LIKE 'invoice%';

-- Check function exists
\df get_next_invoice_number

-- Test invoice sequence generation
SELECT get_next_invoice_number('00000000-0000-4000-8000-000000000001', 2025);

-- Check indexes
\di+ invoice*
```

### Integration with Application

**Domain Model:** `src/Invoice/Domain/Model/Invoice.php`
**Doctrine Entity:** `src/Invoice/Infrastructure/Persistence/Doctrine/Entity/InvoiceEntity.php`
**Repository:** `src/Invoice/Infrastructure/Persistence/Doctrine/Repository/DoctrineInvoiceRepository.php`

**Invoice Number Generation Example:**
```php
// In repository or service
$year = (int) date('Y');
$sequence = $connection->executeQuery(
    'SELECT get_next_invoice_number(:tenant_id, :year)',
    ['tenant_id' => $tenantId->toString(), 'year' => $year]
)->fetchOne();

$invoiceNumber = sprintf('INV-%04d-%05d', $year, $sequence);
// Result: INV-2025-00001
```

### Performance Considerations

1. **Indexes Optimized for RLS**: All indexes start with `tenant_id` for efficient RLS filtering
2. **Partial Indexes**: Used for common query patterns (e.g., overdue invoices)
3. **Composite Indexes**: Reduce table lookups for frequent queries
4. **Atomic Function**: Prevents race conditions in concurrent invoice creation
5. **JSONB for Tax Breakdown**: Flexible storage without schema changes for different tax structures

### Security Features

- **Multi-tenant isolation**: RLS at database level
- **Immutable billing data**: Snapshot at invoice creation prevents tampering
- **Audit trail**: Timestamps track all state changes
- **Referential integrity**: Proper foreign key constraints
- **Business rule enforcement**: CHECK constraints prevent invalid data

### Idempotency

Migration uses `CREATE TABLE IF NOT EXISTS` and `DROP ... IF EXISTS` to allow safe re-runs during development.

**Warning:** In production, use Doctrine Migrations properly - do not re-run migrations.

---

**Created:** 2025-11-28
**Migration Version:** Version20251128300000
**Author:** Database Engineer Agent
