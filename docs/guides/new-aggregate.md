# Guide: Implementing a New Aggregate

**Target Audience**: Developers
**Prerequisites**: Understanding of DDD, CQRS, Hexagonal Architecture
**Estimated Time**: 2-4 hours for a complete aggregate
**Based on**: Best practices from docs/articles/ and DDD_SYMFONY_TOOLING_INTEGRATION.md

---

## Overview

This guide walks you through implementing a complete DDD aggregate in the e-commerce platform, from domain model to API endpoint, following the dual-model pattern and architectural boundaries.

**What we'll build**: A `Product` aggregate in the `Catalog` bounded context

**Layers we'll implement**:
1. Domain Layer (Pure PHP)
2. Application Layer (Commands/Queries + Handlers)
3. Infrastructure Layer (Doctrine Entity, Repository, Custom Types)
4. Presentation Layer (API Platform integration)

---

## Step 1: Domain Layer (Pure Business Logic)

### 1.1 Value Objects

**Location**: `src/Catalog/Domain/ValueObject/`

Create immutable, self-validating value objects:

**ProductId.php:**
```php
<?php

declare(strict_types=1);

namespace App\Catalog\Domain\ValueObject;

use Symfony\Component\Uid\Ulid;

final readonly class ProductId
{
    private function __construct(private string $value)
    {
        if (!Ulid::isValid($value)) {
            throw new \InvalidArgumentException('Invalid ProductId format');
        }
    }

    public static function generate(): self
    {
        return new self((string) new Ulid());
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(ProductId $other): bool
    {
        return $this->value === $other->value;
    }
}
```

**ProductName.php:**
```php
<?php

declare(strict_types=1);

namespace App\Catalog\Domain\ValueObject;

final readonly class ProductName
{
    private function __construct(private string $value)
    {
        if (empty($value)) {
            throw new \InvalidArgumentException('Product name cannot be empty');
        }

        if (strlen($value) > 255) {
            throw new \InvalidArgumentException('Product name cannot exceed 255 characters');
        }
    }

    public static function fromString(string $value): self
    {
        return new self(trim($value));
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
```

**ProductStatus.php (Enum):**
```php
<?php

declare(strict_types=1);

namespace App\Catalog\Domain\ValueObject;

enum ProductStatus: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case ARCHIVED = 'archived';

    public function isDraft(): bool
    {
        return $this === self::DRAFT;
    }

    public function isPublished(): bool
    {
        return $this === self::PUBLISHED;
    }

    public function isArchived(): bool
    {
        return $this === self::ARCHIVED;
    }
}
```

### 1.2 Aggregate Root

**Location**: `src/Catalog/Domain/Model/Product.php`

