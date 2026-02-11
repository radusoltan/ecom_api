# Flash Sale Feature - Complete Implementation Summary

## Implementation Status

###  Completed Components

1. **Domain Layer** - 100% Complete
   - `FlashSaleId` value object
   - `FlashSaleStatus` enum
   - `FlashSale` aggregate with full business logic
   - Domain events: `FlashSaleScheduled`, `FlashSaleActivated`, `FlashSaleEnded`, `FlashSaleCancelled`
   - `FlashSaleRepositoryInterface`

2. **Infrastructure Layer** - 100% Complete
   - `FlashSaleEntity` (Doctrine)
   - `DoctrineORMFlashSaleRepository`

3. **Application Layer - Messages** - 100% Complete
   - `ActivateFlashSaleMessage`
   - `DeactivateFlashSaleMessage`
   - `ActivateFlashSaleMessageHandler`
   - `DeactivateFlashSaleMessageHandler`

4. **Application Layer - Commands** - 100% Complete
   - `CreateFlashSaleCommand` + Handler
   - `CancelFlashSaleCommand` + Handler

### Remaining Components to Create

The following files need to be created to complete the implementation. I'll provide the complete code for each:

---

## 1. Queries (src/Pricing/Application/Query/)

### GetActiveFlashSales/GetActiveFlashSalesQuery.php
```php
<?php

declare(strict_types=1);

namespace App\Pricing\Application\Query\GetActiveFlashSales;

use App\Shared\Domain\ValueObject\TenantId;

final readonly class GetActiveFlashSalesQuery
{
    public function __construct(
        public TenantId $tenantId
    ) {
    }
}
```

### GetActiveFlashSales/GetActiveFlashSalesQueryHandler.php
```php
<?php

declare(strict_types=1);

namespace App\Pricing\Application\Query\GetActiveFlashSales;

use App\Pricing\Domain\Repository\FlashSaleRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetActiveFlashSalesQueryHandler
{
    public function __construct(
        private FlashSaleRepositoryInterface $flashSaleRepository
    ) {
    }

    public function __invoke(GetActiveFlashSalesQuery $query): array
    {
        return $this->flashSaleRepository->findActiveByTenant($query->tenantId);
    }
}
```

### GetUpcomingFlashSales/GetUpcomingFlashSalesQuery.php
```php
<?php

declare(strict_types=1);

namespace App\Pricing\Application\Query\GetUpcomingFlashSales;

use App\Shared\Domain\ValueObject\TenantId;

final readonly class GetUpcomingFlashSalesQuery
{
    public function __construct(
        public TenantId $tenantId
    ) {
    }
}
```

### GetUpcomingFlashSales/GetUpcomingFlashSalesQueryHandler.php
```php
<?php

declare(strict_types=1);

namespace App\Pricing\Application\Query\GetUpcomingFlashSales;

use App\Pricing\Domain\Repository\FlashSaleRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetUpcomingFlashSalesQueryHandler
{
    public function __construct(
        private FlashSaleRepositoryInterface $flashSaleRepository
    ) {
    }

    public function __invoke(GetUpcomingFlashSalesQuery $query): array
    {
        return $this->flashSaleRepository->findUpcoming($query->tenantId);
    }
}
```

### GetFlashSaleById/GetFlashSaleByIdQuery.php
```php
<?php

declare(strict_types=1);

namespace App\Pricing\Application\Query\GetFlashSaleById;

use App\Pricing\Domain\Model\FlashSaleId;
use App\Shared\Domain\ValueObject\TenantId;

final readonly class GetFlashSaleByIdQuery
{
    public function __construct(
        public FlashSaleId $flashSaleId,
        public TenantId $tenantId
    ) {
    }
}
```

### GetFlashSaleById/GetFlashSaleByIdQueryHandler.php
```php
<?php

declare(strict_types=1);

namespace App\Pricing\Application\Query\GetFlashSaleById;

use App\Pricing\Domain\Model\FlashSale;
use App\Pricing\Domain\Repository\FlashSaleRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetFlashSaleByIdQueryHandler
{
    public function __construct(
        private FlashSaleRepositoryInterface $flashSaleRepository
    ) {
    }

    public function __invoke(GetFlashSaleByIdQuery $query): FlashSale
    {
        $flashSale = $this->flashSaleRepository->findById($query->flashSaleId, $query->tenantId);

        if (null === $flashSale) {
            throw new NotFoundHttpException('Flash sale not found');
        }

        return $flashSale;
    }
}
```

---

## 2. API Platform State Processors/Providers (src/Pricing/Presentation/Api/)

Create directories:
```bash
mkdir -p src/Pricing/Presentation/Api/Processor
mkdir -p src/Pricing/Presentation/Api/Provider
```

