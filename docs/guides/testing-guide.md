# Testing Guide for DDD/CQRS Architecture

**Version**: 1.1
**Last Updated**: 2025-11-06
**Based on**: Best practices from docs/articles/ + RLS multi-tenancy patterns

---

## Testing Philosophy

In DDD architecture, testing follows the **test pyramid** with emphasis on isolating business logic from framework dependencies:

```
         /\
        /E2\      ← Few: Critical user journeys
       /----\
      / Func \    ← Some: API endpoints, controller integration
     /--------\
    /Integration\  ← Medium: Repository, database operations
   /------------\
  /   Unit Tests \ ← Many: Domain logic, handlers (NO framework)
 /----------------\
```

**Key Principle**: Business logic (Domain + Application) tested **without** Symfony, Doctrine, or any framework.

---

## 1. Unit Tests (Domain Layer)

### 1.1 Testing Aggregates

**Purpose**: Verify business rules, invariants, and domain logic in complete isolation.

**Tools**: PHPUnit only (no framework)

**Example: Order Aggregate**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Order\Domain\Model;

use App\Order\Domain\Model\Order;
use App\Order\Domain\Model\OrderLine;
use App\Order\Domain\ValueObject\Money;
use App\Order\Domain\Exception\NonPositiveOrderAmountException;
use PHPUnit\Framework\TestCase;

final class OrderTest extends TestCase
{
    public function test_create_order_with_valid_data(): void
    {
        // Arrange
        $lines = [
            new OrderLine('p1', 'Product 1', 1, Money::fromMinor(500)),
            new OrderLine('p2', 'Product 2', 2, Money::fromMinor(500)),
        ];

        // Act
        $order = Order::create(
            'ord-123',
            Money::fromMinor(1500),
            ...$lines
        );

        // Assert
        self::assertSame('ord-123', $order->getId());
        self::assertSame(1500, $order->getAmountToPay());
        self::assertCount(2, [...$order->getItems()]);
        self::assertTrue($order->isPending());
    }

    public function test_cannot_create_order_with_zero_amount(): void
    {
        $this->expectException(NonPositiveOrderAmountException::class);
        $this->expectExceptionMessage('Order amount must be greater than zero');

        Order::create(
            'ord-123',
            Money::fromMinor(0)
        );
    }

    public function test_cannot_create_order_with_negative_amount(): void
    {
        $this->expectException(NonPositiveOrderAmountException::class);

        Order::create(
            'ord-123',
            Money::fromMinor(-100)
        );
    }

    public function test_cancel_pending_order(): void
    {
        // Arrange
        $order = Order::create(
            'ord-123',
            Money::fromMinor(1000),
            new OrderLine('p1', 'Product 1', 1, Money::fromMinor(1000))
        );

        // Act
        $order->cancel();

        // Assert
        self::assertTrue($order->isCancelled());
    }

    public function test_cannot_cancel_shipped_order(): void
    {
        // Arrange
        $order = Order::create(/* ... */);
        $order->ship();

        // Act & Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot cancel shipped order');

        $order->cancel();
    }

    public function test_domain_events_recorded_on_creation(): void
    {
        // Act
        $order = Order::create(
            'ord-123',
            Money::fromMinor(1000),
            new OrderLine('p1', 'Product 1', 1, Money::fromMinor(1000))
        );

        // Assert
        $events = $order->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(OrderCreated::class, $events[0]);
        self::assertSame('ord-123', $events[0]->orderId->value());
    }
}
```

**Best Practices:**
- ✅ Test all factory methods
- ✅ Test all business methods
- ✅ Test invariant violations (exceptions)
- ✅ Test domain event recording
- ✅ Use descriptive test names
- ❌ NO database access
- ❌ NO framework dependencies

### 1.2 Testing Value Objects

**Example: Money Value Object**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\SharedKernel\Domain\ValueObject;

use App\SharedKernel\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function test_creates_money_from_minor_units(): void
    {
        $money = Money::fromMinor(1500, 'USD');

        self::assertSame(1500, $money->toMinor());
        self::assertSame('USD', $money->currency());
    }

    public function test_cannot_create_negative_money(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount cannot be negative');

        Money::fromMinor(-100, 'USD');
    }

    public function test_equality_comparison(): void
    {
        $money1 = Money::fromMinor(1000, 'USD');
        $money2 = Money::fromMinor(1000, 'USD');
        $money3 = Money::fromMinor(1000, 'EUR');

        self::assertTrue($money1->equals($money2));
        self::assertFalse($money1->equals($money3));
    }

    public function test_add_money_same_currency(): void
    {
        $money1 = Money::fromMinor(1000, 'USD');
        $money2 = Money::fromMinor(500, 'USD');

        $result = $money1->add($money2);

        self::assertSame(1500, $result->toMinor());
        self::assertSame('USD', $result->currency());
    }

    public function test_cannot_add_different_currencies(): void
    {
        $money1 = Money::fromMinor(1000, 'USD');
        $money2 = Money::fromMinor(500, 'EUR');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot add different currencies');

        $money1->add($money2);
    }
}
```

