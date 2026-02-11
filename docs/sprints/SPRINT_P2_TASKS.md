# Sprint P2: Individual Task Specifications

This document provides detailed task specifications for agent execution.

---

## Task P2-001: Fix ProductIndexer Missing Status Column

### Context
The ProductIndexer service queries a `product_reviews` table with a `status` column that may not exist, causing 16 functional tests to be skipped.

### Files to Modify

1. **Primary**: `src/Catalog/Infrastructure/Elasticsearch/ProductIndexer.php`
   - Method: `getRatingStats()` (lines 237-258)

2. **Migration (if needed)**: `migrations/Version{timestamp}_AddProductReviewsStatus.php`

3. **Test Files to Unskip**:
   - `tests/Functional/Catalog/Api/ProductSearchApiTest.php`
   - `tests/Functional/Catalog/Api/ProductAutocompleteApiTest.php`

### Implementation Steps

#### Step 1: Verify Database Schema
```bash
# Check if product_reviews table exists
PGPASSWORD=sr324395 psql -h 127.0.0.1 -U ecom_admin -d ecom_test -c "\d product_reviews"
```

#### Step 2: Fix Based on Schema State

**If table does NOT exist** - Modify `getRatingStats()` to handle gracefully:

```php
private function getRatingStats(string $productId, string $tenantId): array
{
    try {
        // Check if table exists first
        $tableExists = $this->connection->executeQuery(
            "SELECT EXISTS (
                SELECT FROM information_schema.tables
                WHERE table_name = 'product_reviews'
            )"
        )->fetchOne();

        if (!$tableExists) {
            return ['average_rating' => 0.0, 'review_count' => 0];
        }

        $sql = "
            SELECT
                COALESCE(AVG(rating)::float, 0.0) as average_rating,
                COUNT(*)::int as review_count
            FROM product_reviews
            WHERE product_id = :product_id
            AND tenant_id = :tenant_id
        ";

        $result = $this->connection->executeQuery($sql, [
            'product_id' => $productId,
            'tenant_id' => $tenantId,
        ])->fetchAssociative();

        return [
            'average_rating' => $result['average_rating'] ?? 0.0,
            'review_count' => $result['review_count'] ?? 0,
        ];
    } catch (\Exception $e) {
        // Log error but don't fail indexing
        return ['average_rating' => 0.0, 'review_count' => 0];
    }
}
```

**If table EXISTS but missing status column** - Create migration:

```php
<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251127000000_AddProductReviewsStatus extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add status column to product_reviews table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM information_schema.columns
                    WHERE table_name = 'product_reviews' AND column_name = 'status'
                ) THEN
                    ALTER TABLE product_reviews
                    ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'approved';

                    CREATE INDEX idx_product_reviews_status
                    ON product_reviews(status);
                END IF;
            END $$;
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE product_reviews DROP COLUMN IF EXISTS status");
    }
}
```

#### Step 3: Remove Test Skips

Remove these lines from both test files:
```php
// REMOVE THIS LINE:
$this->markTestSkipped('ProductIndexer missing status column - fix required in ProductIndexer.php');
```

#### Step 4: Verification

```bash
# Run affected tests
vendor/bin/phpunit tests/Functional/Catalog/Api/ProductSearchApiTest.php -v
vendor/bin/phpunit tests/Functional/Catalog/Api/ProductAutocompleteApiTest.php -v
```

### Definition of Done
- [ ] `getRatingStats()` does not throw SQL exceptions
- [ ] Both test files have `markTestSkipped` removed
- [ ] All 16 tests execute (may still have some failures for other reasons)
- [ ] PHPStan passes for modified files

---

## Task P2-002: Fix VariantEntity Circular Reference

### Context
Bidirectional Doctrine relationship causes circular reference during API serialization.

### Files to Modify

1. **Primary**: `src/Catalog/Infrastructure/Persistence/Doctrine/Entity/VariantEntity.php`

2. **May Need**: `src/Catalog/Infrastructure/Persistence/Doctrine/Entity/ConfigurableProductEntity.php`

