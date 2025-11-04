<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\Model;

// Load ConfigurableProduct file which contains multiple classes
require_once __DIR__ . '/../../../../../src/Catalog/Domain/Model/ConfigurableProduct.php';
require_once __DIR__ . '/../../../../../src/Catalog/Domain/Model/Option.php';
require_once __DIR__ . '/../../../../../src/Catalog/Domain/Model/Variant.php';

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
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ConfigurableProduct aggregate
 */
final class ConfigurableProductTest extends TestCase
{
    private ConfigurableProductId $configurableProductId;
    private ProductId $productId;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->configurableProductId = ConfigurableProductId::generate();
        $this->productId = ProductId::generate();
        $this->tenantId = TenantId::generate();
    }

    /**
     * Test that defineOption() enforces max 10 options rule
     */
    public function testDefineOptionEnforcesMaxTenOptionsRule(): void
    {
        // Arrange
        $configurableProduct = ConfigurableProduct::create(
            $this->configurableProductId,
            $this->productId,
            $this->tenantId
        );

        // Add 10 options (maximum allowed)
        for ($i = 1; $i <= 10; $i++) {
            $option = Option::create(
                OptionId::generate(),
                OptionCode::fromString("option{$i}"),
                LocalizedString::fromArray(['en' => "Option {$i}"]),
                $i,
                []
            );
            $configurableProduct->defineOption($option);
        }

        // Act & Assert - Adding the 11th option should throw exception
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot add more than 10 options');

        $eleventhOption = Option::create(
            OptionId::generate(),
            OptionCode::fromString('option11'),
            LocalizedString::fromArray(['en' => 'Option 11']),
            11,
            []
        );
        $configurableProduct->defineOption($eleventhOption);
    }

    /**
     * Test that addVariant() prevents duplicate option value combinations
     */
    public function testAddVariantPreventsDuplicateCombinations(): void
    {
        // Arrange
        $configurableProduct = ConfigurableProduct::create(
            $this->configurableProductId,
            $this->productId,
            $this->tenantId
        );

        // Define options first (required before adding variants)
        $colorOption = Option::create(
            OptionId::generate(),
            OptionCode::fromString('color'),
            LocalizedString::fromArray(['en' => 'Color']),
            1,
            []
        );
        $sizeOption = Option::create(
            OptionId::generate(),
            OptionCode::fromString('size'),
            LocalizedString::fromArray(['en' => 'Size']),
            2,
            []
        );
        $configurableProduct->defineOption($colorOption);
        $configurableProduct->defineOption($sizeOption);

        // Create a variant with specific option combination
        $optionValueMap = ['color' => 'red', 'size' => 'large'];

        $variant1 = Variant::create(
            VariantId::generate(),
            VariantSKU::fromString('SKU-TST-000001-red-large'),
            $optionValueMap,
            Money::fromScalars(1999, 'USD'),
            Stock::create(10, true, false),
            true
        );

        // Add first variant - should succeed
        $configurableProduct->addVariant($variant1);

        // Act & Assert - Adding second variant with same combination should throw exception
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Variant with this option combination already exists');

        $variant2 = Variant::create(
            VariantId::generate(),
            VariantSKU::fromString('SKU-TST-000002-red-large'),
            $optionValueMap, // Same combination
            Money::fromScalars(2999, 'USD'),
            Stock::create(5, true, false),
            true
        );

        $configurableProduct->addVariant($variant2);
    }

    /**
     * Test that ConfigurableProduct can be created successfully
     */
    public function testConfigurableProductCanBeCreated(): void
    {
        // Act
        $configurableProduct = ConfigurableProduct::create(
            $this->configurableProductId,
            $this->productId,
            $this->tenantId
        );

        // Assert
        $this->assertInstanceOf(ConfigurableProduct::class, $configurableProduct);
        $this->assertTrue($configurableProduct->getId()->equals($this->configurableProductId));
        $this->assertTrue($configurableProduct->getProductId()->equals($this->productId));
        $this->assertTrue($configurableProduct->getTenantId()->equals($this->tenantId));
        $this->assertCount(0, $configurableProduct->getOptions());
        $this->assertCount(0, $configurableProduct->getVariants());
    }

    /**
     * Test that options can be defined successfully
     */
    public function testOptionsCanBeDefined(): void
    {
        // Arrange
        $configurableProduct = ConfigurableProduct::create(
            $this->configurableProductId,
            $this->productId,
            $this->tenantId
        );

        $option = Option::create(
            OptionId::generate(),
            OptionCode::fromString('color'),
            LocalizedString::fromArray(['en' => 'Color']),
            1,
            []
        );

        // Act
        $configurableProduct->defineOption($option);

        // Assert
        $this->assertCount(1, $configurableProduct->getOptions());
        $options = $configurableProduct->getOptions();
        $this->assertEquals('color', $options[0]->getCode()->toString());
    }

    /**
     * Test that variants can be added successfully
     */
    public function testVariantsCanBeAdded(): void
    {
        // Arrange
        $configurableProduct = ConfigurableProduct::create(
            $this->configurableProductId,
            $this->productId,
            $this->tenantId
        );

        // Define options first (required before adding variants)
        $colorOption = Option::create(
            OptionId::generate(),
            OptionCode::fromString('color'),
            LocalizedString::fromArray(['en' => 'Color']),
            1,
            []
        );
        $sizeOption = Option::create(
            OptionId::generate(),
            OptionCode::fromString('size'),
            LocalizedString::fromArray(['en' => 'Size']),
            2,
            []
        );
        $configurableProduct->defineOption($colorOption);
        $configurableProduct->defineOption($sizeOption);

        $variant = Variant::create(
            VariantId::generate(),
            VariantSKU::fromString('SKU-TST-000001-blue-small'),
            ['color' => 'blue', 'size' => 'small'],
            Money::fromScalars(1499, 'USD'),
            Stock::create(20, true, false),
            true
        );

        // Act
        $configurableProduct->addVariant($variant);

        // Assert
        $this->assertCount(1, $configurableProduct->getVariants());
    }
}