```php
<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

use App\Catalog\Domain\Event\ProductCreated;
use App\Catalog\Domain\Event\ProductPublished;
use App\Catalog\Domain\Event\PriceChanged;
use App\Catalog\Domain\ValueObject\ProductId;
use App\Catalog\Domain\ValueObject\ProductName;
use App\Catalog\Domain\ValueObject\ProductStatus;
use App\SharedKernel\Domain\AggregateRoot;
use App\SharedKernel\Domain\ValueObject\Money;
use App\SharedKernel\Domain\ValueObject\TenantId;

final class Product extends AggregateRoot
{
    private function __construct(
        private readonly ProductId $id,
        private readonly TenantId $tenantId,
        private ProductName $name,
        private Money $price,
        private ProductStatus $status,
        private \DateTimeImmutable $createdAt,
        private ?\DateTimeImmutable $updatedAt = null,
    ) {
        $this->guardInvariants();
    }

    /**
     * Factory method: Create new product
     */
    public static function create(
        ProductId $id,
        TenantId $tenantId,
        ProductName $name,
        Money $price,
    ): self {
        $product = new self(
            id: $id,
            tenantId: $tenantId,
            name: $name,
            price: $price,
            status: ProductStatus::DRAFT,
            createdAt: new \DateTimeImmutable(),
        );

        $product->recordEvent(new ProductCreated(
            productId: $id,
            tenantId: $tenantId,
            name: $name,
            price: $price,
            occurredAt: new \DateTimeImmutable(),
        ));

        return $product;
    }

    /**
     * Business method: Change product price
     */
    public function changePrice(Money $newPrice): void
    {
        if ($this->price->equals($newPrice)) {
            return; // No change needed
        }

        if ($this->status->isArchived()) {
            throw new \DomainException('Cannot change price of archived product');
        }

        $oldPrice = $this->price;
        $this->price = $newPrice;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new PriceChanged(
            productId: $this->id,
            oldPrice: $oldPrice,
            newPrice: $newPrice,
            occurredAt: $this->updatedAt,
        ));
    }

    /**
     * Business method: Publish product
     */
    public function publish(): void
    {
        if ($this->status->isPublished()) {
            return;
        }

        if ($this->status->isArchived()) {
            throw new \DomainException('Cannot publish archived product');
        }

        $this->status = ProductStatus::PUBLISHED;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new ProductPublished(
            productId: $this->id,
            occurredAt: $this->updatedAt,
        ));
    }

    /**
     * Reconstitute aggregate from persistence
     * Used by repository when loading from database
     */
    public static function reconstituteFromPersistence(
        ProductId $id,
        TenantId $tenantId,
        ProductName $name,
        Money $price,
        ProductStatus $status,
        \DateTimeImmutable $createdAt,
        ?\DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            tenantId: $tenantId,
            name: $name,
            price: $price,
            status: $status,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    // Getters (read-only access)
    public function id(): ProductId { return $this->id; }
    public function tenantId(): TenantId { return $this->tenantId; }
    public function name(): ProductName { return $this->name; }
    public function price(): Money { return $this->price; }
    public function status(): ProductStatus { return $this->status; }
    public function createdAt(): \DateTimeImmutable { return $this->createdAt; }
    public function updatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }

    private function guardInvariants(): void
    {
        if ($this->price->amount() <= 0) {
            throw new \DomainException('Product price must be positive');
        }
    }
}
```

### 1.3 Domain Events

**Location**: `src/Catalog/Domain/Event/ProductCreated.php`

```php
<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Event;

use App\Catalog\Domain\ValueObject\ProductId;
use App\Catalog\Domain\ValueObject\ProductName;
use App\SharedKernel\Domain\DomainEvent;
use App\SharedKernel\Domain\ValueObject\Money;
use App\SharedKernel\Domain\ValueObject\TenantId;

final readonly class ProductCreated implements DomainEvent
{
    public function __construct(
        public ProductId $productId,
        public TenantId $tenantId,
        public ProductName $name,
        public Money $price,
        public \DateTimeImmutable $occurredAt,
    ) {}

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
```

### 1.4 Repository Interface (Port)

**Location**: `src/Catalog/Domain/Repository/ProductRepositoryInterface.php`

```php
<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Repository;

use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\ValueObject\ProductId;
use App\SharedKernel\Domain\ValueObject\TenantId;

interface ProductRepositoryInterface
{
    public function save(Product $product): void;

    public function findById(ProductId $id): ?Product;

    public function findByTenant(TenantId $tenantId): array;

    public function delete(Product $product): void;
}
```

---

## Step 2: Application Layer (Use Cases)

### 2.1 Command (Write Operation)

**Location**: `src/Catalog/Application/Command/CreateProduct.php`

```php
<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command;

use App\Catalog\Domain\ValueObject\ProductId;
use App\Catalog\Domain\ValueObject\ProductName;
use App\SharedKernel\Domain\ValueObject\Money;
use App\SharedKernel\Domain\ValueObject\TenantId;

final readonly class CreateProduct
{
    public function __construct(
        public ProductId $id,
        public TenantId $tenantId,
        public ProductName $name,
        public Money $price,
    ) {}
}
```

### 2.2 Command Handler

**Location**: `src/Catalog/Application/Command/CreateProductHandler.php`

```php
<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command;

use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\SharedKernel\Application\CommandValidatorInterface;
use App\SharedKernel\Application\TransactionRunnerInterface;

final readonly class CreateProductHandler
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private CommandValidatorInterface $commandValidator,
        private TransactionRunnerInterface $transactionRunner,
    ) {}

    public function __invoke(CreateProduct $command): Product
    {
        // 1. Validate command
        $this->commandValidator->assert($command);

        // 2. Execute in transaction
        return $this->transactionRunner->run(function () use ($command) {
            // 3. Create domain object
            $product = Product::create(
                id: $command->id,
                tenantId: $command->tenantId,
                name: $command->name,
                price: $command->price,
            );

            // 4. Persist
            $this->productRepository->save($product);

            return $product;
        });
    }
}
```

