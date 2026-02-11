# Payment Retry Fields Migration

**Migration**: `Version20251126140000`
**Date**: 2025-11-26
**Status**: Ready for execution

## Overview

This migration adds automatic payment retry functionality to the `payments` table by introducing three new columns and a composite index for efficient retry queries.

## Changes

### Database Schema

#### New Columns

1. **error_code** (VARCHAR(100), nullable)
   - Normalized error code from payment gateway
   - Used for retry decision logic (e.g., `card_declined`, `insufficient_funds`, `processing_error`)
   - Distinguishes between transient (retryable) and permanent errors

2. **retry_count** (INTEGER, NOT NULL, default: 0)
   - Number of retry attempts made (0-indexed)
   - Compared against max retry attempts (default: 3)
   - Incremented with each retry attempt

3. **next_retry_at** (TIMESTAMP, nullable)
   - Scheduled timestamp for next retry attempt
   - Uses exponential backoff: 1h, 4h, 24h
   - NULL when no retry is scheduled
   - Doctrine type: `datetime_immutable`

#### New Index

**idx_payments_retry**: `(status, next_retry_at, retry_count)`
- Optimizes queries for finding payments due for retry
- Query pattern: `WHERE status = 'failed' AND next_retry_at <= NOW() AND retry_count < max`
- Composite index ensures efficient filtering and sorting

### Code Changes

#### PaymentEntity Updates

**ORM Mappings Added**:
```php
#[ORM\Column(type: 'string', length: 100, nullable: true, name: 'error_code')]
private ?string $errorCode = null;

#[ORM\Column(type: 'integer', nullable: false, options: ['default' => 0], name: 'retry_count')]
private int $retryCount = 0;

#[ORM\Column(type: 'datetime_immutable', nullable: true, name: 'next_retry_at')]
private ?\DateTimeImmutable $nextRetryAt = null;
```

**Methods Updated**:
1. `fromDomainModel()` - Maps retry fields from Payment aggregate
2. `toDomainModel()` - Passes retry fields to `reconstituteFromPersistence()`
3. `updateFromDomainModel()` - Updates retry fields on entity
4. Added getters: `getErrorCode()`, `getRetryCount()`, `getNextRetryAt()`
5. Added setters: `setErrorCode()`, `setRetryCount()`, `setNextRetryAt()`

## Migration Safety

### Idempotency
- All column additions use `IF NOT EXISTS` checks
- Index creation checks for existing index
- Safe to run multiple times (idempotent)

### Backwards Compatibility
- New columns are nullable or have defaults
- Existing data is not modified
- Domain model already has default values in `reconstituteFromPersistence()`

### Rollback
- `down()` method removes all changes cleanly
- Drops index first, then columns
- Uses `DROP IF EXISTS` for safety

## Execution

### Dry Run (Safe)
```bash
# Verify SQL without executing
symfony console doctrine:migrations:migrate DoctrineMigrations\\Version20251126140000 --dry-run -vvv
```

### Production Execution
```bash
# 1. Backup database first
pg_dump -h 127.0.0.1 -U ecom_admin ecom > backup_before_retry_fields.sql

# 2. Run migration
symfony console doctrine:migrations:migrate

# 3. Verify columns exist
PGPASSWORD=sr324395 psql -h 127.0.0.1 -U ecom_admin -d ecom -c "\d payments"

# 4. Verify index exists
PGPASSWORD=sr324395 psql -h 127.0.0.1 -U ecom_admin -d ecom -c "\di+ idx_payments_retry"
```

### Rollback (if needed)
```bash
symfony console doctrine:migrations:migrate DoctrineMigrations\\Version20251126120000
```

## Testing

### Verify Columns
```sql
SELECT column_name, data_type, is_nullable, column_default
FROM information_schema.columns
WHERE table_name = 'payments'
AND column_name IN ('error_code', 'retry_count', 'next_retry_at')
ORDER BY column_name;
```

