# Sprint P2: Functional Test Stabilization and Code Quality

**Sprint Goal**: Increase functional test pass rate from 67.8% to 85%+ and reduce PHPStan errors to 0

**Sprint Duration**: 3-5 days (estimated)

**Epic**: Epic 5 - Test Coverage Improvement

---

## Executive Summary

Sprint P2 focuses on eliminating technical debt that blocks functional test execution. The sprint addresses four distinct issues with clear dependencies and parallel execution opportunities.

### Current State
| Metric | Current | Target | Gap |
|--------|---------|--------|-----|
| Unit Tests | 2,126 (100%) | 2,126 | - |
| Integration Tests | 220 (100%) | 220 | - |
| Functional Tests | 528 (67.8%) | 528 (85%+) | +17.2% |
| PHPStan Errors | 50 | 0 | -50 errors |

### Impact Analysis
| Task | Tests Affected | Blocking Other Tests |
|------|----------------|---------------------|
| Fix ProductIndexer | ~16 tests (skipped) | No |
| Fix VariantEntity Circular Ref | ~4 tests (failing) | No |
| Fix TaxRule Collection Provider | ~10 tests (failing) | No |
| PHPStan Errors | 0 tests | CI/CD pipeline |

---

## Task Breakdown

### P2-001: Fix ProductIndexer Missing Status Column

**Priority**: HIGH
**Effort**: 2-3 hours
**Risk**: LOW

#### Problem Analysis

The `ProductIndexer.php` SQL query references a `status` column in the `product_reviews` table that does not exist in the current schema. The tests are currently skipped with the message:

```php
$this->markTestSkipped('ProductIndexer missing status column - fix required in ProductIndexer.php');
```

**Location**: `/var/www/new_ecom/backend/src/Catalog/Infrastructure/Elasticsearch/ProductIndexer.php`

**Failing Code** (lines 239-253):
```php
$sql = "
    SELECT
        AVG(rating)::float as average_rating,
        COUNT(*)::int as review_count
    FROM product_reviews
    WHERE product_id = :product_id
    AND tenant_id = :tenant_id
    AND status = 'approved'  -- THIS COLUMN DOES NOT EXIST
";
```

#### Solution Options

| Option | Description | Effort | Risk |
|--------|-------------|--------|------|
| A. Add migration | Create `status` column in `product_reviews` table | 1h | Low |
| B. Remove filter | Remove status check (temporary workaround) | 15m | Medium |
| C. Check schema | Verify table exists, handle gracefully | 30m | Low |

**Recommended**: Option A (Add migration for `status` column) or Option C if `product_reviews` table does not exist yet.

#### Acceptance Criteria

- [ ] ProductIndexer queries execute without SQL errors
- [ ] All 16 tests in ProductSearchApiTest run (not skipped)
- [ ] All 8 tests in ProductAutocompleteApiTest run (not skipped)
- [ ] Rating statistics return correct values (or 0 if no reviews)
- [ ] Integration tests pass for Elasticsearch indexing

#### Test Files Affected
- `/var/www/new_ecom/backend/tests/Functional/Catalog/Api/ProductSearchApiTest.php` (8 tests)
- `/var/www/new_ecom/backend/tests/Functional/Catalog/Api/ProductAutocompleteApiTest.php` (8 tests)

#### Dependencies
- None (can run in parallel with other tasks)

---

### P2-002: Fix VariantEntity Circular Reference

**Priority**: HIGH
**Effort**: 2-4 hours
**Risk**: MEDIUM

#### Problem Analysis

The `VariantEntity` has a bidirectional relationship with `ConfigurableProductEntity` that causes circular reference during JSON serialization:

```
VariantEntity -> configurableProduct -> ConfigurableProductEntity -> variants -> [VariantEntity, ...]
```

**Location**: `/var/www/new_ecom/backend/src/Catalog/Infrastructure/Persistence/Doctrine/Entity/VariantEntity.php`

**Current Configuration** (lines 53-56):
```php
#[ORM\ManyToOne(targetEntity: ConfigurableProductEntity::class, inversedBy: 'variants')]
#[ORM\JoinColumn(name: 'configurable_product_id', nullable: false, onDelete: 'CASCADE')]
#[Ignore]  // Already has Ignore attribute but may not be sufficient
private ?ConfigurableProductEntity $configurableProduct = null;
```

#### Solution Options