### 2.3 Query (Read Operation)

**Location**: `src/Catalog/Application/Query/GetProduct.php`

```php
<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query;

use App\Catalog\Domain\ValueObject\ProductId;

final readonly class GetProduct
{
    public function __construct(public ProductId $id) {}
}
```

### 2.4 Query Handler

**Location**: `src/Catalog/Application/Query/GetProductHandler.php`

```php
<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query;

use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;

final readonly class GetProductHandler
{
    public function __construct(
        private ProductRepositoryInterface $productRepository
    ) {}

    public function __invoke(GetProduct $query): ?Product
    {
        return $this->productRepository->findById($query->id);
    }
}
```

---

## Step 3: Infrastructure Layer

### 3.1 Doctrine Entity (Infrastructure Adapter)

**Location**: `src/Catalog/Infrastructure/Persistence/Doctrine/Entity/ProductEntity.php`

```php
<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Doctrine\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\ValueObject\ProductId;
use App\Catalog\Domain\ValueObject\ProductName;
use App\Catalog\Domain\ValueObject\ProductStatus;
use App\Catalog\Infrastructure\ApiPlatform\State\ProductProcessor;
use App\SharedKernel\Domain\ValueObject\Money;
use App\SharedKernel\Domain\ValueObject\TenantId;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'products')]
#[ORM\Index(columns: ['tenant_id', 'status'])]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(processor: ProductProcessor::class),
        new Put(processor: ProductProcessor::class),
        new Delete(processor: ProductProcessor::class),
    ],
    normalizationContext: ['groups' => ['product:read']],
    denormalizationContext: ['groups' => ['product:write']],
)]
class ProductEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26, unique: true)]
    private string $id;

    #[ORM\Column(type: 'string', length: 26)]
    private string $tenantId;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'integer')]
    private int $priceAmount;

    #[ORM\Column(type: 'string', length: 3)]
    private string $priceCurrency;

    #[ORM\Column(type: 'string', length: 20, enumType: ProductStatus::class)]
    private ProductStatus $status;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt;

    private function __construct() {}

    /**
     * Convert Domain Model → Doctrine Entity
     */
    public static function fromDomainModel(Product $product): self
    {
        $entity = new self();
        $entity->id = $product->id()->toString();
        $entity->tenantId = $product->tenantId()->toString();
        $entity->name = $product->name()->value();
        $entity->priceAmount = $product->price()->amount();
        $entity->priceCurrency = $product->price()->currency();
        $entity->status = $product->status();
        $entity->createdAt = $product->createdAt();
        $entity->updatedAt = $product->updatedAt();

        return $entity;
    }

    /**
     * Convert Doctrine Entity → Domain Model
     */
    public function toDomainModel(): Product
    {
        return Product::reconstituteFromPersistence(
            id: ProductId::fromString($this->id),
            tenantId: TenantId::fromString($this->tenantId),
            name: ProductName::fromString($this->name),
            price: Money::fromScalars($this->priceAmount, $this->priceCurrency),
            status: $this->status,
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt,
        );
    }

    // Getters for API Platform serialization
    public function getId(): string { return $this->id; }
    public function getTenantId(): string { return $this->tenantId; }
    public function getName(): string { return $this->name; }
    public function getPriceAmount(): int { return $this->priceAmount; }
    public function getPriceCurrency(): string { return $this->priceCurrency; }
    public function getStatus(): ProductStatus { return $this->status; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
}
```

### 3.2 Custom Doctrine Types

**Location**: `src/Catalog/Infrastructure/Persistence/Doctrine/Type/ProductIdType.php`

```php
<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Doctrine\Type;

use App\Catalog\Domain\ValueObject\ProductId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class ProductIdType extends Type
{
    private const NAME = 'product_id';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'VARCHAR(26)';
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?ProductId
    {
        return $value !== null ? ProductId::fromString($value) : null;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        return $value instanceof ProductId ? $value->toString() : null;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
```

**Register in `config/packages/doctrine.yaml`:**
```yaml
doctrine:
    dbal:
        types:
            product_id: App\Catalog\Infrastructure\Persistence\Doctrine\Type\ProductIdType
```

### 3.3 Repository Implementation

**Location**: `src/Catalog/Infrastructure/Persistence/Doctrine/Repository/DoctrineProductRepository.php`