### 1.3 Testing Symfony Validators (Constraints)

**Example: Custom Constraint Test**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Catalog\Infrastructure\Validation;

use App\Catalog\Infrastructure\Validation\ValidSKU;
use App\Catalog\Infrastructure\Validation\ValidSKUValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

final class ValidSKUValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): ValidSKUValidator
    {
        return new ValidSKUValidator();
    }

    public function test_valid_sku_format(): void
    {
        $this->validator->validate('CAT-000001', new ValidSKU());

        $this->assertNoViolation();
    }

    /**
     * @dataProvider invalidSKUProvider
     */
    public function test_invalid_sku_format(string $invalidSKU, string $expectedMessage): void
    {
        $this->validator->validate($invalidSKU, new ValidSKU());

        $this->buildViolation($expectedMessage)
            ->assertRaised();
    }

    public function invalidSKUProvider(): array
    {
        return [
            ['invalid', 'SKU must match format: XXX-000000'],
            ['CAT-ABC', 'SKU must match format: XXX-000000'],
            ['cat-000001', 'SKU must be uppercase'],
        ];
    }
}
```

---

## 2. Application Tests (Handler Tests)

### 2.1 Testing Command Handlers

**Purpose**: Verify use case execution, validation, transactions, and event dispatching.

**Tools**: PHPUnit + In-memory adapters (NO real database)

**Example: CreateProductCommandHandler**

```php
<?php

declare(strict_types=1);

namespace Tests\Application\Catalog\Command;

use App\Catalog\Application\Command\CreateProduct;
use App\Catalog\Application\Command\CreateProductHandler;
use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\ProductName;
use App\SharedKernel\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;
use Tests\Application\Support\InMemory\InMemoryProductRepository;
use Tests\Application\Support\InMemory\InMemoryCommandValidator;
use Tests\Application\Support\InMemory\InMemoryTransactionRunner;

final class CreateProductHandlerTest extends TestCase
{
    private InMemoryProductRepository $repository;
    private InMemoryCommandValidator $validator;
    private InMemoryTransactionRunner $transaction;
    private CreateProductHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryProductRepository();
        $this->validator = new InMemoryCommandValidator();
        $this->transaction = new InMemoryTransactionRunner();

        $this->handler = new CreateProductHandler(
            $this->repository,
            $this->validator,
            $this->transaction
        );
    }

    public function test_creates_product_successfully(): void
    {
        // Arrange
        $command = new CreateProduct(
            id: ProductId::generate(),
            tenantId: TenantId::generate(),
            name: ProductName::fromString('Test Product'),
            price: Money::fromMinor(1000, 'USD')
        );

        // Act
        $product = ($this->handler)($command);

        // Assert
        self::assertInstanceOf(Product::class, $product);
        self::assertNotNull($this->repository->findById($product->id()));
        self::assertTrue($this->transaction->wasExecuted());
    }

    public function test_validation_failure_prevents_creation(): void
    {
        // Arrange
        $command = new CreateProduct(/* invalid data */);
        $this->validator->shouldFail();

        // Act & Assert
        $this->expectException(ValidationException::class);

        ($this->handler)($command);

        self::assertSame(0, $this->repository->count());
    }

    public function test_transaction_rollback_on_exception(): void
    {
        // Arrange
        $this->repository->throwOnSave();
        $command = new CreateProduct(/* valid data */);

        // Act & Assert
        $this->expectException(\RuntimeException::class);

        ($this->handler)($command);

        self::assertTrue($this->transaction->wasRolledBack());
    }
}
```

### 2.2 In-Memory Test Adapters

**InMemoryProductRepository:**

```php
<?php

namespace Tests\Application\Support\InMemory;

use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;