3. **Test File**: `tests/Functional/Catalog/Api/VariantApiTest.php`

### Implementation Steps

#### Step 1: Add Serialization Groups to VariantEntity

```php
<?php
// Add these imports
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;

// Modify class attributes
#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['variant:read']],
            provider: \App\Catalog\Infrastructure\ApiPlatform\State\VariantCollectionProvider::class
        ),
        new Post(
            denormalizationContext: ['groups' => ['variant:write']],
            processor: \App\Catalog\Infrastructure\ApiPlatform\State\CreateVariantProcessor::class
        ),
        new Get(
            normalizationContext: ['groups' => ['variant:read', 'variant:read:item']]
        ),
        new Patch(
            denormalizationContext: ['groups' => ['variant:write']],
            processor: \App\Catalog\Infrastructure\ApiPlatform\State\UpdateVariantProcessor::class
        ),
        new Delete(
            processor: \App\Catalog\Infrastructure\ApiPlatform\State\DeleteVariantProcessor::class
        ),
    ],
    normalizationContext: ['groups' => ['variant:read'], 'enable_max_depth' => true]
)]
class VariantEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    #[Groups(['variant:read'])]
    private string $id;

    #[ORM\ManyToOne(targetEntity: ConfigurableProductEntity::class, inversedBy: 'variants')]
    #[ORM\JoinColumn(name: 'configurable_product_id', nullable: false, onDelete: 'CASCADE')]
    #[Ignore]  // Keep this - don't serialize the parent relationship
    #[MaxDepth(1)]
    private ?ConfigurableProductEntity $configurableProduct = null;

    #[ORM\Column(type: 'string', length: 64)]
    #[Groups(['variant:read', 'variant:write'])]
    private string $sku;

    #[ORM\Column(type: 'json', name: 'option_value_map')]
    #[Groups(['variant:read', 'variant:write'])]
    private array $optionValueMap = [];

    #[ORM\Column(type: 'bigint', name: 'price_amount')]
    #[Groups(['variant:read', 'variant:write'])]
    private int $priceAmount;

    #[ORM\Column(type: 'string', length: 3, name: 'price_currency')]
    #[Groups(['variant:read', 'variant:write'])]
    private string $priceCurrency;

    #[ORM\Column(type: 'integer', name: 'stock_on_hand')]
    #[Groups(['variant:read', 'variant:write'])]
    private int $stockOnHand = 0;

    #[ORM\Column(type: 'boolean', name: 'is_active')]
    #[Groups(['variant:read', 'variant:write'])]
    private bool $isActive = true;

    // ... rest of properties with appropriate Groups
}
```

#### Step 2: Update ConfigurableProductEntity (if needed)

```php
// In ConfigurableProductEntity, add MaxDepth to variants collection
#[ORM\OneToMany(mappedBy: 'configurableProduct', targetEntity: VariantEntity::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
#[MaxDepth(1)]
#[Groups(['configurable_product:read:variants'])]
private Collection $variants;
```

#### Step 3: Verify Serialization

```bash
# Test single variant endpoint
curl -X GET http://localhost:8000/api/v1/variant_entities/{id} \
  -H "Authorization: Bearer {token}" \
  -H "X-Tenant-ID: {tenant_id}"
```

#### Step 4: Run Tests

```bash
vendor/bin/phpunit tests/Functional/Catalog/Api/VariantApiTest.php -v
```

### Definition of Done
- [ ] GET /api/v1/variant_entities returns JSON without errors
- [ ] GET /api/v1/variant_entities/{id} returns single variant
- [ ] No "circular reference" errors in serialization
- [ ] All VariantApiTest tests pass
- [ ] PHPStan passes for modified files

---

## Task P2-003: Fix TaxRuleCollectionProvider Hydra Format

### Context
Collection provider returns plain array instead of API Platform pagination format.

### Files to Modify

1. **Primary**: `src/Tax/Presentation/Api/Provider/TaxRuleCollectionProvider.php`

2. **Repository**: `src/Tax/Domain/Repository/TaxRuleRepositoryInterface.php`
   `src/Tax/Infrastructure/Persistence/Doctrine/Repository/DoctrineTaxRuleRepository.php`