```php
<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Doctrine\Repository;

use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\Catalog\Domain\ValueObject\ProductId;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\ProductEntity;
use App\SharedKernel\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class DoctrineProductRepository implements ProductRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $eventBus,
    ) {}

    public function save(Product $product): void
    {
        // Convert domain model → Doctrine entity
        $entity = ProductEntity::fromDomainModel($product);

        // Persist using Doctrine
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        // Dispatch domain events
        foreach ($product->popEvents() as $event) {
            $this->eventBus->dispatch($event);
        }
    }

    public function findById(ProductId $id): ?Product
    {
        /** @var ProductEntity|null $entity */
        $entity = $this->entityManager
            ->getRepository(ProductEntity::class)
            ->find($id->toString());

        // Convert Doctrine entity → domain model
        return $entity?->toDomainModel();
    }

    public function findByTenant(TenantId $tenantId): array
    {
        $entities = $this->entityManager
            ->getRepository(ProductEntity::class)
            ->findBy(['tenantId' => $tenantId->toString()]);

        // Convert array of entities → array of domain models
        return array_map(
            fn (ProductEntity $entity) => $entity->toDomainModel(),
            $entities
        );
    }

    public function delete(Product $product): void
    {
        $entity = $this->entityManager
            ->getRepository(ProductEntity::class)
            ->find($product->id()->toString());

        if ($entity !== null) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();
        }
    }
}
```

---

## Step 4: Presentation Layer (API Platform)

### 4.1 State Processor

**Location**: `src/Catalog/Infrastructure/ApiPlatform/State/ProductProcessor.php`

```php
<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Catalog\Application\Command\CreateProduct;
use App\Catalog\Application\Command\UpdateProduct;
use App\Catalog\Application\Command\DeleteProduct;
use App\Catalog\Domain\ValueObject\ProductId;
use App\Catalog\Domain\ValueObject\ProductName;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\ProductEntity;
use App\SharedKernel\Domain\ValueObject\Money;
use App\SharedKernel\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class ProductProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
    ) {}

    /**
     * @param ProductEntity $data
     */
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = []
    ): ProductEntity {
        $method = $operation->getMethod();

        return match ($method) {
            'POST' => $this->handleCreate($data),
            'PUT', 'PATCH' => $this->handleUpdate($data),
            'DELETE' => $this->handleDelete($data),
            default => throw new \LogicException("Unsupported method: {$method}"),
        };
    }

    private function handleCreate(ProductEntity $entity): ProductEntity
    {
        $command = new CreateProduct(
            id: ProductId::generate(),
            tenantId: TenantId::fromString($entity->getTenantId()),
            name: ProductName::fromString($entity->getName()),
            price: Money::fromScalars($entity->getPriceAmount(), $entity->getPriceCurrency()),
        );

        $this->commandBus->dispatch($command);

        return $entity;
    }

    private function handleUpdate(ProductEntity $entity): ProductEntity
    {
        $command = new UpdateProduct(
            id: ProductId::fromString($entity->getId()),
            name: ProductName::fromString($entity->getName()),
            price: Money::fromScalars($entity->getPriceAmount(), $entity->getPriceCurrency()),
        );

        $this->commandBus->dispatch($command);

        return $entity;
    }

    private function handleDelete(ProductEntity $entity): ProductEntity
    {
        $command = new DeleteProduct(
            id: ProductId::fromString($entity->getId()),
        );

        $this->commandBus->dispatch($command);

        return $entity;
    }
}
```

---

## Step 5: Configuration & Service Registration

### 5.1 Service Configuration

**File**: `config/services.yaml`

```yaml
services:
    # Domain Repository Interface → Infrastructure Implementation
    App\Catalog\Domain\Repository\ProductRepositoryInterface:
        class: App\Catalog\Infrastructure\Persistence\Doctrine\Repository\DoctrineProductRepository

    # Command Handlers
    App\Catalog\Application\Command\CreateProductHandler:
        tags:
            - { name: 'messenger.message_handler', bus: 'command.bus' }

    # Query Handlers
    App\Catalog\Application\Query\GetProductHandler:
        tags:
            - { name: 'messenger.message_handler', bus: 'query.bus' }

    # API Platform State Processor
    App\Catalog\Infrastructure\ApiPlatform\State\ProductProcessor:
        tags:
            - { name: 'api_platform.state_processor' }
```

