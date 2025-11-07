# DDD Patterns Summary

**Based on**: Articles analyzed from docs/articles/
**Last Updated**: 2025-10-09

---

## Key Architectural Patterns

### 1. Layered Architecture (Core Pattern)

**Structure:**
```
src/{BoundedContext}/
├── Domain/              # Pure business logic
│   ├── Model/          # Entities, Aggregates, Value Objects
│   ├── Repository/     # Repository interfaces (ports)
│   └── Event/          # Domain events
├── Application/         # Use cases
│   ├── Command/        # Write operations (DTOs + Handlers)
│   └── Query/          # Read operations (DTOs + Handlers)
├── Infrastructure/      # Technical implementations
│   ├── Persistence/    # Doctrine repositories, custom types
│   ├── Ohs/           # Open Host Service (for BC communication)
│   └── Validation/     # Framework validation
├── Integration/         # Anti-Corruption Layer (ACL)
└── Presentation/        # HTTP controllers, CLI commands
```

**Key Principles:**
- Domain layer: **NO framework dependencies** (no Symfony, no Doctrine)
- Application layer: **Single entry point** through Handlers
- Infrastructure layer: **Adapters** implementing domain ports
- Presentation layer: **Thin controllers** delegating to handlers

---

## 2. Domain Layer Patterns

### 2.1 Aggregates & Entities

**Pattern**: Rich domain models with factory methods

```php
class Order
{
    // Private constructor - enforce factory usage
    private function __construct(
        private readonly OrderId $id,
        private readonly TenantId $tenantId,
        private Money $amountToPay,
        private OrderStatus $status,
        // ...
    ) {
        $this->guardInvariants();
    }

    // Factory method - enforces invariants
    public static function create(
        string $id,
        Money $amountToPay,
        OrderLine ...$lines
    ): self {
        if ($amountToPay->toMinor() <= 0) {
            throw new NonPositiveOrderAmountException();
        }

        $order = new self(/* ... */);
        $order->recordEvent(new OrderCreated(/* ... */));

        return $order;
    }

    // Reconstitution from persistence
    public static function reconstituteFromPersistence(/* ... */): self {
        return new self(/* ... */);
    }

    // Business methods
    public function cancel(): void {
        if ($this->status === OrderStatus::SHIPPED) {
            throw new CannotCancelShippedOrderException();
        }
        $this->status = OrderStatus::CANCELLED;
        $this->recordEvent(new OrderCancelled(/* ... */));
    }

    // Invariant validation
    private function guardInvariants(): void {
        if ($this->amountToPay->toMinor() <= 0) {
            throw new DomainException('Order amount must be positive');
        }
    }
}
```

**Best Practices:**
- ✅ Private constructors + factory methods
- ✅ Invariants enforced in constructor
- ✅ Business methods (not setters)
- ✅ Domain events for side effects
- ✅ `reconstituteFromPersistence()` for repository use
- ❌ NO Doctrine attributes in domain models
- ❌ NO framework dependencies

### 2.2 Value Objects

**Pattern**: Immutable, self-validating objects

```php
final readonly class ProductName
{
    public function __construct(private string $value)
    {
        if (empty($value)) {
            throw new InvalidArgumentException('Product name cannot be empty');
        }
        if (strlen($value) > 255) {
            throw new InvalidArgumentException('Product name too long');
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(ProductName $other): bool
    {
        return $this->value === $other->value;
    }
}

final readonly class Money
{
    public function __construct(
        private int $amount,      // Store in cents/minor units
        private string $currency
    ) {
        if ($amount < 0) {
            throw new InvalidArgumentException('Amount cannot be negative');
        }
    }

    public static function fromMinor(int $amount, string $currency = 'USD'): self
    {
        return new self($amount, $currency);
    }

    public function toMinor(): int
    {
        return $this->amount;
    }

    public function equals(Money $other): bool
    {
        return $this->amount === $other->amount
            && $this->currency === $other->currency;
    }
}
```

