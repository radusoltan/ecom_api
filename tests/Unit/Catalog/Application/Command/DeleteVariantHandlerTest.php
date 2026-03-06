<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\Command;

use App\Catalog\Application\Command\DeleteVariant;
use App\Catalog\Application\Command\DeleteVariantHandler;
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

#[CoversClass(DeleteVariantHandler::class)]
final class DeleteVariantHandlerTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    private ConfigurableProductRepositoryInterface $configurableProductRepository;
    private DeleteVariantHandler $handler;

    protected function setUp(): void
    {
        $this->configurableProductRepository = $this->createMock(ConfigurableProductRepositoryInterface::class);
        $this->handler = new DeleteVariantHandler($this->configurableProductRepository);
    }

    // -----------------------------------------------------------------------
    // Helper — build a configurable product with one variant
    // -----------------------------------------------------------------------

    private function buildProductWithVariant(
        ProductId $productId,
        TenantId $tenantId,
        VariantId $variantId,
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
            Money::of('29.99', 'EUR'),
            Stock::create(10, true, false),
        );
        $product->addVariant($variant);

        return $product;
    }

    // -----------------------------------------------------------------------
    // Happy path — removes variant and saves configurable product
    // -----------------------------------------------------------------------

    #[Test]
    public function itRemovesVariantAndSavesConfigurableProduct(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $productId = ProductId::generate();
        $variantId = VariantId::generate();

        $configurableProduct = $this->buildProductWithVariant($productId, $tenantId, $variantId);

        $this->configurableProductRepository
            ->expects(self::once())
            ->method('findByProductId')
            ->with($productId, $tenantId)
            ->willReturn($configurableProduct);

        $this->configurableProductRepository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(function (ConfigurableProduct $saved) {
                return 0 === count($saved->getVariants());
            }));

        ($this->handler)(new DeleteVariant(
            variantId: $variantId,
            productId: $productId,
            tenantId: $tenantId,
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

        ($this->handler)(new DeleteVariant(
            variantId: VariantId::generate(),
            productId: $productId,
            tenantId: $tenantId,
        ));
    }

    // -----------------------------------------------------------------------
    // Throws when variant is not found on the configurable product
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsWhenVariantNotFoundOnProduct(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $productId = ProductId::generate();
        $existingVariantId = VariantId::generate();
        $nonExistentId = VariantId::generate();

        $configurableProduct = $this->buildProductWithVariant($productId, $tenantId, $existingVariantId);

        $this->configurableProductRepository
            ->method('findByProductId')
            ->willReturn($configurableProduct);

        $this->configurableProductRepository
            ->expects(self::never())
            ->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Variant');
        $this->expectExceptionMessage('not found for product');

        ($this->handler)(new DeleteVariant(
            variantId: $nonExistentId,
            productId: $productId,
            tenantId: $tenantId,
        ));
    }

    // -----------------------------------------------------------------------
    // Removes the correct variant when multiple variants exist
    // -----------------------------------------------------------------------

    #[Test]
    public function itRemovesCorrectVariantWhenMultipleExist(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $productId = ProductId::generate();

        $variantIdRed = VariantId::generate();
        $variantIdBlue = VariantId::generate();

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

        $redVariant = Variant::create(
            $variantIdRed,
            VariantSKU::fromString('TST-CLR-000001-red'),
            ['color' => 'red'],
            Money::of('29.99', 'EUR'),
            Stock::create(10, true, false),
        );
        $product->addVariant($redVariant);

        $blueVariant = Variant::create(
            $variantIdBlue,
            VariantSKU::fromString('TST-CLR-000001-blue'),
            ['color' => 'blue'],
            Money::of('29.99', 'EUR'),
            Stock::create(5, true, false),
        );
        $product->addVariant($blueVariant);

        $this->configurableProductRepository
            ->method('findByProductId')
            ->willReturn($product);

        $this->configurableProductRepository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(function (ConfigurableProduct $saved) use ($variantIdBlue) {
                $variants = array_values($saved->getVariants());

                // Only one variant remains and it is the blue one
                return 1 === count($variants)
                    && $variants[0]->getId()->equals($variantIdBlue);
            }));

        ($this->handler)(new DeleteVariant(
            variantId: $variantIdRed,
            productId: $productId,
            tenantId: $tenantId,
        ));
    }

    // -----------------------------------------------------------------------
    // Correct error message format contains variant and product IDs
    // -----------------------------------------------------------------------

    #[Test]
    public function itIncludesVariantAndProductIdInErrorMessageWhenNotFound(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $productId = ProductId::generate();
        $existingVariantId = VariantId::generate();
        $missingVariantId = VariantId::fromString('var_missing_id');

        $configurableProduct = $this->buildProductWithVariant($productId, $tenantId, $existingVariantId);

        $this->configurableProductRepository
            ->expects(self::once())
            ->method('findByProductId')
            ->willReturn($configurableProduct);

        $this->configurableProductRepository
            ->expects(self::never())
            ->method('save');

        try {
            ($this->handler)(new DeleteVariant(
                variantId: $missingVariantId,
                productId: $productId,
                tenantId: $tenantId,
            ));
            self::fail('Expected DomainException was not thrown');
        } catch (\DomainException $e) {
            self::assertStringContainsString('var_missing_id', $e->getMessage());
            self::assertStringContainsString($productId->toString(), $e->getMessage());
        }
    }
}