### 5.2 Doctrine Mapping

**File**: `config/packages/doctrine.yaml`

```yaml
doctrine:
    dbal:
        types:
            product_id: App\Catalog\Infrastructure\Persistence\Doctrine\Type\ProductIdType
            tenant_id: App\SharedKernel\Infrastructure\Persistence\Doctrine\Type\TenantIdType

    orm:
        mappings:
            Catalog:
                is_bundle: false
                dir: '%kernel.project_dir%/src/Catalog/Infrastructure/Persistence/Doctrine/Entity'
                prefix: 'App\Catalog\Infrastructure\Persistence\Doctrine\Entity'
                alias: Catalog
```

---

## Step 6: Generate & Run Migration

```bash
# Generate migration
symfony console make:migration

# Review migration file in migrations/

# Run migration
symfony console doctrine:migrations:migrate
```

---

## Step 7: Testing

Create tests following the testing pyramid (see `docs/guides/testing-guide.md`):

1. **Unit tests** for Product aggregate (domain logic)
2. **Application tests** for CreateProductHandler (with in-memory adapters)
3. **Integration tests** for DoctrineProductRepository (real database)
4. **Functional tests** for API endpoints (HTTP)

### Multi-Tenancy Testing (RLS)

**IMPORTANT**: For integration and functional tests with multi-tenant aggregates, use `TenantTestTrait`.

```php
use App\Tests\Support\TenantTestTrait;

final class ProductRepositoryTest extends KernelTestCase
{
    use TenantTestTrait;

    protected function setUp(): void
    {
        parent::setUp();

        // Use default test tenant
        $this->tenantId = $this->getDefaultTenantId();

        // Set RLS context
        $this->setTenantContext($this->tenantId->toString());

        // Clean up test data
        $this->cleanupTestData();
    }
}
```

See `docs/guides/testing-guide.md` section 3.2 for complete examples.

---

## Checklist

Use this checklist when implementing a new aggregate:

### Domain Layer
- [ ] Value Objects created (with validation)
- [ ] Aggregate root created (with factory methods)
- [ ] Business methods implemented
- [ ] Domain events defined
- [ ] Repository interface defined
- [ ] Domain tests written (≥95% coverage)

### Application Layer
- [ ] Commands created (write operations)
- [ ] Command Handlers implemented
- [ ] Queries created (read operations)
- [ ] Query Handlers implemented
- [ ] Application tests written (≥90% coverage)

### Infrastructure Layer
- [ ] Doctrine Entity created (with conversion methods)
- [ ] Custom Doctrine Types created
- [ ] Repository implementation created
- [ ] Doctrine configuration added
- [ ] Integration tests written (≥70% coverage)

### Presentation Layer
- [ ] API Platform resource configured
- [ ] State Processor implemented
- [ ] Service configuration added
- [ ] Migration generated and run
- [ ] Functional tests written (≥60% coverage)

### Architecture Validation
- [ ] Deptrac validation passes (`vendor/bin/deptrac`)
- [ ] PHPStan level 8 passes (`vendor/bin/phpstan`)
- [ ] No framework dependencies in Domain layer
- [ ] Dual-model pattern correctly implemented
- [ ] Domain events dispatched after persistence

---

## Common Pitfalls to Avoid

❌ **DON'T**:
- Add Doctrine attributes to domain models
- Put business logic in Doctrine entities
- Use Doctrine entities outside infrastructure layer
- Skip domain event recording
- Bypass application layer (use handlers!)
- Mix read and write in same handler

✅ **DO**:
- Keep domain pure (no framework dependencies)
- Use factory methods in aggregates
- Validate in value object constructors
- Convert domain ↔ entity in repositories
- Dispatch domain events after persistence
- Test domain logic without framework

---

## Next Steps

After implementing your aggregate:

1. Review with team (code review)
2. Run Deptrac to verify boundaries
3. Check test coverage (≥80%)
4. Update API documentation
5. Add to CLAUDE.md if patterns differ

---

## References

- **Pattern Details**: `docs/architecture/ddd-patterns-summary.md`
- **Testing Guide**: `docs/technical/testing-guide.md`
- **Integration Guide**: `docs/technical/DDD_SYMFONY_TOOLING_INTEGRATION.md`
- **PRD**: `docs/business/ECOM_PRD_v5.1.md`