**Best Practices:**
- ✅ Immutable (readonly properties)
- ✅ Validation in constructor
- ✅ Named constructors for clarity
- ✅ `equals()` method for comparison
- ✅ NO setters

---

## 3. Application Layer Patterns

### 3.1 Command Pattern (CQRS Write Side)

**Command (DTO):**
```php
final readonly class CreateProductCommand
{
    public function __construct(
        public ProductId $id,
        public TenantId $tenantId,
        public ProductName $name,
        public Money $price,
    ) {}
}
```

**Command Handler:**
```php
final class CreateProductCommandHandler
{
    public function __construct(
        private ProductRepositoryInterface $repository,
        private CommandValidatorInterface $validator,
        private TransactionRunnerInterface $transaction,
    ) {}

    public function __invoke(CreateProductCommand $command): Product
    {
        // 1. Validate command
        $this->validator->assert($command);

        // 2. Execute in transaction
        return $this->transaction->run(function() use ($command) {
            // 3. Create domain object
            $product = Product::create(
                id: $command->id,
                tenantId: $command->tenantId,
                name: $command->name,
                price: $command->price,
            );

            // 4. Persist
            $this->repository->add($product);

            return $product;
        });
    }
}
```

**Best Practices:**
- ✅ Command as DTO (data only, no logic)
- ✅ Handler validates, orchestrates, runs transaction
- ✅ Business logic in domain objects
- ✅ Returns domain object or void
- ❌ NO business logic in handler

### 3.2 Query Pattern (CQRS Read Side)

**Query:**
```php
final readonly class GetProductByIdQuery
{
    public function __construct(public ProductId $id) {}
}
```

**Query Handler:**
```php
final class GetProductByIdHandler
{
    public function __construct(
        private ProductRepositoryInterface $repository
    ) {}

    public function __invoke(GetProductByIdQuery $query): ?Product
    {
        return $this->repository->findById($query->id);
    }
}
```

**Best Practices:**
- ✅ Simple read operations
- ✅ Can return DTOs for read models
- ✅ NO business logic
- ✅ NO write operations

---

## 4. Infrastructure Layer Patterns

### 4.1 Dual-Model Pattern (Domain ↔ Doctrine)

**Domain Model (Pure PHP):**
```php
// src/Catalog/Domain/Model/Product.php
final class Product extends AggregateRoot
{
    private function __construct(
        private readonly ProductId $id,
        private ProductName $name,
        private Money $price,
        // ...
    ) {}

    public static function create(/* ... */): self { /* ... */ }
    public static function reconstituteFromPersistence(/* ... */): self { /* ... */ }
}
```

**Doctrine Entity (Infrastructure Adapter):**
```php
// src/Catalog/Infrastructure/Persistence/Doctrine/Entity/ProductEntity.php
#[ORM\Entity]
#[ORM\Table(name: 'products')]
#[ApiResource(/* ... */)]
class ProductEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'product_id')]
    private string $id;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'integer')]
    private int $priceAmount;

    // Conversion methods
    public static function fromDomainModel(Product $product): self
    {
        $entity = new self();
        $entity->id = $product->id()->toString();
        $entity->name = $product->name()->value();
        $entity->priceAmount = $product->price()->toMinor();
        return $entity;
    }

    public function toDomainModel(): Product
    {
        return Product::reconstituteFromPersistence(
            id: ProductId::fromString($this->id),
            name: ProductName::fromString($this->name),
            price: Money::fromMinor($this->priceAmount),
        );
    }
}
```

**Custom Doctrine Types:**
```php
// src/Catalog/Infrastructure/Persistence/Doctrine/Type/ProductIdType.php
final class ProductIdType extends Type
{
    public function convertToPHPValue($value, AbstractPlatform $platform): ?ProductId
    {
        return $value ? ProductId::fromString($value) : null;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        return $value instanceof ProductId ? $value->toString() : null;
    }
}
```