3. **Test File**: `tests/Functional/Tax/Api/TaxRuleApiTest.php`

### Implementation Steps

#### Step 1: Add Count Method to Repository Interface

```php
// In TaxRuleRepositoryInterface.php
public function countByTenant(TenantId $tenantId, bool $activeOnly = false): int;
```

#### Step 2: Implement Count Method in Repository

```php
// In DoctrineTaxRuleRepository.php
public function countByTenant(TenantId $tenantId, bool $activeOnly = false): int
{
    $qb = $this->entityManager->createQueryBuilder()
        ->select('COUNT(t.id)')
        ->from(TaxRuleEntity::class, 't')
        ->where('t.tenantId = :tenantId')
        ->setParameter('tenantId', $tenantId->toString());

    if ($activeOnly) {
        $qb->andWhere('t.isActive = true');
    }

    return (int) $qb->getQuery()->getSingleScalarResult();
}
```

#### Step 3: Create Paginator Class

```php
<?php
// src/Tax/Presentation/Api/Provider/TaxRulePaginator.php

declare(strict_types=1);

namespace App\Tax\Presentation\Api\Provider;

use App\Tax\Presentation\Api\Resource\TaxRuleResource;

final class TaxRulePaginator implements \IteratorAggregate, \Countable
{
    /**
     * @param TaxRuleResource[] $items
     */
    public function __construct(
        private readonly array $items,
        private readonly int $totalItems,
        private readonly int $currentPage,
        private readonly int $itemsPerPage
    ) {
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->items);
    }

    public function count(): int
    {
        return $this->totalItems;
    }

    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    public function getItemsPerPage(): int
    {
        return $this->itemsPerPage;
    }

    public function getLastPage(): int
    {
        return (int) ceil($this->totalItems / $this->itemsPerPage);
    }

    public function getTotalItems(): int
    {
        return $this->totalItems;
    }
}
```

#### Step 4: Update TaxRuleCollectionProvider

```php
<?php

declare(strict_types=1);

namespace App\Tax\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\PaginatorInterface;
use ApiPlatform\State\Pagination\PartialPaginatorInterface;
use ApiPlatform\State\ProviderInterface;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tax\Application\Query\GetAllTaxRules;
use App\Tax\Domain\Repository\TaxRuleRepositoryInterface;
use App\Tax\Presentation\Api\Transformer\TaxRuleResourceTransformer;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

final class TaxRuleCollectionProvider implements ProviderInterface
{
    use HandleTrait;

    public function __construct(
        MessageBusInterface $queryBus,
        private readonly TaxRuleResourceTransformer $transformer,
        private readonly TaxRuleRepositoryInterface $repository
    ) {
        $this->messageBus = $queryBus;
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        $request = $context['request'] ?? null;
        if (!$request) {
            throw new BadRequestHttpException('Request context is missing');
        }

        $tenantIdHeader = $request->headers->get('X-Tenant-ID');
        if (!$tenantIdHeader) {
            throw new BadRequestHttpException('X-Tenant-ID header is required');
        }

        $tenantId = TenantId::fromString($tenantIdHeader);
        $activeOnly = 'true' === $request->query->get('activeOnly', 'false');
        $page = max(1, (int) $request->query->get('page', 1));
        $itemsPerPage = min(100, max(1, (int) $request->query->get('itemsPerPage', 30)));
        $offset = ($page - 1) * $itemsPerPage;

        // Get total count
        $totalItems = $this->repository->countByTenant($tenantId, $activeOnly);

        // Get items for current page
        $query = new GetAllTaxRules(
            tenantId: $tenantId,
            activeOnly: $activeOnly,
            limit: $itemsPerPage,
            offset: $offset
        );

        $dtos = $this->handle($query);
        $resources = $this->transformer->fromDTOs($dtos);

        // Return paginator that API Platform can serialize to Hydra format
        return new TaxRulePaginator($resources, $totalItems, $page, $itemsPerPage);
    }
}
```