final class InMemoryProductRepository implements ProductRepositoryInterface
{
    private array $products = [];
    private bool $shouldThrow = false;

    public function save(Product $product): void
    {
        if ($this->shouldThrow) {
            throw new \RuntimeException('Repository error');
        }

        $this->products[$product->id()->toString()] = $product;
    }

    public function findById(ProductId $id): ?Product
    {
        return $this->products[$id->toString()] ?? null;
    }

    public function throwOnSave(): void
    {
        $this->shouldThrow = true;
    }

    public function count(): int
    {
        return count($this->products);
    }
}
```

**Best Practices:**
- ✅ Test validation through validator contract
- ✅ Test transaction execution
- ✅ Test exception handling
- ✅ Use in-memory adapters (fast, isolated)
- ❌ NO real database
- ❌ NO real framework services

---

## 3. Integration Tests

### 3.1 Testing Doctrine Repositories

**Purpose**: Verify persistence layer works correctly with real database.

**Tools**: PHPUnit + Symfony KernelTestCase + Test database

**Example: DoctrineProductRepository**

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Catalog\Infrastructure\Repository;

use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Infrastructure\Persistence\Doctrine\Repository\DoctrineProductRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineProductRepositoryTest extends KernelTestCase
{
    private DoctrineProductRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = static::getContainer();
        $this->repository = $container->get(DoctrineProductRepository::class);

        // Clean database before each test
        $em = $container->get('doctrine')->getManager();
        $em->createQuery('DELETE FROM App\Catalog\Infrastructure\Persistence\Doctrine\Entity\ProductEntity')->execute();
    }

    public function test_persists_and_retrieves_product(): void
    {
        // Arrange
        $product = Product::create(
            id: ProductId::generate(),
            tenantId: TenantId::generate(),
            name: ProductName::fromString('Test Product'),
            price: Money::fromMinor(1000)
        );

        // Act
        $this->repository->save($product);
        $retrieved = $this->repository->findById($product->id());

        // Assert
        self::assertNotNull($retrieved);
        self::assertEquals($product->id()->toString(), $retrieved->id()->toString());
        self::assertEquals($product->name()->value(), $retrieved->name()->value());
        self::assertEquals($product->price()->toMinor(), $retrieved->price()->toMinor());
    }

    public function test_returns_null_for_non_existent_product(): void
    {
        $result = $this->repository->findById(ProductId::generate());

        self::assertNull($result);
    }

    /**
     * @dataProvider productDataProvider
     */
    public function test_find_by_tenant(array $products, TenantId $searchTenant, int $expectedCount): void
    {
        // Arrange
        foreach ($products as $product) {
            $this->repository->save($product);
        }

        // Act
        $result = $this->repository->findByTenant($searchTenant);

        // Assert
        self::assertCount($expectedCount, $result);
    }

    public function productDataProvider(): array
    {
        $tenant1 = TenantId::generate();
        $tenant2 = TenantId::generate();

        return [
            'find products for tenant 1' => [
                'products' => [
                    Product::create(/* ... */, $tenant1, /* ... */),
                    Product::create(/* ... */, $tenant1, /* ... */),
                    Product::create(/* ... */, $tenant2, /* ... */),
                ],
                'searchTenant' => $tenant1,
                'expectedCount' => 2,
            ],
        ];
    }
}
```

**Configuration for Test Database:**

```yaml
# config/packages/test/doctrine.yaml
doctrine:
    dbal:
        dbname_suffix: '_test%env(default::TEST_TOKEN)%'
```

**Best Practices:**
- ✅ Use real database (SQLite or PostgreSQL for tests)
- ✅ Clean database before each test
- ✅ Test conversion domain ↔ entity
- ✅ Test custom Doctrine types
- ✅ Use data providers for multiple scenarios

---

## 3.2 Testing with Multi-Tenancy (RLS)

**Purpose**: Properly configure tenant context to avoid PostgreSQL Row-Level Security (RLS) violations.

**Background**: This application uses PostgreSQL RLS policies to enforce tenant isolation at the database level. All tables with `tenant_id` columns have RLS policies that check the session variable `app.tenant_id`.

### The Problem

Without proper tenant context, tests fail with:
```
SQLSTATE[42501]: Insufficient privilege: 7 ERROR:  new row violates row-level security policy for table "stock_items"
```

### The Solution: TenantTestTrait

Use the `TenantTestTrait` helper in all integration and functional tests that interact with the database.