| Option | Description | Effort | Risk |
|--------|-------------|--------|------|
| A. Serialization Groups | Add `#[Groups]` attributes to control serialization depth | 2h | Low |
| B. Custom Normalizer | Create custom normalizer for VariantEntity | 3h | Medium |
| C. Max Depth | Add `#[MaxDepth]` attribute to limit recursion | 1h | Low |
| D. DTO Response | Return DTO instead of entity in API responses | 3h | Low |

**Recommended**: Option A (Serialization Groups) combined with Option C (MaxDepth) for defense in depth.

#### Acceptance Criteria

- [ ] GET /api/v1/variant_entities returns JSON without circular reference errors
- [ ] GET /api/v1/variant_entities/{id} returns single variant correctly
- [ ] Variant collection endpoint returns paginated results
- [ ] All 8 tests in VariantApiTest pass
- [ ] No performance degradation in serialization

#### Test Files Affected
- `/var/www/new_ecom/backend/tests/Functional/Catalog/Api/VariantApiTest.php` (~8 tests)

#### Dependencies
- None (can run in parallel with other tasks)

---

### P2-003: Fix TaxRuleCollectionProvider Hydra Format

**Priority**: HIGH
**Effort**: 3-4 hours
**Risk**: MEDIUM

#### Problem Analysis

The `TaxRuleCollectionProvider` returns a plain array instead of proper Hydra pagination format expected by API Platform tests.

**Location**: `/var/www/new_ecom/backend/src/Tax/Presentation/Api/Provider/TaxRuleCollectionProvider.php`

**Current Return** (line 67):
```php
// Transform to resources - API Platform automatically wraps in Hydra format
return $this->transformer->fromDTOs($dtos);  // Returns TaxRuleResource[]
```

**Expected Hydra Format**:
```json
{
    "@context": "/api/contexts/TaxRule",
    "@id": "/api/v1/tax_rules",
    "@type": "hydra:Collection",
    "hydra:member": [...],
    "hydra:totalItems": 35,
    "hydra:view": {
        "@id": "/api/v1/tax_rules?page=1",
        "@type": "hydra:PartialCollectionView",
        "hydra:first": "/api/v1/tax_rules?page=1",
        "hydra:last": "/api/v1/tax_rules?page=2",
        "hydra:next": "/api/v1/tax_rules?page=2"
    }
}
```

#### Solution Options

| Option | Description | Effort | Risk |
|--------|-------------|--------|------|
| A. Return Paginator | Return API Platform Paginator object | 2h | Low |
| B. Custom Hydra Response | Manually construct Hydra format | 3h | Medium |
| C. Use Doctrine Paginator | Leverage Doctrine's paginator with API Platform | 2h | Low |

**Recommended**: Option A or C - Use API Platform's built-in pagination support.

#### Implementation Details

The provider needs to:
1. Get total count from repository (new method needed)
2. Return a proper iterable/paginator that API Platform can serialize
3. Include pagination metadata in response

**Repository Changes Required**:
```php
// Add to TaxRuleRepositoryInterface
public function countByTenant(TenantId $tenantId, bool $activeOnly = false): int;
```

#### Acceptance Criteria

- [ ] GET /api/v1/tax_rules returns `hydra:member` array
- [ ] Response includes `hydra:totalItems` count
- [ ] Response includes `hydra:view` with pagination links
- [ ] Pagination with `?page=1` works correctly
- [ ] All ~10 TaxRuleApiTest pagination tests pass
- [ ] Empty collection returns `hydra:totalItems: 0`

#### Test Files Affected
- `/var/www/new_ecom/backend/tests/Functional/Tax/Api/TaxRuleApiTest.php` (~30 tests, ~10 pagination-related)

#### Dependencies
- Requires TaxRuleRepository modification (countByTenant method)

---

### P2-004: Reduce PHPStan Errors to Zero

**Priority**: MEDIUM
**Effort**: 4-6 hours
**Risk**: LOW

#### Problem Analysis

50 PHPStan errors at level 8. Common error categories:
1. Nullable method calls without null checks
2. Type mismatches (array vs specific types)
3. Missing return type declarations
4. Incorrect PHPDoc annotations

#### Solution Approach

1. **Run PHPStan Analysis**:
   ```bash
   vendor/bin/phpstan analyse --error-format=json > phpstan-errors.json
   ```

2. **Categorize Errors** by file/type

3. **Fix in Priority Order**:
   - Critical: Nullable method calls on potentially null objects
   - High: Type mismatches that could cause runtime errors
   - Medium: PHPDoc corrections
   - Low: Style improvements

#### Acceptance Criteria