#### Step 5: Register Paginator with API Platform

Update `config/packages/api_platform.yaml` if needed:
```yaml
api_platform:
    collection:
        pagination:
            enabled: true
            items_per_page: 30
            maximum_items_per_page: 100
```

#### Step 6: Verify Response Format

```bash
curl -X GET "http://localhost:8000/api/v1/tax_rules?page=1" \
  -H "Authorization: Bearer {token}" \
  -H "X-Tenant-ID: {tenant_id}" \
  -H "Accept: application/ld+json"
```

Expected response should include:
- `hydra:member`
- `hydra:totalItems`
- `hydra:view` with pagination links

#### Step 7: Run Tests

```bash
vendor/bin/phpunit tests/Functional/Tax/Api/TaxRuleApiTest.php -v
```

### Definition of Done
- [ ] GET /api/v1/tax_rules returns `hydra:member` array
- [ ] Response includes `hydra:totalItems`
- [ ] Response includes pagination `hydra:view` when > itemsPerPage
- [ ] Empty collection returns correct format
- [ ] All TaxRuleApiTest tests pass
- [ ] PHPStan passes for modified files

---

## Task P2-004: Reduce PHPStan Errors to Zero

### Context
50 PHPStan errors at level 8 blocking CI/CD pipeline.

### Execution Steps

#### Step 1: Generate Error Report

```bash
cd /var/www/new_ecom/backend
vendor/bin/phpstan analyse --error-format=json > phpstan-errors.json 2>&1
vendor/bin/phpstan analyse --error-format=table > phpstan-errors.txt 2>&1
```

#### Step 2: Categorize Errors

Common patterns to look for:
1. **Nullable access**: `Cannot call method X() on Y|null`
2. **Type mismatch**: `Parameter expects X, Y given`
3. **Missing return**: `Method should return X but returns Y`
4. **Array types**: `Array<string, mixed> vs array<int, X>`

#### Step 3: Fix by Category

**Pattern A - Null checks**:
```php
// Before (error)
$result = $entity->getRelation()->getName();

// After (fixed)
$relation = $entity->getRelation();
$result = $relation !== null ? $relation->getName() : null;
```

**Pattern B - Type assertions**:
```php
// Before (error)
/** @var string */
$value = $request->get('param');

// After (fixed)
$value = $request->get('param');
if (!is_string($value)) {
    throw new \InvalidArgumentException('Expected string');
}
```

**Pattern C - PHPDoc fixes**:
```php
// Before (error)
/** @return array */
public function getData(): array

// After (fixed)
/** @return array<string, mixed> */
public function getData(): array
```

#### Step 4: Incremental Verification

```bash
# After each file fix
vendor/bin/phpstan analyse src/Path/To/FixedFile.php

# Full check
vendor/bin/phpstan analyse
```

#### Step 5: Run Tests After Fixes

```bash
vendor/bin/phpunit
```

### Definition of Done
- [ ] `vendor/bin/phpstan analyse` returns 0 errors
- [ ] No new baseline entries in `phpstan-baseline.neon`
- [ ] All existing tests pass
- [ ] Changes reviewed for correctness

---

## Verification Checklist

After all tasks complete, run full verification:

```bash
# 1. Reset test database
./tests/reset_test_db.sh

# 2. Run all tests
vendor/bin/phpunit

# 3. Check PHPStan
vendor/bin/phpstan analyse

# 4. Check Deptrac (architecture)
vendor/bin/deptrac analyse --config-file=deptrac.yaml

# 5. Run specific functional tests
vendor/bin/phpunit tests/Functional/ --testdox
```

### Expected Results After Sprint P2

| Metric | Before | After |
|--------|--------|-------|
| Unit Tests | 2,126 PASS | 2,126 PASS |
| Integration Tests | 220 PASS | 220 PASS |
| Functional Tests | 358/528 PASS (67.8%) | 449+/528 PASS (85%+) |
| PHPStan Errors | 50 | 0 |
| Skipped Tests | ~16 | 0 |

---

**Document Version**: 1.0
**Created**: 2025-11-27