### Processor/CreateFlashSaleProcessor.php
```php
<?php

declare(strict_types=1);

namespace App\Pricing\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Pricing\Application\Command\CreateFlashSale\CreateFlashSaleCommand;
use App\Pricing\Domain\Model\FlashSaleId;
use App\Pricing\Infrastructure\Persistence\Doctrine\Entity\FlashSaleEntity;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @implements ProcessorInterface<FlashSaleEntity>
 */
final readonly class CreateFlashSaleProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private RequestStack $requestStack
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): FlashSaleEntity
    {
        assert($data instanceof FlashSaleEntity);

        $request = $this->requestStack->getCurrentRequest();
        $tenantIdHeader = $request?->headers->get('X-Tenant-ID');

        if (null === $tenantIdHeader) {
            throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('X-Tenant-ID header is required');
        }

        $command = new CreateFlashSaleCommand(
            flashSaleId: FlashSaleId::generate(),
            tenantId: TenantId::fromString($tenantIdHeader),
            name: $data->getName(),
            productIds: $data->getProductIds(),
            discountType: $data->getDiscountType(),
            discountValue: $data->getDiscountValue(),
            startTime: $data->getStartTime()->format('c'),
            endTime: $data->getEndTime()->format('c')
        );

        $this->messageBus->dispatch($command);

        return $data;
    }
}
```

### Processor/CancelFlashSaleProcessor.php
```php
<?php

declare(strict_types=1);

namespace App\Pricing\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Pricing\Application\Command\CancelFlashSale\CancelFlashSaleCommand;
use App\Pricing\Domain\Model\FlashSaleId;
use App\Pricing\Infrastructure\Persistence\Doctrine\Entity\FlashSaleEntity;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @implements ProcessorInterface<FlashSaleEntity>
 */
final readonly class CancelFlashSaleProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private RequestStack $requestStack
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): FlashSaleEntity
    {
        assert($data instanceof FlashSaleEntity);

        $request = $this->requestStack->getCurrentRequest();
        $tenantIdHeader = $request?->headers->get('X-Tenant-ID');

        if (null === $tenantIdHeader) {
            throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('X-Tenant-ID header is required');
        }

        $command = new CancelFlashSaleCommand(
            flashSaleId: FlashSaleId::fromString($data->getId()),
            tenantId: TenantId::fromString($tenantIdHeader)
        );

        $this->messageBus->dispatch($command);

        return $data;
    }
}
```

### Provider/FlashSaleItemProvider.php
```php
<?php

declare(strict_types=1);

namespace App\Pricing\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Pricing\Application\Query\GetFlashSaleById\GetFlashSaleByIdQuery;
use App\Pricing\Domain\Model\FlashSaleId;
use App\Pricing\Infrastructure\Persistence\Doctrine\Entity\FlashSaleEntity;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @implements ProviderInterface<FlashSaleEntity>
 */
final readonly class FlashSaleItemProvider implements ProviderInterface
{
    use HandleTrait;

    public function __construct(
        private MessageBusInterface $messageBus,
        private RequestStack $requestStack
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?FlashSaleEntity
    {
        $request = $this->requestStack->getCurrentRequest();
        $tenantIdHeader = $request?->headers->get('X-Tenant-ID');

        if (null === $tenantIdHeader) {
            throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('X-Tenant-ID header is required');
        }

        $query = new GetFlashSaleByIdQuery(
            flashSaleId: FlashSaleId::fromString((string) $uriVariables['id']),
            tenantId: TenantId::fromString($tenantIdHeader)
        );

        $flashSale = $this->handle($query);

        return FlashSaleEntity::fromDomainModel($flashSale);
    }
}
```

### Provider/FlashSaleCollectionProvider.php
```php
<?php

declare(strict_types=1);

namespace App\Pricing\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Pricing\Application\Query\GetActiveFlashSales\GetActiveFlashSalesQuery;
use App\Pricing\Infrastructure\Persistence\Doctrine\Entity\FlashSaleEntity;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @implements ProviderInterface<FlashSaleEntity>
 */
final readonly class FlashSaleCollectionProvider implements ProviderInterface
{
    use HandleTrait;

    public function __construct(
        private MessageBusInterface $messageBus,
        private RequestStack $requestStack
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $tenantIdHeader = $request?->headers->get('X-Tenant-ID');

        if (null === $tenantIdHeader) {
            throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('X-Tenant-ID header is required');
        }

        $query = new GetActiveFlashSalesQuery(
            tenantId: TenantId::fromString($tenantIdHeader)
        );

        $flashSales = $this->handle($query);

        return array_map(
            fn ($flashSale) => FlashSaleEntity::fromDomainModel($flashSale),
            $flashSales
        );
    }
}
```

---

## 3. Event Subscribers (src/Pricing/Application/EventSubscriber/)

Create directory:
```bash
mkdir -p src/Pricing/Application/EventSubscriber
```

### FlashSaleActivatedSubscriber.php
```php
<?php

declare(strict_types=1);

namespace App\Pricing\Application\EventSubscriber;

use App\Pricing\Domain\Event\FlashSaleActivated;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class FlashSaleActivatedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            FlashSaleActivated::class => 'onFlashSaleActivated',
        ];
    }

    public function onFlashSaleActivated(FlashSaleActivated $event): void
    {
        $this->logger->info('Flash sale activated - ready for customer notifications', [
            'flash_sale_id' => $event->flashSaleId()->toString(),
            'tenant_id' => $event->tenantId()->toString(),
            'name' => $event->name(),
            'product_count' => count($event->productIds()),
        ]);

        // TODO: Send email notifications to subscribed customers
        // TODO: Send webhook notifications to external systems
        // TODO: Trigger push notifications for mobile apps
    }
}
```

