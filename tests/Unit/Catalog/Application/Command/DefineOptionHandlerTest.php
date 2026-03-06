<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\Command;

use App\Catalog\Application\Command\DefineOption;
use App\Catalog\Application\Command\DefineOptionHandler;
use App\Catalog\Domain\Model\ConfigurableProduct;
use App\Catalog\Domain\Model\ConfigurableProductId;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Repository\ConfigurableProductRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(DefineOptionHandler::class)]
final class DefineOptionHandlerTest extends TestCase
{
    private ConfigurableProductRepositoryInterface $configurableProductRepository;
    private MessageBusInterface $eventBus;
    private DefineOptionHandler $handler;

    protected function setUp(): void
    {
        $this->configurableProductRepository = $this->createMock(ConfigurableProductRepositoryInterface::class);
        $this->eventBus = $this->createMock(MessageBusInterface::class);
        $this->handler = new DefineOptionHandler(
            $this->configurableProductRepository,
            $this->eventBus
        );
    }

    // ---------------------------------------------------------------------------
    // Creates new configurable product when it doesn't exist
    // ---------------------------------------------------------------------------

    #[Test]
    public function itCreatesNewConfigurableProductWhenNotFound(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $this->configurableProductRepository
            ->expects(self::once())
            ->method('findByProductId')
            ->with($productId, $tenantId)
            ->willReturn(null);

        $this->configurableProductRepository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(function (ConfigurableProduct $saved) {
                return 1 === count($saved->getOptions());
            }));

        $this->eventBus
            ->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(fn ($event) => new Envelope($event));

        $command = new DefineOption(
            productId: $productId,
            tenantId: $tenantId,
            code: 'color',
            nameTranslations: ['en' => 'Color', 'fr' => 'Couleur'],
            position: 0,
            values: []
        );

        ($this->handler)($command);
    }

    // ---------------------------------------------------------------------------
    // Uses existing configurable product when found
    // ---------------------------------------------------------------------------

    #[Test]
    public function itUsesExistingConfigurableProductWhenFound(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $existingProduct = ConfigurableProduct::create(
            ConfigurableProductId::generate(),
            $productId,
            $tenantId
        );

        $this->configurableProductRepository
            ->expects(self::once())
            ->method('findByProductId')
            ->willReturn($existingProduct);

        $this->configurableProductRepository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(function (ConfigurableProduct $saved) {
                // Same instance with the new option added
                return 1 === count($saved->getOptions());
            }));

        $this->eventBus
            ->method('dispatch')
            ->willReturnCallback(fn ($event) => new Envelope($event));

        $command = new DefineOption(
            productId: $productId,
            tenantId: $tenantId,
            code: 'size',
            nameTranslations: ['en' => 'Size'],
            position: 1,
            values: []
        );

        ($this->handler)($command);
    }

    // ---------------------------------------------------------------------------
    // Adds option values when provided
    // ---------------------------------------------------------------------------

    #[Test]
    public function itAddsOptionValuesWhenProvided(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $this->configurableProductRepository
            ->method('findByProductId')
            ->willReturn(null);

        $this->configurableProductRepository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(function (ConfigurableProduct $saved) {
                $options = $saved->getOptions();
                if (1 !== count($options)) {
                    return false;
                }

                return 3 === count($options[0]->getValues());
            }));

        $this->eventBus
            ->method('dispatch')
            ->willReturnCallback(fn ($event) => new Envelope($event));

        $command = new DefineOption(
            productId: $productId,
            tenantId: $tenantId,
            code: 'color',
            nameTranslations: ['en' => 'Color'],
            position: 0,
            values: [
                ['code' => 'red', 'nameTranslations' => ['en' => 'Red'], 'position' => 0],
                ['code' => 'green', 'nameTranslations' => ['en' => 'Green'], 'position' => 1],
                ['code' => 'blue', 'nameTranslations' => ['en' => 'Blue'], 'position' => 2],
            ]
        );

        ($this->handler)($command);
    }

    // ---------------------------------------------------------------------------
    // Dispatches OptionDefined event
    // ---------------------------------------------------------------------------

    #[Test]
    public function itDispatchesOptionDefinedEvent(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $this->configurableProductRepository
            ->method('findByProductId')
            ->willReturn(null);

        $this->configurableProductRepository
            ->method('save');

        $dispatchedEvents = [];
        $this->eventBus
            ->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(function ($event) use (&$dispatchedEvents) {
                $dispatchedEvents[] = $event;

                return new Envelope($event);
            });

        $command = new DefineOption(
            productId: $productId,
            tenantId: $tenantId,
            code: 'material',
            nameTranslations: ['en' => 'Material'],
            position: 2,
            values: []
        );

        ($this->handler)($command);

        self::assertCount(1, $dispatchedEvents);
        self::assertInstanceOf(\App\Catalog\Domain\Event\OptionDefined::class, $dispatchedEvents[0]);
    }

    // ---------------------------------------------------------------------------
    // Option with default position (0)
    // ---------------------------------------------------------------------------

    #[Test]
    public function itUsesDefaultPositionZero(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $this->configurableProductRepository
            ->method('findByProductId')
            ->willReturn(null);

        $this->configurableProductRepository
            ->expects(self::once())
            ->method('save');

        $this->eventBus
            ->method('dispatch')
            ->willReturnCallback(fn ($event) => new Envelope($event));

        // Default position = 0
        $command = new DefineOption(
            productId: $productId,
            tenantId: $tenantId,
            code: 'width',
            nameTranslations: ['en' => 'Width'],
        );

        ($this->handler)($command);
    }

    // ---------------------------------------------------------------------------
    // Option value with default position
    // ---------------------------------------------------------------------------

    #[Test]
    public function itUsesDefaultPositionForOptionValueWhenNotProvided(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $this->configurableProductRepository
            ->method('findByProductId')
            ->willReturn(null);

        $this->configurableProductRepository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(function (ConfigurableProduct $saved) {
                $options = $saved->getOptions();
                $values = $options[0]->getValues();

                // position key not provided — should default to 0
                return 0 === $values[0]->getPosition();
            }));

        $this->eventBus
            ->method('dispatch')
            ->willReturnCallback(fn ($event) => new Envelope($event));

        $command = new DefineOption(
            productId: $productId,
            tenantId: $tenantId,
            code: 'finish',
            nameTranslations: ['en' => 'Finish'],
            position: 0,
            values: [
                // No 'position' key — handler should use ?? 0
                ['code' => 'matte', 'nameTranslations' => ['en' => 'Matte']],
            ]
        );

        ($this->handler)($command);
    }
}
