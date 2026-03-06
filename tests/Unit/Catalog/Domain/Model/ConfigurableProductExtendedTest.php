<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\Model;

// Both ConfigurableProduct and ConfigurableProductId live in this file.
require_once __DIR__.'/../../../../../src/Catalog/Domain/Model/ConfigurableProduct.php';
require_once __DIR__.'/../../../../../src/Catalog/Domain/Model/Option.php';
require_once __DIR__.'/../../../../../src/Catalog/Domain/Model/Variant.php';

use App\Catalog\Domain\Model\ConfigurableProduct;
use App\Catalog\Domain\Model\ConfigurableProductId;
use App\Catalog\Domain\Model\Option;
use App\Catalog\Domain\Model\OptionId;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\Stock;
use App\Catalog\Domain\Model\Variant;
use App\Catalog\Domain\Model\VariantId;
use App\Catalog\Domain\ValueObject\LocalizedString;
use App\Catalog\Domain\ValueObject\OptionCode;
use App\Catalog\Domain\ValueObject\VariantSKU;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfigurableProduct::class)]
#[CoversClass(ConfigurableProductId::class)]
final class ConfigurableProductExtendedTest extends TestCase
{
    private ConfigurableProductId $configurableProductId;
    private ProductId $productId;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->configurableProductId = ConfigurableProductId::fromString('cfgprod_test_001');
        $this->productId = ProductId::generate();
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
    }

    // -------------------------------------------------------------------
    // ConfigurableProductId
    // -------------------------------------------------------------------

    #[Test]
    public function itCreatesConfigurableProductIdFromString(): void
    {
        $id = ConfigurableProductId::fromString('cfgprod_abc');

        self::assertSame('cfgprod_abc', $id->toString());
        self::assertSame('cfgprod_abc', $id->value());
        self::assertSame('cfgprod_abc', (string) $id);
    }

    #[Test]
    public function itGeneratesUniqueConfigurableProductId(): void
    {
        $id1 = ConfigurableProductId::generate();
        $id2 = ConfigurableProductId::generate();

        self::assertNotSame($id1->toString(), $id2->toString());
        self::assertStringStartsWith('cfgprod_', $id1->toString());
    }

    #[Test]
    public function itComparesConfigurableProductIdsForEquality(): void
    {
        $id1 = ConfigurableProductId::fromString('same-id');
        $id2 = ConfigurableProductId::fromString('same-id');
        $id3 = ConfigurableProductId::fromString('different-id');

        self::assertTrue($id1->equals($id2));
        self::assertFalse($id1->equals($id3));
    }

    #[Test]
    public function itThrowsWhenConfigurableProductIdIsEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ConfigurableProduct ID cannot be empty');

        ConfigurableProductId::fromString('');
    }

    // -------------------------------------------------------------------
    // reconstituteFromPersistence()
    // -------------------------------------------------------------------

    #[Test]
    public function itReconstituteFromPersistenceWithOptionsAndVariants(): void
    {
        $now = new \DateTimeImmutable('2025-01-01');
        $colorOption = $this->makeOption('color', 1);
        $sizeOption = $this->makeOption('size', 2);
        $variant = Variant::create(
            VariantId::generate(),
            VariantSKU::fromString('SKU-TST-000001-red-small'),
            ['color' => 'red', 'size' => 'small'],
            Money::of('10.00', 'USD'),
            Stock::create(10, true, false),
        );

        $product = ConfigurableProduct::reconstituteFromPersistence(
            id: $this->configurableProductId,
            productId: $this->productId,
            tenantId: $this->tenantId,
            options: [$colorOption, $sizeOption],
            variants: [$variant],
            createdAt: $now,
            updatedAt: $now,
        );

        self::assertCount(2, $product->getOptions());
        self::assertCount(1, $product->getVariants());
        self::assertSame($now, $product->getCreatedAt());
        self::assertSame($now, $product->getUpdatedAt());
    }

    // -------------------------------------------------------------------
    // removeOption()
    // -------------------------------------------------------------------

    #[Test]
    public function itRemovesOptionAndAssociatedVariants(): void
    {
        $product = $this->createProductWithColorAndSize();
        $colorCode = OptionCode::fromString('color');

        // Add a variant before removing option
        $product->addVariant(Variant::create(
            VariantId::generate(),
            VariantSKU::fromString('SKU-TST-000001-red-small'),
            ['color' => 'red', 'size' => 'small'],
            Money::of('10.00', 'USD'),
            Stock::create(5, true, false),
        ));

        self::assertCount(1, $product->getVariants());

        $product->popEvents();
        $product->removeOption($colorCode);

        // Option removed, and variant that used 'color' also removed
        self::assertFalse($product->hasOption($colorCode));
        self::assertCount(0, $product->getVariants());
    }

    #[Test]
    public function itThrowsWhenRemovingNonExistentOption(): void
    {
        $product = ConfigurableProduct::create(
            $this->configurableProductId,
            $this->productId,
            $this->tenantId,
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('does not exist');

        $product->removeOption(OptionCode::fromString('nonexistent'));
    }

    #[Test]
    public function itThrowsWhenAddingDuplicateOptionCode(): void
    {
        $product = ConfigurableProduct::create(
            $this->configurableProductId,
            $this->productId,
            $this->tenantId,
        );

        $option = $this->makeOption('color', 1);
        $product->defineOption($option);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('already exists');

        $product->defineOption($this->makeOption('color', 2));
    }

    // -------------------------------------------------------------------
    // removeVariant()
    // -------------------------------------------------------------------

    #[Test]
    public function itRemovesVariantById(): void
    {
        $product = $this->createProductWithColorAndSize();
        $variantId = VariantId::generate();

        $product->addVariant(Variant::create(
            $variantId,
            VariantSKU::fromString('SKU-TST-000002-blue-xl'),
            ['color' => 'blue', 'size' => 'xl'],
            Money::of('15.00', 'USD'),
            Stock::create(5, true, false),
        ));

        self::assertCount(1, $product->getVariants());

        $product->popEvents();
        $product->removeVariant($variantId);

        self::assertCount(0, $product->getVariants());
    }

    #[Test]
    public function itThrowsWhenRemovingNonExistentVariant(): void
    {
        $product = ConfigurableProduct::create(
            $this->configurableProductId,
            $this->productId,
            $this->tenantId,
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Variant not found');

        $product->removeVariant(VariantId::fromString('nonexistent'));
    }

    #[Test]
    public function itThrowsWhenVariantDoesNotMatchOptions(): void
    {
        $product = $this->createProductWithColorAndSize();

        // Variant has only 'color', not 'size'
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Variant option structure does not match product options');

        $product->addVariant(Variant::create(
            VariantId::generate(),
            VariantSKU::fromString('SKU-TST-000003-red-na'),
            ['color' => 'red'],
            Money::of('10.00', 'USD'),
            Stock::create(5, true, false),
        ));
    }

    // -------------------------------------------------------------------
    // getOption() / hasOption() / getVariant()
    // -------------------------------------------------------------------

    #[Test]
    public function itGetsOptionByCode(): void
    {
        $product = $this->createProductWithColorAndSize();
        $colorCode = OptionCode::fromString('color');

        $option = $product->getOption($colorCode);

        self::assertNotNull($option);
        self::assertTrue($option->getCode()->equals($colorCode));
    }

    #[Test]
    public function itReturnsNullForNonExistentOption(): void
    {
        $product = ConfigurableProduct::create(
            $this->configurableProductId,
            $this->productId,
            $this->tenantId,
        );

        self::assertNull($product->getOption(OptionCode::fromString('nonexistent')));
    }

    #[Test]
    public function itGetsVariantById(): void
    {
        $product = $this->createProductWithColorAndSize();
        $variantId = VariantId::generate();

        $product->addVariant(Variant::create(
            $variantId,
            VariantSKU::fromString('SKU-TST-000004-red-sm'),
            ['color' => 'red', 'size' => 'small'],
            Money::of('10.00', 'USD'),
            Stock::create(5, true, false),
        ));

        $found = $product->getVariant($variantId);
        self::assertNotNull($found);
        self::assertTrue($found->getId()->equals($variantId));
    }

    #[Test]
    public function itReturnsNullForNonExistentVariant(): void
    {
        $product = ConfigurableProduct::create(
            $this->configurableProductId,
            $this->productId,
            $this->tenantId,
        );

        self::assertNull($product->getVariant(VariantId::fromString('nonexistent')));
    }

    // -------------------------------------------------------------------
    // findVariantByCombination()
    // -------------------------------------------------------------------

    #[Test]
    public function itFindsVariantByCombination(): void
    {
        $product = $this->createProductWithColorAndSize();
        $variantId = VariantId::generate();

        $product->addVariant(Variant::create(
            $variantId,
            VariantSKU::fromString('SKU-TST-000005-grn-md'),
            ['color' => 'green', 'size' => 'medium'],
            Money::of('12.00', 'USD'),
            Stock::create(3, true, false),
        ));

        $found = $product->findVariantByCombination(['color' => 'green', 'size' => 'medium']);
        self::assertNotNull($found);
        self::assertTrue($found->getId()->equals($variantId));
    }

    #[Test]
    public function itReturnsNullWhenCombinationNotFound(): void
    {
        $product = $this->createProductWithColorAndSize();

        $result = $product->findVariantByCombination(['color' => 'purple', 'size' => 'huge']);
        self::assertNull($result);
    }

    // -------------------------------------------------------------------
    // getActiveVariants()
    // -------------------------------------------------------------------

    #[Test]
    public function itReturnsOnlyActiveVariants(): void
    {
        $product = $this->createProductWithColorAndSize();

        $activeId = VariantId::generate();
        $inactiveId = VariantId::generate();

        $product->addVariant(Variant::create(
            $activeId,
            VariantSKU::fromString('SKU-TST-000006-red-sm'),
            ['color' => 'red', 'size' => 'small'],
            Money::of('10.00', 'USD'),
            Stock::create(5, true, false),
            true,
        ));

        $product->addVariant(Variant::create(
            $inactiveId,
            VariantSKU::fromString('SKU-TST-000007-blu-lg'),
            ['color' => 'blue', 'size' => 'large'],
            Money::of('10.00', 'USD'),
            Stock::create(5, true, false),
            false,
        ));

        $activeVariants = $product->getActiveVariants();
        self::assertCount(1, $activeVariants);
        $first = reset($activeVariants);
        self::assertTrue($first->getId()->equals($activeId));
    }

    // -------------------------------------------------------------------
    // getSortedOptions()
    // -------------------------------------------------------------------

    #[Test]
    public function itReturnsSortedOptionsByPosition(): void
    {
        $product = ConfigurableProduct::create(
            $this->configurableProductId,
            $this->productId,
            $this->tenantId,
        );

        $product->defineOption($this->makeOption('size', 3));
        $product->defineOption($this->makeOption('color', 1));
        $product->defineOption($this->makeOption('material', 2));

        $sorted = $product->getSortedOptions();

        self::assertCount(3, $sorted);
        self::assertSame('color', $sorted[0]->getCode()->toString());
        self::assertSame('material', $sorted[1]->getCode()->toString());
        self::assertSame('size', $sorted[2]->getCode()->toString());
    }

    // -------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------

    private function makeOption(string $code, int $position): Option
    {
        return Option::create(
            OptionId::generate(),
            OptionCode::fromString($code),
            LocalizedString::fromArray(['en' => ucfirst($code)]),
            $position,
            []
        );
    }

    private function createProductWithColorAndSize(): ConfigurableProduct
    {
        $product = ConfigurableProduct::create(
            $this->configurableProductId,
            $this->productId,
            $this->tenantId,
        );
        $product->defineOption($this->makeOption('color', 1));
        $product->defineOption($this->makeOption('size', 2));

        return $product;
    }
}