### FlashSaleEndedSubscriber.php
```php
<?php

declare(strict_types=1);

namespace App\Pricing\Application\EventSubscriber;

use App\Pricing\Domain\Event\FlashSaleEnded;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class FlashSaleEndedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            FlashSaleEnded::class => 'onFlashSaleEnded',
        ];
    }

    public function onFlashSaleEnded(FlashSaleEnded $event): void
    {
        $this->logger->info('Flash sale ended', [
            'flash_sale_id' => $event->flashSaleId()->toString(),
            'tenant_id' => $event->tenantId()->toString(),
        ]);

        // TODO: Send "last chance" notifications if configured
        // TODO: Update cache/search indices
        // TODO: Trigger analytics events
    }
}
```

---

## 4. Database Migration

Run the following command to generate the migration:

```bash
cd /var/www/new_ecom/backend
symfony console make:migration
```

Then edit the generated migration to ensure it matches this schema:

```php
public function up(Schema $schema): void
{
    $this->addSql('CREATE TABLE flash_sales (
        id VARCHAR(36) NOT NULL,
        tenant_id VARCHAR(36) NOT NULL,
        name VARCHAR(100) NOT NULL,
        product_ids JSON NOT NULL,
        discount_type VARCHAR(20) NOT NULL,
        discount_value DOUBLE PRECISION NOT NULL,
        start_time TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
        end_time TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
        status VARCHAR(20) NOT NULL,
        created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
        updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
        PRIMARY KEY(id)
    )');

    $this->addSql('COMMENT ON COLUMN flash_sales.start_time IS \'(DC2Type:datetime_immutable)\'');
    $this->addSql('COMMENT ON COLUMN flash_sales.end_time IS \'(DC2Type:datetime_immutable)\'');
    $this->addSql('COMMENT ON COLUMN flash_sales.created_at IS \'(DC2Type:datetime_immutable)\'');
    $this->addSql('COMMENT ON COLUMN flash_sales.updated_at IS \'(DC2Type:datetime_immutable)\'');

    $this->addSql('CREATE INDEX idx_flash_sales_tenant_id ON flash_sales (tenant_id)');
    $this->addSql('CREATE INDEX idx_flash_sales_status ON flash_sales (status)');
    $this->addSql('CREATE INDEX idx_flash_sales_start_time ON flash_sales (start_time)');
    $this->addSql('CREATE INDEX idx_flash_sales_end_time ON flash_sales (end_time)');
    $this->addSql('CREATE INDEX idx_flash_sales_tenant_status ON flash_sales (tenant_id, status)');
    $this->addSql('CREATE INDEX idx_flash_sales_created_at ON flash_sales (created_at)');
}
```

---

## 5. Configuration Files

### config/packages/messenger.yaml

Add flash sale message routing:

```yaml
framework:
    messenger:
        transports:
            async: '%env(MESSENGER_TRANSPORT_DSN)%'

        routing:
            # Flash Sale Messages
            'App\Pricing\Application\Message\ActivateFlashSaleMessage': async
            'App\Pricing\Application\Message\DeactivateFlashSaleMessage': async
```

---

## 6. Service Configuration

Services are auto-configured via Symfony's autowiring. No manual configuration needed.

---

## Next Steps

1. Create query files in `src/Pricing/Application/Query/`
2. Create processor/provider files in `src/Pricing/Presentation/Api/`
3. Create event subscriber files in `src/Pricing/Application/EventSubscriber/`
4. Generate and run migration
5. Update messenger configuration
6. Write comprehensive tests
7. Run PHPStan and fix issues
8. Test API endpoints

---

## API Endpoints Summary

Once complete, these endpoints will be available:

```
POST   /api/v1/flash-sales                    Create flash sale
GET    /api/v1/flash-sales                     List all flash sales (active)
GET    /api/v1/flash-sales/{id}                Get flash sale details
PATCH  /api/v1/flash-sales/{id}/cancel        Cancel scheduled flash sale
DELETE /api/v1/flash-sales/{id}               Delete flash sale
```

All endpoints require `X-Tenant-ID` header.

---

## Testing Checklist

- [ ] Unit tests for FlashSale aggregate
- [ ] Unit tests for FlashSaleStatus enum
- [ ] Unit tests for command handlers
- [ ] Unit tests for query handlers
- [ ] Unit tests for message handlers
- [ ] Unit tests for event subscribers
- [ ] Integration tests for repository
- [ ] Integration tests for scheduler messages
- [ ] Functional tests for API endpoints
- [ ] PHPStan level 8 passes
- [ ] PHP-CS-Fixer passes

---

End of Implementation Summary