**Location**: `tests/Support/TenantTestTrait.php`

**Features**:
- ✅ `setTenantContext(string $tenantId)` - Sets PostgreSQL session variable for RLS
- ✅ `getDefaultTenantId()` - Returns test tenant from environment (`DEFAULT_TENANT_ID`)
- ✅ `withTenantHeader(array $headers, string $tenantId)` - Adds X-Tenant-ID header for API tests
- ✅ `getEntityManager()` - Helper for accessing EntityManager

### Integration Test Example

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Inventory\Infrastructure\Repository;

use App\Inventory\Domain\Model\StockItem;
use App\Inventory\Domain\Repository\StockItemRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tests\Support\TenantTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class StockItemRepositoryTest extends KernelTestCase
{
    use TenantTestTrait;  // ← Add this trait

    private StockItemRepositoryInterface $repository;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = static::getContainer();
        $this->repository = $container->get(StockItemRepositoryInterface::class);

        // ✅ Use default test tenant ID (not random!)
        $this->tenantId = $this->getDefaultTenantId();

        // ✅ Set tenant context for RLS (persists across transactions)
        $this->setTenantContext($this->tenantId->toString());

        // ✅ Clean up existing test data to avoid pollution
        $this->cleanupTestData();
    }

    protected function tearDown(): void
    {
        // Clean up after each test
        $this->cleanupTestData();

        parent::tearDown();
    }

    private function cleanupTestData(): void
    {
        $em = $this->getEntityManager();
        $connection = $em->getConnection();

        // Delete all stock items for the test tenant
        $connection->executeStatement(
            sprintf(
                "DELETE FROM stock_items WHERE tenant_id = '%s'",
                $this->tenantId->toString()
            )
        );
    }

    public function testSaveAndFindById(): void
    {
        // Arrange
        $stockItem = StockItem::create(
            StockItemId::generate(),
            $this->tenantId,  // ← Use default tenant, not random
            ProductId::generate(),
            WarehouseId::generate(),
            Quantity::fromInt(100)
        );

        // Act
        $this->repository->save($stockItem);
        $foundStockItem = $this->repository->findById($stockItem->id());

        // Assert
        $this->assertNotNull($foundStockItem);
        $this->assertTrue($foundStockItem->id()->equals($stockItem->id()));
    }
}
```

### Functional Test Example

```php
<?php

declare(strict_types=1);

namespace Tests\Functional\Inventory\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tests\Support\TenantTestTrait;

final class StockItemApiTest extends ApiTestCase
{
    use TenantTestTrait;  // ← Add this trait

    private TenantId $tenantId;

    protected function setUp(): void
    {
        parent::setUp();

        // ✅ Use default test tenant ID
        $this->tenantId = $this->getDefaultTenantId();

        // ✅ Set tenant context for direct DB operations
        $this->setTenantContext($this->tenantId->toString());

        // ✅ Clean up existing test data
        $this->cleanupTestData();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestData();
        parent::tearDown();
    }

    private function cleanupTestData(): void
    {
        $em = $this->getEntityManager();
        $connection = $em->getConnection();

        $connection->executeStatement(
            sprintf(
                "DELETE FROM stock_items WHERE tenant_id = '%s'",
                $this->tenantId->toString()
            )
        );
    }

