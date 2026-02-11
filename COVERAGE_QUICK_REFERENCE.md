# Coverage Quick Reference Guide

## Quick Commands

### Generate Full Coverage Report
```bash
XDEBUG_MODE=coverage php -d memory_limit=1024M vendor/bin/phpunit --coverage-html coverage --coverage-text
```

### Generate Coverage for Unit Tests Only (Faster)
```bash
XDEBUG_MODE=coverage php -d memory_limit=512M vendor/bin/phpunit tests/Unit --coverage-text --colors=never
```

### View Coverage Summary
```bash
XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-text --colors=never 2>&1 | grep -E "^\s+(Classes|Methods|Lines):"
```

### Generate Coverage for Specific Context
```bash
# Catalog
XDEBUG_MODE=coverage vendor/bin/phpunit tests/Unit/Catalog --coverage-text

# Order
XDEBUG_MODE=coverage vendor/bin/phpunit tests/Unit/Order --coverage-text

# Payment
XDEBUG_MODE=coverage vendor/bin/phpunit tests/Unit/Payment --coverage-text
```

### View HTML Report
```bash
# Open in browser (WSL)
wslview coverage/index.html

# Or serve via PHP
php -S localhost:8001 -t coverage
# Then open: http://localhost:8001
```

## Current Status (2025-11-27)

| Metric | Value |
|--------|-------|
| Lines Coverage | 29.23% (8411/28777) |
| Methods Coverage | 36.82% (1758/4774) |
| Classes Coverage | 14.96% (161/1076) |
| Tests Executed | 2,858 |
| Assertions | 7,283 |

## Coverage by Context

| Context | Priority | Lines | Methods | Status |
|---------|----------|-------|---------|--------|
| Catalog | P0 | 4.08% | 7.62% | CRITICAL |
| Order | P0 | 1.78% | 3.08% | CRITICAL |
| Payment | P0 | 3.91% | 3.39% | CRITICAL |
| Inventory | P0 | 1.89% | 2.72% | CRITICAL |
| Pricing | P0 | 2.15% | 3.64% | CRITICAL |
| Tenant | P0 | 0.78% | 1.70% | CRITICAL |
| User | P0 | 0.75% | 1.68% | CRITICAL |
| Customer | P1 | 1.15% | 2.24% | NEEDS WORK |
| Tax | P1 | 0.34% | 0.63% | NEEDS WORK |
| Returns | P2 | 0.78% | 1.70% | NEEDS WORK |
| Shared | P0 | 2.20% | 2.01% | NEEDS WORK |

## Components at 100% Coverage

- Money (39 tests)
- TenantId (70 tests)
- LanguageCode (28 tests)
- UserRole (30 tests)
- User domain model
- Order domain model (99.22%)
- ReturnRequest domain model
- TaxCalculationService
- CacheService
- SynonymManager

## Priority Actions

### Week 1
1. Fix 149 test errors
2. Fix 212 test failures
3. Add Payment processing tests (target: 90%)
4. Add Order edge case tests

### Week 2-3
5. Command handler tests (50+ tests)
6. Query handler tests (40+ tests)
7. Value object completion

### Week 4-6
8. Repository integration tests
9. API processor tests
10. Functional test expansion

## Tools

- **Coverage Engine**: Xdebug 3.4.5
- **Test Framework**: PHPUnit 11.x
- **HTML Report**: `/var/www/new_ecom/backend/coverage/index.html`
- **Text Report**: `/var/www/new_ecom/backend/coverage.txt`

## Configuration

**File**: `phpunit.xml.dist`
```xml
<coverage cacheDirectory=".phpunit.cache/code-coverage"
          processUncoveredFiles="true">
    <report>
        <html outputDirectory="coverage" lowUpperBound="50" highLowerBound="80"/>
        <text outputFile="coverage.txt" showUncoveredFiles="false" showOnlySummary="true"/>
    </report>
    <include>
        <directory suffix=".php">src</directory>
    </include>
    <exclude>
        <directory>src/*/Infrastructure/Persistence/Doctrine/Migrations</directory>
        <file>src/Kernel.php</file>
        <file>src/Schedule.php</file>
    </exclude>
</coverage>
```

## Xdebug Settings

```ini
xdebug.mode=coverage
xdebug.coverage_enable=1
memory_limit=1024M
```

## CI/CD Integration (Future)

```yaml
# .github/workflows/coverage.yml
name: Coverage
on: [push, pull_request]
jobs:
  coverage:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Run tests with coverage
        run: |
          XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-clover coverage.xml
      - name: Upload to Codecov
        uses: codecov/codecov-action@v2
        with:
          files: ./coverage.xml
```

---

**See COVERAGE_REPORT.md for detailed analysis and recommendations.**
