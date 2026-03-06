<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\Command;

use App\Catalog\Application\Command\UpdateVariant;
use App\Catalog\Application\Command\UpdateVariantHandler;
use App\Catalog\Domain\Model\ConfigurableProduct;
use App\Catalog\Domain\Model\ConfigurableProductId;
use App\Catalog\Domain\Model\Option;
use App\Catalog\Domain\Model\OptionId;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\Stock;
use App\Catalog\Domain\Model\Variant;
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

#[CoversClass(UpdateVariantHandler::class)]
final class UpdateVariantHandlerTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    private ConfigurableProductRepositoryInterface $configurableProductRepository;
    private UpdateVariantHandler $handler;

    protected function setUp(): void
    {
        $this->configurableProductRepository = $this->createMock(ConfigurableProductRepositoryInterface::class);
        $this->handler = new UpdateVariantHandler($this->configurableProductRepository);
    }

    // -----------------------------------------------------------------------
    // Helper — build a configurable product with one variant
    // -----------------------------------------------------------------------

    private function buildProductWithVariant(
        ProductId $productId,
        TenantId $tenantId,
        VariantId $variantId,
        Money $price,
        Stock $stock,
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

        $variant = Variant::create(
            $variantId,
            VariantSKU::fromString('TST-CLR-000001-red'),
            ['color' => 'red'],
            $price,
            $stock,
        );
        $product->addVariant($variant);

        return $product;
    }

    // -----------------------------------------------------------------------
    // Happy path — updates price and saves
    // -----------------------------------------------------------------------

    #[Test]
    public function itUpdatesPriceAndSaves(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $productId = ProductId::generate();
        $variantId = VariantId::generate();

        $originalPrice = Money::of('29.99', 'EUR');
        $originalStock = Stock::create(10, true, false);

        $configurableProduct = $this->buildProductWithVariant(
            $productId,
            $tenantId,
            $variantId,
            $originalPrice,
            $originalStock,
        );

        $this->configurableProductRepository
            ->expects(self::once())
            ->method('findByProductId')
            ->with($productId, $tenantId)
            ->willReturn($configurableProduct);

        $this->configurableProductRepository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(function (ConfigurableProduct $saved) {
                $variants = $saved->getVariants();
                $variant = reset($variants);

                return 3999 === $variant->getPrice()->getAmount(); // 39.99 EUR in minor units
            }));

        $newPrice = Money::of('39.99', 'EUR');

        ($this->handler)(new UpdateVariant(
            variantId: $variantId,
            productId: $productId,
            tenantId: $tenantId,
            price: $newPrice,
        ));
    }

    // -----------------------------------------------------------------------
    // Happy path — updates stock quantity
    // -----------------------------------------------------------------------

    #[Test]
    public function itUpdatesStockQuantity(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $productId = ProductId::generate();
        $variantId = VariantId::generate();

        $configurableProduct = $this->buildProductWithVariant(
            $productId,
            $tenantId,
            $variantId,
            Money::of('29.99', 'EUR'),
            Stock::create(5, true, false),
        );

        $this->configurableProductRepository
            ->method('findByProductId')
            ->willReturn($configurableProduct);

        $this->configurableProductRepository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(function (ConfigurableProduct $saved) {
                $variants = $saved->getVariants();
                $variant = reset($variants);

                return 50 === $variant->getStock()->quantity();
            }));

        ($this->handler)(new UpdateVariant(
            variantId: $variantId,
            productId: $productId,
            tenantId: $tenantId,
            stockQuantity: 50,
        ));
    }

    // -----------------------------------------------------------------------
    // Happy path — deactivates variant
    // -----------------------------------------------------------------------

    #[Test]
    public function itDeactivatesVariant(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $productId = ProductId::generate();
        $variantId = VariantId::generate();

        $configurableProduct = $this->buildProductWithVariant(
            $productId,
            $tenantId,
            $variantId,
            Money::of('29.99', 'EUR'),
            Stock::create(10, true, false),
        );

        $this->configurableProductRepository
            ->method('findByProductId')
            ->willReturn($configurableProduct);

        $this->configurableProductRepository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(function (ConfigurableProduct $saved) {
                $variants = $saved->getVariants();
                $variant = reset($variants);

                return false === $variant->isActive();
            }));

        ($this->handler)(new UpdateVariant(
            variantId: $variantId,
            productId: $productId,
            tenantId: $tenantId,
            isActive: false,
        ));
    }

    // -----------------------------------------------------------------------
    // No-op when all nullable fields are null (nothing to update)
    // -----------------------------------------------------------------------

    #[Test]
    public function itDoesNotUpdateWhenAllFieldsAreNull(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $productId = ProductId::generate();
        $variantId = VariantId::generate();

        $configurableProduct = $this->buildProductWithVariant(
            $productId,
            $tenantId,
            $variantId,
            Money::of('29.99', 'EUR'),
            Stock::create(10, true, false),
        );

        $this->configurableProductRepository
            ->method('findByProductId')
            ->willReturn($configurableProduct);

        // Save is still called (no guard in handler for "nothing changed")
        $this->configurableProductRepository
            ->expects(self::once())
            ->method('save');

        // All nullable fields left as null — handler skips the update block
        ($this->handler)(new UpdateVariant(
            variantId: $variantId,
            productId: $productId,
            tenantId: $tenantId,
            // price, stockQuantity, isActive all null
        ));
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

        ($this->handler)(new UpdateVariant(
            variantId: VariantId::generate(),
            productId: $productId,
            tenantId: $tenantId,
            price: Money::of('9.99', 'EUR'),
        ));
    }

    // -----------------------------------------------------------------------
    // Throws when variant ID is not found on the configurable product
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsWhenVariantNotFoundOnProduct(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $productId = ProductId::generate();
        $existingVariantId = VariantId::generate();
        $wrongVariantId = VariantId::generate(); // Does not exist

        $configurableProduct = $this->buildProductWithVariant(
            $productId,
            $tenantId,
            $existingVariantId,
            Money::of('29.99', 'EUR'),
            Stock::create(10, true, false),
        );

        $this->configurableProductRepository
            ->method('findByProductId')
            ->willReturn($configurableProduct);

        $this->configurableProductRepository
            ->expects(self::never())
            ->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Variant');
        $this->expectExceptionMessage('not found for product');

        ($this->handler)(new UpdateVariant(
            variantId: $wrongVariantId,
            productId: $productId,
            tenantId: $tenantId,
            price: Money::of('9.99', 'EUR'),
        ));
    }

    // -----------------------------------------------------------------------
    // Updates multiple fields at once (price + stock + isActive)
    // -----------------------------------------------------------------------

    #[Test]
    public function itUpdatesMultipleFieldsSimultaneously(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $productId = ProductId::generate();
        $variantId = VariantId::generate();

        $configurableProduct = $this->buildProductWithVariant(
            $productId,
            $tenantId,
            $variantId,
            Money::of('29.99', 'EUR'),
            Stock::create(10, true, false),
        );

        $this->configurableProductRepository
            ->method('findByProductId')
            ->willReturn($configurableProduct);

        $this->configurableProductRepository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(function (ConfigurableProduct $saved) {
                $variants = $saved->getVariants();
                $variant = reset($variants);

                return 4999 === $variant->getPrice()->getAmount()
                    && 25 === $variant->getStock()->quantity()
                    && true === $variant->isActive();
            }));

        ($this->handler)(new UpdateVariant(
            variantId: $variantId,
            productId: $productId,
            tenantId: $tenantId,
            price: Money::of('49.99', 'EUR'),
            stockQuantity: 25,
            isActive: true,
        ));
    }

    // -----------------------------------------------------------------------
    // Preserves existing stock settings when only price is changed
    // -----------------------------------------------------------------------

    #[Test]
    public function itPreservesExistingStockSettingsWhenOnlyPriceChanged(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $productId = ProductId::generate();
        $variantId = VariantId::generate();

        $originalStock = Stock::create(42, false, true); // trackInventory=false, allowBackorder=true

        $configurableProduct = $this->buildProductWithVariant(
            $productId,
            $tenantId,
            $variantId,
            Money::of('29.99', 'EUR'),
            $originalStock,
        );

        $this->configurableProductRepository
            ->method('findByProductId')
            ->willReturn($configurableProduct);

        $this->configurableProductRepository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(function (ConfigurableProduct $saved) {
                $variants = $saved->getVariants();
                $variant = reset($variants);
                $stock = $variant->getStock();

                return false === $stock->trackInventory()
                    && true === $stock->allowBackorder()
                    && 42 === $stock->quantity();
            }));

        ($this->handler)(new UpdateVariant(
            variantId: $variantId,
            productId: $productId,
            tenantId: $tenantId,
            price: Money::of('59.99', 'EUR'), // Only price changes
        ));
    }
}