- [ ] `vendor/bin/phpstan analyse` returns 0 errors
- [ ] No new baseline entries added
- [ ] All existing tests continue to pass
- [ ] Code changes are semantically equivalent (no behavior changes)
- [ ] CI/CD pipeline passes PHPStan check

#### Dependencies
- Should run AFTER P2-001, P2-002, P2-003 to avoid fixing code that will change

---

## Sprint Execution Plan

### Parallel Execution Matrix

```
Day 1-2:
  [Agent A] P2-001: ProductIndexer ------>
  [Agent B] P2-002: VariantEntity ------->
  [Agent C] P2-003: TaxRule Provider ---->

Day 3:
  [Agent A] Verification & Integration Testing
  [Agent B] Cross-task testing
  [Agent C] Edge case handling

Day 4-5:
  [Agent A/B/C] P2-004: PHPStan Errors (collaborative)
  [All] Final verification & documentation
```

### Task Dependencies Graph

```
P2-001 (ProductIndexer)     ----\
                                 \
P2-002 (VariantEntity)      ------+---> P2-004 (PHPStan)
                                 /
P2-003 (TaxRule Provider)   ----/
```

### Recommended Execution Order

| Order | Task | Reason |
|-------|------|--------|
| 1 | P2-001, P2-002, P2-003 | Independent, can run in parallel |
| 2 | P2-004 | Depends on other tasks completing |

---

## Effort Estimation

| Task | Min Hours | Max Hours | Expected |
|------|-----------|-----------|----------|
| P2-001: ProductIndexer | 2h | 3h | 2.5h |
| P2-002: VariantEntity | 2h | 4h | 3h |
| P2-003: TaxRule Provider | 3h | 4h | 3.5h |
| P2-004: PHPStan | 4h | 6h | 5h |
| Integration Testing | 1h | 2h | 1.5h |
| Documentation | 0.5h | 1h | 0.5h |
| **Total** | **12.5h** | **20h** | **16h** |

**Sprint Capacity**: 3-5 days with 1-3 agents

---

## Risk Assessment

| Risk | Impact | Likelihood | Mitigation |
|------|--------|------------|------------|
| Product reviews table doesn't exist | Medium | High | Create full migration, not just column |
| Serialization fix breaks existing clients | High | Low | Add tests for backward compatibility |
| Hydra format changes affect other endpoints | Medium | Low | Test all API endpoints after changes |
| PHPStan fixes introduce bugs | High | Low | Run full test suite after each fix |

---

## Success Metrics

| Metric | Before | Target | Verification |
|--------|--------|--------|--------------|
| Functional Tests Pass Rate | 67.8% | 85%+ | `vendor/bin/phpunit tests/Functional/` |
| Skipped Tests | ~16 | 0 | No `markTestSkipped` in output |
| PHPStan Errors | 50 | 0 | `vendor/bin/phpstan analyse` |
| CI/CD Pipeline | Failing | Passing | GitHub Actions green |

---

## Post-Sprint Actions

1. **Update CLAUDE.md** with new test counts
2. **Update CHECKLIST.md** with Epic 5 progress
3. **Create Sprint P2 Completion Report**
4. **Plan Sprint P3** for remaining test coverage gaps

---

## Agent Assignments (Recommended)

| Task | Specialist Type | Skills Required |
|------|-----------------|-----------------|
| P2-001 | Backend Developer | SQL, Doctrine, Elasticsearch |
| P2-002 | Backend Developer | API Platform, Serialization |
| P2-003 | Backend Developer | API Platform, Hydra/JSON-LD |
| P2-004 | Code Quality | PHPStan, Type System |

---

## Quick Reference Commands

```bash
# Run specific test files
vendor/bin/phpunit tests/Functional/Catalog/Api/ProductSearchApiTest.php
vendor/bin/phpunit tests/Functional/Catalog/Api/ProductAutocompleteApiTest.php
vendor/bin/phpunit tests/Functional/Catalog/Api/VariantApiTest.php
vendor/bin/phpunit tests/Functional/Tax/Api/TaxRuleApiTest.php

# Run all functional tests
vendor/bin/phpunit tests/Functional/

# Run PHPStan
vendor/bin/phpstan analyse

# Check specific file for PHPStan errors
vendor/bin/phpstan analyse src/Catalog/Infrastructure/Elasticsearch/ProductIndexer.php

# Reset test database
./tests/reset_test_db.sh
```

---

**Document Version**: 1.0
**Created**: 2025-11-27
**Sprint Status**: PLANNED