    public function testCreateStockItem(): void
    {
        $client = $this->createAuthenticatedClient();

        // ✅ Include tenant header in API request
        $response = $client->request('POST', '/api/stock-items', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'X-Tenant-ID' => $this->tenantId->toString(),  // ← Important!
            ],
            'json' => [
                'tenantId' => $this->tenantId->toString(),
                'productId' => ProductId::generate()->toString(),
                'warehouseId' => WarehouseId::generate()->toString(),
                'initialQuantity' => 100,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(201);
    }

    private function createAuthenticatedClient()
    {
        // ... JWT authentication setup

        return static::createClient([], [
            'headers' => [
                'authorization' => 'Bearer ' . $token,
                'X-Tenant-ID' => $this->tenantId->toString(),  // ← Also here
            ]
        ]);
    }
}
```

### Test Database Setup

**Default Tenant Configuration** (`tests/bootstrap.php`):

```php
<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

// Set default tenant ID for tests to avoid RLS violations
if ($_SERVER['APP_ENV'] === 'test') {
    // Use a fixed UUID v4 for the default test tenant
    // Format: xxxxxxxx-xxxx-4xxx-8xxx-xxxxxxxxxxxx (v4 with variant bits)
    $_ENV['DEFAULT_TENANT_ID'] = '00000000-0000-4000-8000-000000000001';

    // Optionally load test-specific environment overrides
    if (file_exists(dirname(__DIR__).'/.env.test.local')) {
        (new Dotenv())->load(dirname(__DIR__).'/.env.test.local');
    }
}
```

**Create Test Database and Tenant**:

```bash
# 1. Create test database
APP_ENV=test symfony console doctrine:database:drop --force --if-exists
APP_ENV=test symfony console doctrine:database:create

# 2. Create schema
APP_ENV=test symfony console doctrine:schema:create

# 3. Insert default test tenant
PGPASSWORD=sr324395 psql -h 127.0.0.1 -U ecom_admin -d ecom_test -c "
SET LOCAL app.tenant_id = '00000000-0000-4000-8000-000000000001';
INSERT INTO tenants (id, name, owner_email, status, created_at, description, slug)
VALUES (
    '00000000-0000-4000-8000-000000000001',
    'Test Tenant',
    'test@example.com',
    'active',
    NOW(),
    'Default tenant for integration tests',
    'test-tenant'
);"
```

### Common Pitfalls

❌ **DON'T**:
```php
// Missing tenant header
$client->request('GET', '/api/stock-items');  // ❌ RLS violation

// Random tenant ID
$this->tenantId = TenantId::generate();  // ❌ Won't exist in DB
```

✅ **DO**:
```php
// Include tenant header
$client->request('GET', '/api/stock-items', [], [],
    ['HTTP_X_TENANT_ID' => $this->tenantId->toString()]
);  // ✅ Proper tenant context

// Use default test tenant
$this->tenantId = $this->getDefaultTenantId();  // ✅ Exists in DB
```

❌ **DON'T**:
```php
// Missing tenant context in integration test
$repository->save($stockItem);  // ❌ RLS violation
```

✅ **DO**:
```php
// Set context in setUp()
protected function setUp(): void {
    parent::setUp();
    $this->setTenantContext($this->getDefaultTenantId()->toString());
}  // ✅ RLS context set
```

### How It Works

1. **PostgreSQL RLS Policy** checks `current_setting('app.tenant_id')` for each query
2. **TenantTestTrait** sets this value using `SET app.tenant_id = 'uuid'`
3. **SET** (not SET LOCAL) persists across transactions within the same connection
4. **Default Test Tenant** (`00000000-0000-4000-8000-000000000001`) must exist in test DB
5. **Cleanup** prevents test data pollution between tests

### Benefits

✅ **Prevents RLS Violations**: Tests no longer fail with permission errors
✅ **Enforces Tenant Isolation**: Tests use proper multi-tenant context
✅ **Consistent Test Data**: All tests use same tenant ID
✅ **No Data Pollution**: Cleanup methods ensure test isolation

---

## 4. Functional/Application Tests

### 4.1 Testing API Endpoints

**Purpose**: Verify HTTP layer works correctly end-to-end.

**Tools**: PHPUnit + WebTestCase

**Example: Product API**

```php
<?php

declare(strict_types=1);

namespace Tests\Functional\Catalog\Presentation\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class ProductApiTest extends WebTestCase
{
    public function test_create_product_returns_201(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('POST', '/api/products', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-Tenant-ID' => 'tenant-123',
        ], json_encode([
            'name' => 'Test Product',
            'price' => ['amount' => 1000, 'currency' => 'USD'],
        ]));

        // Assert
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertResponseHeaderSame('Content-Type', 'application/ld+json; charset=utf-8');

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('id', $data);
        self::assertSame('Test Product', $data['name']);
    }

