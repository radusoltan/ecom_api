<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cart\Domain\Model;

use App\Cart\Domain\Model\CartItem;
use App\Cart\Domain\Model\CartItemId;
use App\Cart\Domain\Model\Quantity;
use App\Catalog\Domain\Model\ProductId;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class CartItemTest extends TestCase
{
    // =============================================
    // Test CartItem Creation
    // =============================================

    public function testItCreatesCartItemWithValidData(): void
    {
        // Arrange
        $itemId = CartItemId::generate();
        $productId = ProductId::generate();
        $quantity = Quantity::fromInt(5);
        $unitPrice = Money::fromScalars(1999, 'USD');

        // Act
        $item = CartItem::create($itemId, $productId, null, $quantity, $unitPrice);

        // Assert
        $this->assertTrue($item->id()->equals($itemId));
        $this->assertTrue($item->productId()->equals($productId));
        $this->assertNull($item->variantId());
        $this->assertEquals(5, $item->quantity()->toInt());
        $this->assertEquals(1999, $item->unitPrice()->getAmount());
    }

    public function testItCreatesCartItemWithVariantId(): void
    {
        // Arrange
        $itemId = CartItemId::generate();
        $productId = ProductId::generate();
        $variantId = 'variant-123';
        $quantity = Quantity::fromInt(2);
        $unitPrice = Money::fromScalars(2999, 'EUR');

        // Act
        $item = CartItem::create($itemId, $productId, $variantId, $quantity, $unitPrice);

        // Assert
        $this->assertSame($variantId, $item->variantId());
    }

    public function testItCreatesCartItemWithMinQuantity(): void
    {
        // Arrange
        $quantity = Quantity::fromInt(1);

        // Act
        $item = $this->createSampleCartItem($quantity);

        // Assert
        $this->assertEquals(1, $item->quantity()->toInt());
    }

    public function testItCreatesCartItemWithMaxQuantity(): void
    {
        // Arrange
        $quantity = Quantity::fromInt(999);

        // Act
        $item = $this->createSampleCartItem($quantity);

        // Assert
        $this->assertEquals(999, $item->quantity()->toInt());
    }

    // =============================================
    // Test Update Quantity
    // =============================================

    public function testItUpdatesQuantityToNewValue(): void
    {
        // Arrange
        $item = $this->createSampleCartItem(Quantity::fromInt(2));
        $newQuantity = Quantity::fromInt(5);

        // Act
        $item->updateQuantity($newQuantity);

        // Assert
        $this->assertEquals(5, $item->quantity()->toInt());
    }

    public function testItUpdatesQuantityToMinValue(): void
    {
        // Arrange
        $item = $this->createSampleCartItem(Quantity::fromInt(10));

        // Act
        $item->updateQuantity(Quantity::fromInt(1));

        // Assert
        $this->assertEquals(1, $item->quantity()->toInt());
    }

    public function testItUpdatesQuantityToMaxValue(): void
    {
        // Arrange
        $item = $this->createSampleCartItem(Quantity::fromInt(10));

        // Act
        $item->updateQuantity(Quantity::fromInt(999));

        // Assert
        $this->assertEquals(999, $item->quantity()->toInt());
    }

    // =============================================
    // Test Increase Quantity
    // =============================================

    public function testItIncreasesQuantityByAddingAmount(): void
    {
        // Arrange
        $item = $this->createSampleCartItem(Quantity::fromInt(5));
        $additionalQuantity = Quantity::fromInt(3);

        // Act
        $item->increaseQuantity($additionalQuantity);

        // Assert
        $this->assertEquals(8, $item->quantity()->toInt());
    }

    public function testItIncreasesQuantityFromMinToHigherValue(): void
    {
        // Arrange
        $item = $this->createSampleCartItem(Quantity::fromInt(1));

        // Act
        $item->increaseQuantity(Quantity::fromInt(10));

        // Assert
        $this->assertEquals(11, $item->quantity()->toInt());
    }

    public function testItThrowsWhenIncreasingQuantityExceedsMax(): void
    {
        // Arrange
        $item = $this->createSampleCartItem(Quantity::fromInt(900));

        // Expect
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Quantity cannot exceed 999');

        // Act
        $item->increaseQuantity(Quantity::fromInt(100));
    }

    // =============================================
    // Test Row Total Calculation
    // =============================================

    public function testItCalculatesRowTotalForSingleQuantity(): void
    {
        // Arrange
        $quantity = Quantity::fromInt(1);
        $unitPrice = Money::fromScalars(1999, 'USD');
        $item = $this->createSampleCartItem($quantity, $unitPrice);

        // Act
        $rowTotal = $item->rowTotal();

        // Assert
        $this->assertEquals(1999, $rowTotal->getAmount());
        $this->assertEquals('USD', $rowTotal->getCurrency()->getCurrencyCode());
    }

    public function testItCalculatesRowTotalForMultipleQuantities(): void
    {
        // Arrange
        $quantity = Quantity::fromInt(5);
        $unitPrice = Money::fromScalars(1999, 'USD');
        $item = $this->createSampleCartItem($quantity, $unitPrice);

        // Act
        $rowTotal = $item->rowTotal();

        // Assert
        $this->assertEquals(9995, $rowTotal->getAmount()); // 5 × 1999 = 9995
    }

    public function testItCalculatesRowTotalForMaxQuantity(): void
    {
        // Arrange
        $quantity = Quantity::fromInt(999);
        $unitPrice = Money::fromScalars(100, 'EUR');
        $item = $this->createSampleCartItem($quantity, $unitPrice);

        // Act
        $rowTotal = $item->rowTotal();

        // Assert
        $this->assertEquals(99900, $rowTotal->getAmount()); // 999 × 100 = 99900
    }

    public function testItCalculatesRowTotalWithDifferentCurrency(): void
    {
        // Arrange
        $quantity = Quantity::fromInt(3);
        $unitPrice = Money::fromScalars(2500, 'EUR');
        $item = $this->createSampleCartItem($quantity, $unitPrice);

        // Act
        $rowTotal = $item->rowTotal();

        // Assert
        $this->assertEquals(7500, $rowTotal->getAmount());
        $this->assertEquals('EUR', $rowTotal->getCurrency()->getCurrencyCode());
    }

    public function testItCalculatesRowTotalAfterQuantityUpdate(): void
    {
        // Arrange
        $item = $this->createSampleCartItem(Quantity::fromInt(2), Money::fromScalars(1000, 'USD'));

        // Act
        $item->updateQuantity(Quantity::fromInt(4));
        $rowTotal = $item->rowTotal();

        // Assert
        $this->assertEquals(4000, $rowTotal->getAmount()); // 4 × 1000 = 4000
    }

    // =============================================
    // Test IsSameAs (Product+Variant Matching)
    // =============================================

    public function testItIdentifiesSameProductWithoutVariant(): void
    {
        // Arrange
        $productId = ProductId::generate();
        $item = CartItem::create(
            CartItemId::generate(),
            $productId,
            null,
            Quantity::fromInt(1),
            Money::fromScalars(1000, 'USD')
        );

        // Act
        $isSame = $item->isSameAs($productId, null);

        // Assert
        $this->assertTrue($isSame);
    }

    public function testItIdentifiesSameProductWithSameVariant(): void
    {
        // Arrange
        $productId = ProductId::generate();
        $variantId = 'variant-123';
        $item = CartItem::create(
            CartItemId::generate(),
            $productId,
            $variantId,
            Quantity::fromInt(1),
            Money::fromScalars(1000, 'USD')
        );

        // Act
        $isSame = $item->isSameAs($productId, $variantId);

        // Assert
        $this->assertTrue($isSame);
    }

    public function testItIdentifiesDifferentProductWithoutVariant(): void
    {
        // Arrange
        $productId1 = ProductId::generate();
        $productId2 = ProductId::generate();
        $item = CartItem::create(
            CartItemId::generate(),
            $productId1,
            null,
            Quantity::fromInt(1),
            Money::fromScalars(1000, 'USD')
        );

        // Act
        $isSame = $item->isSameAs($productId2, null);

        // Assert
        $this->assertFalse($isSame);
    }

    public function testItIdentifiesSameProductWithDifferentVariant(): void
    {
        // Arrange
        $productId = ProductId::generate();
        $item = CartItem::create(
            CartItemId::generate(),
            $productId,
            'variant-123',
            Quantity::fromInt(1),
            Money::fromScalars(1000, 'USD')
        );

        // Act
        $isSame = $item->isSameAs($productId, 'variant-456');

        // Assert
        $this->assertFalse($isSame);
    }

    public function testItIdentifiesSameProductWithVariantVsNoVariant(): void
    {
        // Arrange
        $productId = ProductId::generate();
        $item = CartItem::create(
            CartItemId::generate(),
            $productId,
            'variant-123',
            Quantity::fromInt(1),
            Money::fromScalars(1000, 'USD')
        );

        // Act
        $isSame = $item->isSameAs($productId, null);

        // Assert
        $this->assertFalse($isSame);
    }

    // =============================================
    // Helper Methods
    // =============================================

    private function createSampleCartItem(
        Quantity $quantity,
        ?Money $unitPrice = null,
    ): CartItem {
        return CartItem::create(
            CartItemId::generate(),
            ProductId::generate(),
            null,
            $quantity,
            $unitPrice ?? Money::fromScalars(1999, 'USD')
        );
    }
}
