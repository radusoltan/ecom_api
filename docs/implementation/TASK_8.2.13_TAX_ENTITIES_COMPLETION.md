# Task 8.2.13: Tax Doctrine Entities and Custom Types - COMPLETED ✅

**Date**: 2025-11-28  
**Sprint**: 8.2 - Tax Management Infrastructure  
**Status**: ✅ **COMPLETED**

---

## Summary

Successfully created complete Doctrine infrastructure layer for Tax bounded context following the dual-model pattern.

## Files Created/Updated

### ✅ Created Files (3)

1. **TaxRateType** - `/var/www/new_ecom/backend/src/Tax/Infrastructure/Persistence/Doctrine/Type/TaxRateType.php`
   - Maps `TaxRate` value object to `NUMERIC(5,2)` database column
   - Handles percentage conversion (0.00-100.00)
   - Follows pattern from `ProductIdType`

2. **TaxCategoryType** - `/var/www/new_ecom/backend/src/Tax/Infrastructure/Persistence/Doctrine/Type/TaxCategoryType.php`
   - Maps `TaxCategory` enum to `VARCHAR(20)` database column
   - Valid values: standard, reduced, super_reduced, zero, exempt
   - Uses PHP 8.1 enum backing value

3. **Tax Infrastructure Documentation** - This file

### ✅ Updated Files (3)

1. **TaxRuleIdType** - `/var/www/new_ecom/backend/src/Tax/Infrastructure/Persistence/Doctrine/Type/TaxRuleIdType.php`
   - Fixed namespace: `App\Tax\Domain\ValueObject\TaxRuleId` → `App\Tax\Domain\Model\TaxRuleId`
   - Changed SQL type to UUID (from generic string)
   - Enhanced null handling

2. **TaxRuleEntity** - `/var/www/new_ecom/backend/src/Tax/Infrastructure/Persistence/Doctrine/Entity/TaxRuleEntity.php`
   - Completely rewritten to match current domain model
   - Added all missing fields: `category`, `description`, `priority`, `isReverseCharge`, `validFrom`, `validUntil`
   - Fixed namespace references (Model vs ValueObject)
   - Added API Platform annotations
   - Added comprehensive getters/setters for API
   - Implemented complete conversion methods:
     - `fromDomainModel()` - domain → entity
     - `toDomainModel()` - entity → domain
     - `updateFromDomainModel()` - for updates

3. **Doctrine Configuration** - `/var/www/new_ecom/backend/config/packages/doctrine.yaml`
   - Registered `tax_rate: App\Tax\Infrastructure\Persistence\Doctrine\Type\TaxRateType`
   - Registered `tax_category: App\Tax\Infrastructure\Persistence\Doctrine\Type\TaxCategoryType`
   - (`tax_rule_id` was already registered)

---

## TaxRuleEntity Complete Mapping

| Domain Field | Entity Column | Type | Notes |
|--------------|---------------|------|-------|
| `id` | `id` | UUID | Primary key, uses `tax_rule_id` custom type |
| `tenantId` | `tenant_id` | UUID | Multi-tenant isolation, uses `tenant_id` custom type |
| `jurisdiction.countryCode` | `country_code` | VARCHAR(2) | ISO 3166-1 alpha-2 |
| `jurisdiction.regionCode` | `region_code` | VARCHAR(10) | Optional, for US states, etc. |
| `category` | `category` | VARCHAR(20) | Enum: standard, reduced, super_reduced, zero, exempt |
| `rate` | `rate` | NUMERIC(5,2) | Tax percentage 0.00-100.00 |
| `name` | `name` | VARCHAR(100) | Tax rule name |
| `description` | `description` | TEXT | Optional description |
| `isActive` | `is_active` | BOOLEAN | Active/inactive status |
| `validFrom` | `valid_from` | TIMESTAMPTZ | Validity start date (nullable) |
| `validTo` | `valid_until` | TIMESTAMPTZ | Validity end date (nullable) |
| `priority` | `priority` | INTEGER | Higher priority wins conflicts |
| `isReverseCharge` | `is_reverse_charge` | BOOLEAN | EU B2B reverse charge mechanism |
| `createdAt` | `created_at` | TIMESTAMPTZ | Creation timestamp |
| `updatedAt` | `updated_at` | TIMESTAMPTZ | Last update timestamp |

---

## Database Schema Alignment

The entity perfectly aligns with the migration `Version20251128120000_CreateTaxTables`:

```sql
CREATE TABLE tax_rules (
    id UUID PRIMARY KEY,
    tenant_id UUID NOT NULL,
    country_code VARCHAR(2) NOT NULL,
    region_code VARCHAR(10),
    category VARCHAR(20) NOT NULL,
    rate NUMERIC(5,2) NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    is_active BOOLEAN NOT NULL DEFAULT true,
    valid_from TIMESTAMP WITH TIME ZONE,
    valid_until TIMESTAMP WITH TIME ZONE,
    priority INTEGER NOT NULL DEFAULT 0,
    is_reverse_charge BOOLEAN NOT NULL DEFAULT false,
    created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
    
    CONSTRAINT fk_tax_rules_tenant FOREIGN KEY (tenant_id)
        REFERENCES tenants(id) ON DELETE CASCADE
);
```