Expected output:
```
 column_name   | data_type         | is_nullable | column_default
---------------+-------------------+-------------+----------------
 error_code    | character varying | YES         | NULL
 next_retry_at | timestamp         | YES         | NULL
 retry_count   | integer           | NO          | 0
```

### Verify Index
```sql
SELECT indexname, indexdef
FROM pg_indexes
WHERE tablename = 'payments'
AND indexname = 'idx_payments_retry';
```

Expected output:
```
    indexname     |                          indexdef
------------------+-------------------------------------------------------------
 idx_payments_retry | CREATE INDEX idx_payments_retry ON public.payments
                   | USING btree (status, next_retry_at, retry_count)
```

### Test Entity Conversion
```php
// Create payment with retry fields
$payment = Payment::create(
    id: PaymentId::generate(),
    tenantId: TenantId::generate(),
    orderId: 'ORD-123',
    amountInCents: 10000,
    currency: 'USD',
    method: PaymentMethod::creditCard(),
    gateway: PaymentGateway::stripe()
);

// Mark as failed with error code
$payment->markAsFailed('Card declined', 'card_declined');

// Schedule retry
$policy = RetryPolicy::default();
$payment->scheduleRetry($policy);

// Convert to entity
$entity = PaymentEntity::fromDomainModel($payment);

// Verify fields are set
assert($entity->getErrorCode() === 'card_declined');
assert($entity->getRetryCount() === 0);
assert($entity->getNextRetryAt() !== null);

// Convert back to domain
$reconstituted = $entity->toDomainModel();
assert($reconstituted->errorCode() === 'card_declined');
assert($reconstituted->retryCount() === 0);
assert($reconstituted->nextRetryAt() !== null);
```

## Business Rules

### Retry Policy (from Payment aggregate)
- **Max Attempts**: 3 retries
- **Backoff Strategy**: Exponential
  - Attempt 1: +1 hour
  - Attempt 2: +4 hours (exponential: 2^2)
  - Attempt 3: +24 hours (capped)

### Retryable Errors
- `card_declined` (transient)
- `insufficient_funds` (transient)
- `processing_error` (transient)
- `timeout` (transient)

### Non-Retryable Errors
- `expired_card` (permanent)
- `fraudulent` (permanent)
- `invalid_card_number` (permanent)
- `lost_or_stolen_card` (permanent)

## Performance Impact

### Index Size Estimation
- 3 columns: `status` (VARCHAR 20) + `next_retry_at` (TIMESTAMP 8) + `retry_count` (INTEGER 4) = ~32 bytes/row
- 1M payments ≈ 32 MB index size
- Negligible performance impact

### Query Performance
**Before**: Full table scan to find payments due for retry
```sql
SELECT * FROM payments WHERE status = 'failed'; -- Seq Scan
```

**After**: Index scan on composite index
```sql
SELECT * FROM payments
WHERE status = 'failed'
AND next_retry_at <= NOW()
AND retry_count < 3; -- Index Scan using idx_payments_retry
```

Expected speedup: **100x-1000x** for retry queries

## Related Files

- Migration: `/var/www/new_ecom/backend/migrations/Version20251126140000.php`
- Entity: `/var/www/new_ecom/backend/src/Payment/Infrastructure/Persistence/Doctrine/Entity/PaymentEntity.php`
- Domain Model: `/var/www/new_ecom/backend/src/Payment/Domain/Model/Payment.php`
- Value Object: `/var/www/new_ecom/backend/src/Payment/Domain/ValueObject/RetryPolicy.php`

## Next Steps

1. ✅ Migration created
2. ✅ PaymentEntity updated
3. ⏳ Run migration in development
4. ⏳ Test entity conversion
5. ⏳ Create retry scheduler command
6. ⏳ Create retry processor command
7. ⏳ Add monitoring for retry metrics

---
**Author**: Claude Code (Database Engineer)
**Reviewed**: Pending
**Approved**: Pending
