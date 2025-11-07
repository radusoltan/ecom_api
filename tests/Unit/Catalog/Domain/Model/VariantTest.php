<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\Model;

// Load Variant file which contains multiple classes
require_once __DIR__.'/../../../../../src/Catalog/Domain/Model/Variant.php';

use App\Catalog\Domain\Model\Stock;
use App\Catalog\Domain\Model\Variant;
use App\Catalog\Domain\Model\VariantId;
use App\Catalog\Domain\ValueObject\VariantSKU;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Variant entity.
 */
final class VariantTest extends TestCase
{
    /**
     * Test that create() validates option value map is not empty.
     */
    public function testCreateValidatesOptionValueMapNotEmpty(): void
    {
        // Arrange & Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Variant must have at least one option value');

        Variant::create(
            VariantId::generate(),
            VariantSKU::fromString('SKU-TST-000001-test'),
            [], // Empty map should fail
            Money::fromScalars(1999, 'USD'),
            Stock::create(10, true, false),
            true
        );
    }

    /**
     * Test that matchesCombination() correctly matches option combinations.
     */
    public function testMatchesCombinationCorrectlyMatches(): void
    {
        // Arrange
        $optionValueMap = ['color' => 'red', 'size' => 'large'];

        $variant = Variant::create(
            VariantId::generate(),
            VariantSKU::fromString('SKU-TST-000001-red-large'),
            $optionValueMap,
            Money::fromScalars(1999, 'USD'),
            Stock::create(10, true, false),
            true
        );

        // Act & Assert - Exact match should return true
        $this->assertTrue($variant->matchesCombination(['color' => 'red', 'size' => 'large']));

        // Different order but same values should match
        $this->assertTrue($variant->matchesCombination(['size' => 'large', 'color' => 'red']));

        // Different combination should not match
        $this->assertFalse($variant->matchesCombination(['color' => 'blue', 'size' => 'large']));
        $this->assertFalse($variant->matchesCombination(['color' => 'red', 'size' => 'small']));

        // Partial combination should not match
        $this->assertFalse($variant->matchesCombination(['color' => 'red']));

        // Extra options should not match
        $this->assertFalse($variant->matchesCombination([
            'color' => 'red',
            'size' => 'large',
            'material' => 'cotton',
        ]));
    }

    /**
     * Test that variant can be created successfully.
     */
    public function testVariantCanBeCreated(): void
    {
        // Arrange
        $variantId = VariantId::generate();
        $sku = VariantSKU::fromString('SKU-TST-000001-green-medium');
        $optionValueMap = ['color' => 'green', 'size' => 'medium'];
        $price = Money::fromScalars(2499, 'USD');
        $stock = Stock::create(15, true, false);

        // Act
        $variant = Variant::create(
            $variantId,
            $sku,
            $optionValueMap,
            $price,
            $stock,
            true
        );

        // Assert
        $this->assertInstanceOf(Variant::class, $variant);
        $this->assertTrue($variant->getId()->equals($variantId));
        $this->assertEquals('SKU-TST-000001-green-medium', $variant->getSku()->toString());
        $this->assertEquals($optionValueMap, $variant->getOptionValueMap());
        $this->assertTrue($variant->getPrice()->equals($price));
        $this->assertTrue($variant->isActive());
    }

    /**
     * Test that variant price can be updated.
     */
    public function testVariantPriceCanBeUpdated(): void
    {
        // Arrange
        $variant = Variant::create(
            VariantId::generate(),
            VariantSKU::fromString('SKU-TST-000001-black-small'),
            ['color' => 'black', 'size' => 'small'],
            Money::fromScalars(1999, 'USD'),
            Stock::create(10, true, false),
            true
        );

        $newPrice = Money::fromScalars(2999, 'USD');

        // Act
        $variant->updatePrice($newPrice);

        // Assert
        $this->assertTrue($variant->getPrice()->equals($newPrice));
    }

    /**
     * Test that variant can be activated and deactivated.
     */
    public function testVariantCanBeActivatedAndDeactivated(): void
    {
        // Arrange
        $variant = Variant::create(
            VariantId::generate(),
            VariantSKU::fromString('SKU-TST-000001-white-large'),
            ['color' => 'white', 'size' => 'large'],
            Money::fromScalars(3499, 'USD'),
            Stock::create(5, true, false),
            true
        );

        // Act - Deactivate
        $variant->deactivate();

        // Assert
        $this->assertFalse($variant->isActive());

        // Act - Activate
        $variant->activate();

        // Assert
        $this->assertTrue($variant->isActive());
    }

    /**
     * Test that stock can be updated.
     */
    public function testStockCanBeUpdated(): void
    {
        // Arrange
        $variant = Variant::create(
            VariantId::generate(),
            VariantSKU::fromString('SKU-TST-000001-yellow-medium'),
            ['color' => 'yellow', 'size' => 'medium'],
            Money::fromScalars(1799, 'USD'),
            Stock::create(10, true, false),
            true
        );

        $newStock = Stock::create(25, true, true);

        // Act
        $variant->updateStock($newStock);

        // Assert
        $this->assertEquals(25, $variant->getStock()->quantity());
        $this->assertTrue($variant->getStock()->allowBackorder());
    }
}