**Indexes**:
- ✅ `idx_tax_rules_tenant` - Primary RLS index
- ✅ `idx_tax_rules_jurisdiction_lookup` - Tax calculation queries
- ✅ `idx_tax_rules_country` - Country filtering
- ✅ `idx_tax_rules_category` - Category filtering
- ✅ `idx_tax_rules_active` - Active rules filtering
- ✅ `idx_tax_rules_validity` - Time-based validity queries

---

## Custom Types Design

### TaxRuleIdType

```php
// Database: UUID
// PHP: TaxRuleId (value object wrapping UUID v4)
// Example: "550e8400-e29b-41d4-a716-446655440000"
```

### TaxRateType

```php
// Database: NUMERIC(5,2)
// PHP: TaxRate (value object with percentage validation)
// Example: 19.00 (for 19%)
// Validation: 0.00 <= rate <= 100.00
```

### TaxCategoryType

```php
// Database: VARCHAR(20)
// PHP: TaxCategory (enum)
// Values: 'standard', 'reduced', 'super_reduced', 'zero', 'exempt'
// Example: TaxCategory::STANDARD->value === 'standard'
```

---

## Dual-Model Pattern Implementation

Following the established pattern from `ProductEntity` and `CategoryEntity`:

### 1. Pure Domain Model
```php
// src/Tax/Domain/Model/TaxRule.php
final class TaxRule extends AggregateRoot
{
    private TaxRuleId $id;
    private TenantId $tenantId;
    private TaxJurisdiction $jurisdiction;
    private TaxCategory $category;
    private TaxRate $rate;
    // ... business logic methods
}
```

### 2. Doctrine Entity (Infrastructure Adapter)
```php
// src/Tax/Infrastructure/Persistence/Doctrine/Entity/TaxRuleEntity.php
#[ORM\Entity]
#[ORM\Table(name: 'tax_rules')]
#[ApiResource]
final class TaxRuleEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'tax_rule_id')]
    private string $id;
    
    // Scalar types for database columns
    
    public static function fromDomainModel(TaxRule $taxRule): self;
    public function toDomainModel(): TaxRule;
    public function updateFromDomainModel(TaxRule $taxRule): void;
}
```

### 3. Repository Converts Between Layers
```php
// Repository will use:
$entity = TaxRuleEntity::fromDomainModel($domainModel);
$entityManager->persist($entity);

// And reverse:
$domainModel = $entity->toDomainModel();
```

---

## Verification

### ✅ PHP Syntax Check
```bash
$ php -l TaxRuleEntity.php
No syntax errors detected

$ php -l TaxRateType.php
No syntax errors detected

$ php -l TaxCategoryType.php
No syntax errors detected
```

### ✅ Class Loading
```bash
$ php -r "require 'vendor/autoload.php'; 
    new App\Tax\Infrastructure\Persistence\Doctrine\Type\TaxRuleIdType();
    new App\Tax\Infrastructure\Persistence\Doctrine\Type\TaxRateType();
    new App\Tax\Infrastructure\Persistence\Doctrine\Type\TaxCategoryType();"
# All types loaded successfully
```

### ✅ PHPStan Level 8
```bash
$ vendor/bin/phpstan analyse src/Tax/Infrastructure/Persistence/Doctrine/Entity/ --level=8
# No errors in entity or custom types
```

---

## API Platform Integration

The `TaxRuleEntity` is fully configured for API Platform:

```php
#[ApiResource(
    shortName: 'TaxRule',
    operations: [
        new GetCollection(),  // GET /api/tax_rules
        new Post(),           // POST /api/tax_rules
        new Get(),            // GET /api/tax_rules/{id}
        new Patch(),          // PATCH /api/tax_rules/{id}
        new Delete()          // DELETE /api/tax_rules/{id}
    ]
)]
```

**Future**: Add custom state processors/providers for:
- `CreateTaxRuleProcessor` - validate business rules on creation
- `UpdateTaxRateProcessor` - enforce rate change validation
- `ActivateTaxRuleProcessor` - activate/deactivate rules

---

## Next Steps (Task 8.2.14)

1. **Create Repository Implementation**
   - `DoctrineTaxRuleRepository` implementing `TaxRuleRepositoryInterface`
   - Methods: `save()`, `findById()`, `findApplicableRules()`, `findBestMatchingRule()`
   - Fix namespace issues (ValueObject → Model)

2. **Create State Processors** (Task 8.2.15)
   - CreateTaxRuleProcessor
   - UpdateTaxRuleProcessor
   - ActivateTaxRuleProcessor
   - DeactivateTaxRuleProcessor

3. **Create Unit Tests** (Task 8.2.16)
   - TaxRuleEntityTest (conversion methods)
   - TaxRuleIdTypeTest
   - TaxRateTypeTest
   - TaxCategoryTypeTest

4. **Create Integration Tests** (Task 8.2.17)
   - DoctrineTaxRuleRepositoryTest
   - Full persistence cycle tests

---

## Alignment with PRD

**Section 6.1 - Tax Management**:
- ✅ Multi-jurisdiction support (country + region)
- ✅ Category-based tax rates (standard, reduced, zero, exempt)
- ✅ Time-based validity periods (`validFrom`, `validTo`)
- ✅ Priority-based rule resolution
- ✅ Reverse charge mechanism for EU B2B
- ✅ Multi-tenant isolation (RLS ready)

---

## Technical Debt

None. All implementation follows established patterns and best practices.

---

**Completed by**: Database Engineer Agent  
**Review Status**: Ready for review  
**Merge Status**: Ready for merge after repository implementation