    public function test_create_product_with_invalid_data_returns_422(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/products', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'name' => '', // Invalid: empty name
            'price' => ['amount' => -100, 'currency' => 'USD'], // Invalid: negative
        ]));

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_get_product_by_id_returns_200(): void
    {
        // Arrange
        $client = static::createClient();
        $productId = $this->createTestProduct();

        // Act
        $client->request('GET', "/api/products/{$productId}");

        // Assert
        self::assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame($productId, $data['id']);
    }

    public function test_get_non_existent_product_returns_404(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/products/non-existent-id');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    private function createTestProduct(): string
    {
        // Helper to create test data
        // ...
    }
}
```

**Best Practices:**
- ✅ Test HTTP status codes
- ✅ Test response content/format
- ✅ Test validation errors
- ✅ Test authentication/authorization
- ✅ Use fixtures or factories for test data
- ✅ Clean database between tests

---

## 5. Testing Best Practices

### 5.1 Test Organization

```
tests/
├── Unit/                    # Domain + Value Objects (fast, isolated)
│   ├── Catalog/
│   │   └── Domain/
│   │       ├── Model/
│   │       │   └── ProductTest.php
│   │       └── ValueObject/
│   │           └── ProductNameTest.php
│   └── SharedKernel/
│       └── Domain/
│           └── ValueObject/
│               └── MoneyTest.php
├── Application/             # Handler tests (with in-memory adapters)
│   ├── Catalog/
│   │   ├── Command/
│   │   │   └── CreateProductHandlerTest.php
│   │   └── Query/
│   │       └── GetProductHandlerTest.php
│   └── Support/
│       └── InMemory/        # Shared in-memory adapters
│           ├── InMemoryProductRepository.php
│           └── InMemoryTransactionRunner.php
├── Integration/             # Infrastructure layer (with real dependencies)
│   └── Catalog/
│       └── Infrastructure/
│           └── Repository/
│               └── DoctrineProductRepositoryTest.php
└── Functional/              # API/E2E tests
    └── Catalog/
        └── Presentation/
            └── Api/
                └── ProductApiTest.php
```

### 5.2 PHPUnit Configuration

```xml
<!-- phpunit.xml.dist -->
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         colors="true"
         bootstrap="tests/bootstrap.php">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Application">
            <directory>tests/Application</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory>tests/Integration</directory>
        </testsuite>
        <testsuite name="Functional">
            <directory>tests/Functional</directory>
        </testsuite>
    </testsuites>

    <php>
        <ini name="display_errors" value="1" />
        <ini name="error_reporting" value="-1" />
        <server name="APP_ENV" value="test" force="true" />
        <server name="SHELL_VERBOSITY" value="-1" />
        <server name="SYMFONY_PHPUNIT_REMOVE" value="" />
        <server name="SYMFONY_PHPUNIT_VERSION" value="10.5" />
    </php>

    <coverage>
        <include>
            <directory suffix=".php">src</directory>
        </include>
        <exclude>
            <directory>src/*/Infrastructure/Persistence/Doctrine/Entity</directory>
            <directory>src/*/Presentation</directory>
        </exclude>
    </coverage>
</phpunit>
```

### 5.3 Running Tests

```bash
# Run all tests
vendor/bin/phpunit

# Run specific test suite
vendor/bin/phpunit --testsuite=Unit
vendor/bin/phpunit --testsuite=Application
vendor/bin/phpunit --testsuite=Integration
vendor/bin/phpunit --testsuite=Functional

# Run with coverage
vendor/bin/phpunit --coverage-html coverage

# Run specific test
vendor/bin/phpunit tests/Unit/Catalog/Domain/Model/ProductTest.php

# Run with filter
vendor/bin/phpunit --filter=test_create_product
```

---

## 6. Coverage Targets

| Layer | Coverage Target | Reason |
|-------|----------------|---------|
| Domain | **≥ 95%** | Critical business logic |
| Application | **≥ 90%** | Use case orchestration |
| Infrastructure | **≥ 70%** | Technical adapters |
| Presentation | **≥ 60%** | Thin controllers, mostly integration |

---

## 7. Common Patterns & Anti-Patterns

### ✅ DO

- Write domain tests first (TDD for business logic)
- Use in-memory adapters for application tests
- Test one thing per test method
- Use descriptive test names (`test_cannot_create_order_with_negative_amount`)
- Use data providers for multiple scenarios
- Clean up test data between tests

### ❌ DON'T

- Don't test framework code (Symfony, Doctrine)
- Don't use real database for unit/application tests
- Don't write tests that depend on execution order
- Don't test private methods directly (test through public API)
- Don't mock domain objects
- Don't skip tests for "simple" code

---

## Summary

**Testing Hierarchy**:
1. **Unit** (Domain): Business rules, NO dependencies
2. **Application** (Handlers): Use cases, in-memory adapters
3. **Integration** (Infrastructure): Real dependencies, database
4. **Functional** (API): End-to-end HTTP tests

**Key Takeaway**: Most tests should be **fast, isolated unit tests** of domain logic. Reserve integration/functional tests for critical paths only.
