<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\Command;

use App\Catalog\Application\Command\DefineOptionValue;
use App\Catalog\Application\Command\DefineOptionValueHandler;
use App\Catalog\Domain\Model\ConfigurableProduct;
use App\Catalog\Domain\Model\ConfigurableProductId;
use App\Catalog\Domain\Model\Option;
use App\Catalog\Domain\Model\OptionId;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Repository\ConfigurableProductRepositoryInterface;
use App\Catalog\Domain\ValueObject\LocalizedString;
use App\Catalog\Domain\ValueObject\OptionCode;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DefineOptionValueHandler::class)]
final class DefineOptionValueHandlerTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    private ConfigurableProductRepositoryInterface $configurableProductRepository;
    private DefineOptionValueHandler $handler;

    protected function setUp(): void
    {
        $this->configurableProductRepository = $this->createMock(ConfigurableProductRepositoryInterface::class);
        $this->handler = new DefineOptionValueHandler($this->configurableProductRepository);
    }

    // -----------------------------------------------------------------------
    // Happy path — adds option value to existing option
    // -----------------------------------------------------------------------

    #[Test]
    public function itAddsValueToExistingOption(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $productId = ProductId::generate();

        $configurableProduct = ConfigurableProduct::create(
            ConfigurableProductId::generate(),
            $productId,
            $tenantId
        );

        // Pre-add a 'color' option to the product
        $colorOption = Option::create(
            OptionId::generate(),
            OptionCode::fromString('color'),
            LocalizedString::fromArray(['en' => 'Color']),
            0
        );
        $configurableProduct->defineOption($colorOption);

        $this->configurableProductRepository
            ->expects(self::once())
            ->method('findByProductId')
            ->with($productId, $tenantId)
            ->willReturn($configurableProduct);

        $this->configurableProductRepository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(function (ConfigurableProduct $saved) {
                $options = $saved->getOptions();
                $colorOption = $options[0];

                return 1 === count($colorOption->getValues());
            }));

        $command = new DefineOptionValue(
            productId: $productId,
            tenantId: $tenantId,
            optionCode: 'color',
            valueCode: 'red',
            nameTranslations: ['en' => 'Red'],
            position: 0,
        );

        ($this->handler)($command);
    }

    // -----------------------------------------------------------------------
    // Throws when configurable product is not found
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsWhenConfigurableProductNotFound(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $productId = ProductId::generate();

        $this->configurableProductRepository
            ->expects(self::once())
            ->method('findByProductId')
            ->with($productId, $tenantId)
            ->willReturn(null);

        $this->configurableProductRepository
            ->expects(self::never())
            ->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Configurable product not found for product');

        ($this->handler)(new DefineOptionValue(
            productId: $productId,
            tenantId: $tenantId,
            optionCode: 'color',
            valueCode: 'red',
            nameTranslations: ['en' => 'Red'],
        ));
    }

    // -----------------------------------------------------------------------
    // Throws when option code is not found on the configurable product
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsWhenOptionCodeNotFound(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $productId = ProductId::generate();

        $configurableProduct = ConfigurableProduct::create(
            ConfigurableProductId::generate(),
            $productId,
            $tenantId
        );

        // Add 'size' option but not 'color'
        $sizeOption = Option::create(
            OptionId::generate(),
            OptionCode::fromString('size'),
            LocalizedString::fromArray(['en' => 'Size']),
            0
        );
        $configurableProduct->defineOption($sizeOption);

        $this->configurableProductRepository
            ->method('findByProductId')
            ->willReturn($configurableProduct);

        $this->configurableProductRepository
            ->expects(self::never())
            ->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Option with code "color" not found for product');

        ($this->handler)(new DefineOptionValue(
            productId: $productId,
            tenantId: $tenantId,
            optionCode: 'color', // Does not exist on this product
            valueCode: 'red',
            nameTranslations: ['en' => 'Red'],
        ));
    }

    // -----------------------------------------------------------------------
    // Adds value with custom position
    // -----------------------------------------------------------------------

    #[Test]
    public function itAddsValueWithCustomPosition(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $productId = ProductId::generate();

        $configurableProduct = ConfigurableProduct::create(
            ConfigurableProductId::generate(),
            $productId,
            $tenantId
        );

        $colorOption = Option::create(
            OptionId::generate(),
            OptionCode::fromString('color'),
            LocalizedString::fromArray(['en' => 'Color']),
            0
        );
        $configurableProduct->defineOption($colorOption);

        $this->configurableProductRepository
            ->method('findByProductId')
            ->willReturn($configurableProduct);

        $this->configurableProductRepository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(function (ConfigurableProduct $saved) {
                $options = $saved->getOptions();
                $values = $options[0]->getValues();

                return 1 === count($values) && 3 === $values[0]->getPosition();
            }));

        ($this->handler)(new DefineOptionValue(
            productId: $productId,
            tenantId: $tenantId,
            optionCode: 'color',
            valueCode: 'blue',
            nameTranslations: ['en' => 'Blue'],
            position: 3,
        ));
    }

    // -----------------------------------------------------------------------
    // Uses default position 0 when not specified
    // -----------------------------------------------------------------------

    #[Test]
    public function itUsesDefaultPositionZero(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $productId = ProductId::generate();

        $configurableProduct = ConfigurableProduct::create(
            ConfigurableProductId::generate(),
            $productId,
            $tenantId
        );

        $materialOption = Option::create(
            OptionId::generate(),
            OptionCode::fromString('material'),
            LocalizedString::fromArray(['en' => 'Material']),
            0
        );
        $configurableProduct->defineOption($materialOption);

        $this->configurableProductRepository
            ->method('findByProductId')
            ->willReturn($configurableProduct);

        $this->configurableProductRepository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(function (ConfigurableProduct $saved) {
                $values = $saved->getOptions()[0]->getValues();

                return 1 === count($values) && 0 === $values[0]->getPosition();
            }));

        // Default position (0) not explicitly provided
        ($this->handler)(new DefineOptionValue(
            productId: $productId,
            tenantId: $tenantId,
            optionCode: 'material',
            valueCode: 'cotton',
            nameTranslations: ['en' => 'Cotton'],
        ));
    }

    // -----------------------------------------------------------------------
    // Throws when duplicate value code added to option
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsWhenDuplicateValueCodeAdded(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $productId = ProductId::generate();

        $configurableProduct = ConfigurableProduct::create(
            ConfigurableProductId::generate(),
            $productId,
            $tenantId
        );

        $colorOption = Option::create(
            OptionId::generate(),
            OptionCode::fromString('color'),
            LocalizedString::fromArray(['en' => 'Color']),
            0
        );
        $configurableProduct->defineOption($colorOption);

        // Directly add 'red' to the option to simulate existing state
        $colorOption->addValue(
            \App\Catalog\Domain\Model\OptionValue::create(
                \App\Catalog\Domain\Model\OptionValueId::generate(),
                \App\Catalog\Domain\ValueObject\OptionValueCode::fromString('red'),
                LocalizedString::fromArray(['en' => 'Red']),
                0
            )
        );

        $this->configurableProductRepository
            ->expects(self::once())
            ->method('findByProductId')
            ->willReturn($configurableProduct);

        $this->configurableProductRepository
            ->expects(self::never())
            ->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Value with code "red" already exists for option "color"');

        ($this->handler)(new DefineOptionValue(
            productId: $productId,
            tenantId: $tenantId,
            optionCode: 'color',
            valueCode: 'red',
            nameTranslations: ['en' => 'Red Again'],
        ));
    }
}
