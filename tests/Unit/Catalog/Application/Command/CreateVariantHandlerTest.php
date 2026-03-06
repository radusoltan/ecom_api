<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\Command;

use App\Catalog\Application\Command\CreateVariant;
use App\Catalog\Application\Command\CreateVariantHandler;
use App\Catalog\Domain\Model\ConfigurableProduct;
use App\Catalog\Domain\Model\ConfigurableProductId;
use App\Catalog\Domain\Model\Option;
use App\Catalog\Domain\Model\OptionId;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\VariantId;
use App\Catalog\Domain\Repository\ConfigurableProductRepositoryInterface;
use App\Catalog\Domain\ValueObject\LocalizedString;
use App\Catalog\Domain\ValueObject\OptionCode;
use App\Catalog\Domain\ValueObject\VariantSKU;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreateVariantHandler::class)]
final class CreateVariantHandlerTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    private ConfigurableProductRepositoryInterface $configurableProductRepository;
    private CreateVariantHandler $handler;

    protected function setUp(): void
    {
        $this->configurableProductRepository = $this->createMock(ConfigurableProductRepositoryInterface::class);
        $this->handler = new CreateVariantHandler($this->configurableProductRepository);
    }

    // -----------------------------------------------------------------------
    // Helper — build a configurable product with a 'color' option
    // -----------------------------------------------------------------------

    private function buildConfigurableProductWithColorOption(
        ProductId $productId,
        TenantId $tenantId,
    ): ConfigurableProduct {
        $product = ConfigurableProduct::create(
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
        $product->defineOption($colorOption);

        return $product;
    }

    // -----------------------------------------------------------------------
    // Happy path — creates variant and saves to repository
    // -----------------------------------------------------------------------

    #[Test]
    public function itCreatesVariantAndSavesConfigurableProduct(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $productId = ProductId::generate();
        $variantId = VariantId::generate();

        $configurableProduct = $this->buildConfigurableProductWithColorOption($productId, $tenantId);

        $this->configurableProductRepository
            ->expects(self::once())
            ->method('findByProductId')
            ->with($productId, $tenantId)
            ->willReturn($configurableProduct);

        $this->configurableProductRepository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(function (ConfigurableProduct $saved) {
                return 1 === count($saved->getVariants());
            }));

        $command = new CreateVariant(
            variantId: $variantId,
            productId: $productId,
            tenantId: $tenantId,
            sku: VariantSKU::fromString('TST-CLR-000001-red'),
            optionValueMap: ['color' => 'red'],
            price: Money::of('29.99', 'EUR'),
            stockQuantity: 10,
            trackInventory: true,
            allowBackorder: false,
            isActive: true,
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

        ($this->handler)(new CreateVariant(
            variantId: VariantId::generate(),
            productId: $productId,
            tenantId: $tenantId,
            sku: VariantSKU::fromString('TST-CLR-000001-red'),
            optionValueMap: ['color' => 'red'],
            price: Money::of('29.99', 'EUR'),
            stockQuantity: 10,
        ));
    }

    // -----------------------------------------------------------------------
    // Throws when variant option structure does not match product options
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsWhenVariantOptionsDoNotMatchProductOptions(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $productId = ProductId::generate();

        // Product has 'color' option, but variant uses 'size'
        $configurableProduct = $this->buildConfigurableProductWithColorOption($productId, $tenantId);

        $this->configurableProductRepository
            ->method('findByProductId')
            ->willReturn($configurableProduct);

        $this->configurableProductRepository
            ->expects(self::never())
            ->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Variant option structure does not match product options');

        ($this->handler)(new CreateVariant(
            variantId: VariantId::generate(),
            productId: $productId,
            tenantId: $tenantId,
            sku: VariantSKU::fromString('TST-SIZ-000001-xl'),
            optionValueMap: ['size' => 'xl'], // Wrong option key
            price: Money::of('29.99', 'EUR'),
            stockQuantity: 5,
        ));
    }

    // -----------------------------------------------------------------------
    // Throws when duplicate variant combination already exists
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsWhenDuplicateVariantCombinationExists(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $productId = ProductId::generate();

        $configurableProduct = $this->buildConfigurableProductWithColorOption($productId, $tenantId);

        // Add first variant with 'color' => 'red'
        $firstVariant = \App\Catalog\Domain\Model\Variant::create(
            VariantId::generate(),
            VariantSKU::fromString('TST-CLR-000001-red'),
            ['color' => 'red'],
            Money::of('29.99', 'EUR'),
            \App\Catalog\Domain\Model\Stock::create(10, true, false),
        );
        $configurableProduct->addVariant($firstVariant);

        $this->configurableProductRepository
            ->method('findByProductId')
            ->willReturn($configurableProduct);

        $this->configurableProductRepository
            ->expects(self::never())
            ->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Variant with this option combination already exists');

        // Try to add another variant with same 'color' => 'red' combination
        ($this->handler)(new CreateVariant(
            variantId: VariantId::generate(),
            productId: $productId,
            tenantId: $tenantId,
            sku: VariantSKU::fromString('TST-CLR-000002-red'),
            optionValueMap: ['color' => 'red'], // Duplicate combination
            price: Money::of('39.99', 'EUR'),
            stockQuantity: 5,
        ));
    }

    // -----------------------------------------------------------------------
    // Creates inactive variant when isActive is false
    // -----------------------------------------------------------------------

    #[Test]
    public function itCreatesInactiveVariant(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $productId = ProductId::generate();

        $configurableProduct = $this->buildConfigurableProductWithColorOption($productId, $tenantId);

        $this->configurableProductRepository
            ->method('findByProductId')
            ->willReturn($configurableProduct);

        $this->configurableProductRepository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(function (ConfigurableProduct $saved) {
                $variants = $saved->getVariants();

                return 1 === count($variants) && !$variants[0]->isActive();
            }));

        ($this->handler)(new CreateVariant(
            variantId: VariantId::generate(),
            productId: $productId,
            tenantId: $tenantId,
            sku: VariantSKU::fromString('TST-CLR-000001-blue'),
            optionValueMap: ['color' => 'blue'],
            price: Money::of('19.99', 'EUR'),
            stockQuantity: 0,
            isActive: false,
        ));
    }

    // -----------------------------------------------------------------------
    // Creates variant with stock tracking disabled (allow backorder)
    // -----------------------------------------------------------------------

    #[Test]
    public function itCreatesVariantWithBackorderEnabled(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $productId = ProductId::generate();

        $configurableProduct = $this->buildConfigurableProductWithColorOption($productId, $tenantId);

        $this->configurableProductRepository
            ->method('findByProductId')
            ->willReturn($configurableProduct);

        $this->configurableProductRepository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(function (ConfigurableProduct $saved) {
                $variants = $saved->getVariants();
                $stock = $variants[0]->getStock();

                return 1 === count($variants)
                    && true === $stock->allowBackorder()
                    && true === $stock->trackInventory();
            }));

        ($this->handler)(new CreateVariant(
            variantId: VariantId::generate(),
            productId: $productId,
            tenantId: $tenantId,
            sku: VariantSKU::fromString('TST-CLR-000001-green'),
            optionValueMap: ['color' => 'green'],
            price: Money::of('49.99', 'EUR'),
            stockQuantity: 0,
            trackInventory: true,
            allowBackorder: true,
        ));
    }
}