**Repository Adapter:**
```php
final readonly class DoctrineProductRepository implements ProductRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private MessageBusInterface $eventBus,
    ) {}

    public function save(Product $product): void
    {
        // Convert domain → entity
        $entity = ProductEntity::fromDomainModel($product);

        $this->em->persist($entity);
        $this->em->flush();

        // Dispatch domain events
        foreach ($product->popEvents() as $event) {
            $this->eventBus->dispatch($event);
        }
    }

    public function findById(ProductId $id): ?Product
    {
        $entity = $this->em->getRepository(ProductEntity::class)
            ->find($id->toString());

        // Convert entity → domain
        return $entity?->toDomainModel();
    }
}
```

---

## 5. Bounded Context Communication Patterns

### 5.1 Open Host Service (OHS)

**Contract (Published Language):**
```php
// src/Catalogue/Contracts/Reservation/CatalogueStockReservationPort.php
namespace App\Catalogue\Contracts\Reservation;

interface CatalogueStockReservationPort
{
    public function reserve(CatalogueReserveStockRequest $request): CatalogueReservationResult;
}
```

**OHS Implementation:**
```php
// src/Catalogue/Infrastructure/Ohs/CatalogueStockReservationService.php
final class CatalogueStockReservationService implements CatalogueStockReservationPort
{
    public function __construct(
        private ReserveStockCommandHandler $handler
    ) {}

    public function reserve(CatalogueReserveStockRequest $request): CatalogueReservationResult
    {
        try {
            $command = new ReserveStockCommand($request->items);
            ($this->handler)($command);
            return CatalogueReservationResult::ok();
        } catch (\Throwable $e) {
            return CatalogueReservationResult::fail($e->getMessage());
        }
    }
}
```

### 5.2 Anti-Corruption Layer (ACL)

**ACL Adapter:**
```php
// src/Order/Integration/Catalogue/StockReservationAdapter.php
final readonly class StockReservationAdapter implements StockReservationPort
{
    public function __construct(
        private CatalogueStockReservationPort $catalogueReservation
    ) {}

    public function reserve(ReservationRequest $request): ReservationResult
    {
        // Translate Order BC request → Catalogue BC request
        $catalogueRequest = new CatalogueReserveStockRequest(
            array_map(
                fn($i) => ['product_id' => $i['product_id'], 'quantity' => $i['quantity']],
                $request->items
            ),
            ['order_id' => $request->orderId]
        );

        // Call Catalogue OHS
        $result = $this->catalogueReservation->reserve($catalogueRequest);

        // Translate Catalogue BC response → Order BC response
        return $result->success
            ? ReservationResult::ok()
            : ReservationResult::fail($result->reason);
    }
}
```

**Key Rules:**
- ✅ BCs communicate only via Contracts (OHS/ACL)
- ✅ NO direct dependencies between domain models
- ✅ NO foreign keys across bounded contexts
- ✅ Cross-BC references stored as IDs/values only
- ✅ Each BC is source of truth for its data

---

## 6. Testing Patterns

### 6.1 Domain Tests (Pure Unit Tests)

```php
final class OrderTest extends TestCase
{
    public function test_create_order_with_positive_amount(): void
    {
        $order = Order::create(
            'ord-1',
            Money::fromMinor(1500),
            new OrderLine('p1', 'Product 1', 1, Money::fromMinor(1500)),
        );

        self::assertSame('ord-1', $order->getId());
        self::assertSame(1500, $order->getAmountToPay());
    }

    public function test_cannot_create_order_with_zero_amount(): void
    {
        $this->expectException(NonPositiveOrderAmountException::class);

        Order::create(
            'ord-1',
            Money::fromMinor(0),
        );
    }
}
```

**Best Practices:**
- ✅ Test business rules and invariants
- ✅ NO database, NO framework
- ✅ Fast execution
- ✅ Focus on domain logic

### 6.2 Application Tests (Handler Tests)

```php
final class CreateProductHandlerTest extends TestCase
{
    public function test_creates_product_successfully(): void
    {
        $repository = new InMemoryProductRepository();
        $validator = new InMemoryCommandValidator();
        $transaction = new InMemoryTransactionRunner();

        $handler = new CreateProductHandler($repository, $validator, $transaction);

        $command = new CreateProductCommand(
            id: ProductId::generate(),
            name: ProductName::fromString('Test Product'),
            price: Money::fromMinor(1000),
        );

        $product = $handler($command);

        self::assertNotNull($repository->findById($product->id()));
    }
}
```

**Best Practices:**
- ✅ Test use case execution
- ✅ Use in-memory adapters
- ✅ Verify validation, transactions, events
- ✅ Independent of infrastructure

### 6.3 Integration Tests

```php
final class DoctrineProductRepositoryTest extends KernelTestCase
{
    public function test_persists_and_retrieves_product(): void
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        $repository = new DoctrineProductRepository($em, $eventBus);

        $product = Product::create(/* ... */);
        $repository->save($product);

        $retrieved = $repository->findById($product->id());

        self::assertEquals($product->id(), $retrieved->id());
    }
}
```

---

## 7. Symfony Configuration Patterns

### 7.1 Messenger Configuration (CQRS Buses)

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        buses:
            command.bus:
                middleware:
                    - validation
                    - doctrine_transaction
            query.bus:
                middleware:
                    - validation
        routing:
            'App\*\Application\Command\*': command.bus
            'App\*\Application\Query\*': query.bus
```

### 7.2 Service Configuration

```yaml
# config/services.yaml
services:
    # Domain repository interfaces → Infrastructure implementations
    App\Catalog\Domain\Repository\ProductRepositoryInterface:
        class: App\Catalog\Infrastructure\Persistence\Doctrine\Repository\DoctrineProductRepository

    # Command handlers
    App\Catalog\Application\Command\:
        resource: '../src/Catalog/Application/Command/*Handler.php'
        tags:
            - { name: 'messenger.message_handler', bus: 'command.bus' }

    # Query handlers
    App\Catalog\Application\Query\:
        resource: '../src/Catalog/Application/Query/*Handler.php'
        tags:
            - { name: 'messenger.message_handler', bus: 'query.bus' }
```

---

## 8. Architecture Enforcement (Deptrac)

**Purpose**: Prevent architectural violations automatically

**Rules:**
- Domain can only depend on SharedKernel/Domain
- Application can depend on Domain + SharedKernel
- Infrastructure can depend on Application + Domain + Framework
- Presentation can only depend on Application
- BCs communicate only via Contracts

**Example Configuration:**
```yaml
# deptrac.yml
paths:
  - ./src
layers:
  - name: Domain
    collectors:
      - type: directory
        regex: src/.*/Domain/.*
  - name: Application
    collectors:
      - type: directory
        regex: src/.*/Application/.*
ruleset:
  Domain:
    - SharedKernel.Domain
  Application:
    - Domain
    - SharedKernel
  Infrastructure:
    - Application
    - Domain
```

**Usage:**
```bash
vendor/bin/deptrac analyze deptrac.yml
```

---

## Summary: Key Takeaways

1. **Domain Purity**: NO framework dependencies in domain layer
2. **Single Entry Point**: All use cases through Handlers
3. **Dual-Model**: Separate domain models from Doctrine entities
4. **CQRS**: Separate Command (write) from Query (read)
5. **Hexagonal**: Ports (interfaces) + Adapters (implementations)
6. **BC Isolation**: Communication via OHS/ACL, no direct dependencies
7. **Testing Pyramid**: Domain → Application → Integration → E2E
8. **Enforcement**: Use Deptrac to prevent violations

## When to Use DDD

✅ **Use DDD when:**
- High domain complexity
- Long-term project (2+ years)
- Business logic changes frequently
- Need for clear boundaries between teams

❌ **Don't use DDD when:**
- Simple CRUD application
- Rapid prototyping/MVP
- Small team without DDD experience
- Short-term project (< 6 months)
