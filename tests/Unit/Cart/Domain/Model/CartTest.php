<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cart\Domain\Model;

use App\Cart\Domain\Event\CartCleared;
use App\Cart\Domain\Event\CartCreated;
use App\Cart\Domain\Event\CartQuantityUpdated;
use App\Cart\Domain\Event\ItemAddedToCart;
use App\Cart\Domain\Event\ItemRemovedFromCart;
use App\Cart\Domain\Exception\CartItemNotFoundException;
use App\Cart\Domain\Model\Cart;
use App\Cart\Domain\Model\CartId;
use App\Cart\Domain\Model\Quantity;
use App\Cart\Domain\Model\SessionId;
use App\Catalog\Domain\Model\ProductId;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class CartTest extends TestCase
{
    // =============================================
    // Test Cart Creation
    // =============================================

    public function testItCreatesCartWithActiveStatus(): void
    {
        // Arrange
        $cartId = CartId::generate();
        $tenantId = TenantId::generate();
        $sessionId = SessionId::generate();

        // Act
        $cart = Cart::create($cartId, $tenantId, null, $sessionId);

        // Assert
        $this->assertTrue($cart->status()->isActive());
    }

    public function testItCreatesCartForGuestWithSessionId(): void
    {
        // Arrange
        $cartId = CartId::generate();
        $tenantId = TenantId::generate();
        $sessionId = SessionId::generate();

        // Act
        $cart = Cart::create($cartId, $tenantId, null, $sessionId);

        // Assert
        $this->assertNull($cart->customerId());
        $this->assertNotNull($cart->sessionId());
        $this->assertTrue($cart->sessionId()->equals($sessionId));
    }

    public function testItCreatesCartForCustomer(): void
    {
        // Arrange
        $cartId = CartId::generate();
        $tenantId = TenantId::generate();
        $customerId = CustomerId::generate();
        $sessionId = SessionId::generate();

        // Act
        $cart = Cart::create($cartId, $tenantId, $customerId, $sessionId);

        // Assert
        $this->assertNotNull($cart->customerId());
        $this->assertTrue($cart->customerId()->equals($customerId));
    }

    public function testItRecordsCartCreatedEvent(): void
    {
        // Arrange
        $cartId = CartId::generate();
        $tenantId = TenantId::generate();
        $sessionId = SessionId::generate();

        // Act
        $cart = Cart::create($cartId, $tenantId, null, $sessionId);
        $events = $cart->popEvents();

        // Assert
        $this->assertCount(1, $events);
        $this->assertInstanceOf(CartCreated::class, $events[0]);
    }

    public function testItSetsTimestampsOnCreation(): void
    {
        // Arrange
        $cartId = CartId::generate();
        $tenantId = TenantId::generate();
        $sessionId = SessionId::generate();
        $beforeCreation = new \DateTimeImmutable();

        // Act
        $cart = Cart::create($cartId, $tenantId, null, $sessionId);
        $afterCreation = new \DateTimeImmutable();

        // Assert
        $this->assertGreaterThanOrEqual($beforeCreation, $cart->createdAt());
        $this->assertLessThanOrEqual($afterCreation, $cart->createdAt());
        $this->assertGreaterThanOrEqual($beforeCreation, $cart->updatedAt());
        $this->assertLessThanOrEqual($afterCreation, $cart->updatedAt());
    }

    // =============================================
    // Test Add Item to Cart
    // =============================================

    public function testItAddsItemToEmptyCart(): void
    {
        // Arrange
        $cart = $this->createSampleCart();
        $productId = ProductId::generate();
        $quantity = Quantity::fromInt(2);
        $unitPrice = Money::fromScalars(1999, 'USD');

        // Act
        $cart->addItem($productId, null, $quantity, $unitPrice);

        // Assert
        $this->assertCount(1, $cart->items());
    }

    public function testItRecordsItemAddedEventWhenAddingNewItem(): void
    {
        // Arrange
        $cart = $this->createSampleCart();
        $productId = ProductId::generate();
        $quantity = Quantity::fromInt(2);
        $unitPrice = Money::fromScalars(1999, 'USD');

        // Act
        $cart->popEvents(); // Clear creation event
        $cart->addItem($productId, null, $quantity, $unitPrice);
        $events = $cart->popEvents();

        // Assert
        $this->assertCount(1, $events);
        $this->assertInstanceOf(ItemAddedToCart::class, $events[0]);
    }

    public function testItMergesQuantitiesWhenAddingDuplicateItem(): void
    {
        // Arrange
        $cart = $this->createSampleCart();
        $productId = ProductId::generate();
        $quantity1 = Quantity::fromInt(2);
        $quantity2 = Quantity::fromInt(3);
        $unitPrice = Money::fromScalars(1999, 'USD');

        // Act
        $cart->addItem($productId, null, $quantity1, $unitPrice);
        $cart->addItem($productId, null, $quantity2, $unitPrice);

        // Assert
        $this->assertCount(1, $cart->items()); // Still only 1 item
        $this->assertEquals(5, $cart->items()[0]->quantity()->toInt()); // 2 + 3 = 5
    }

    public function testItRecordsQuantityUpdatedEventWhenMergingDuplicates(): void
    {
        // Arrange
        $cart = $this->createSampleCart();
        $productId = ProductId::generate();
        $quantity1 = Quantity::fromInt(2);
        $quantity2 = Quantity::fromInt(3);
        $unitPrice = Money::fromScalars(1999, 'USD');

        // Act
        $cart->addItem($productId, null, $quantity1, $unitPrice);
        $cart->popEvents(); // Clear first add event
        $cart->addItem($productId, null, $quantity2, $unitPrice);
        $events = $cart->popEvents();

        // Assert
        $this->assertCount(1, $events);
        $this->assertInstanceOf(CartQuantityUpdated::class, $events[0]);
    }

    public function testItThrowsExceptionWhenExceedingMaxItems(): void
    {
        // Arrange
        $cart = $this->createSampleCart();
        $unitPrice = Money::fromScalars(1000, 'USD');

        // Add 100 items (max limit)
        for ($i = 0; $i < 100; ++$i) {
            $cart->addItem(ProductId::generate(), null, Quantity::fromInt(1), $unitPrice);
        }

        // Expect
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot add more than 100 items to cart');

        // Act
        $cart->addItem(ProductId::generate(), null, Quantity::fromInt(1), $unitPrice);
    }

    public function testItThrowsExceptionWhenAddingToNonActiveCart(): void
    {
        // Arrange
        $cart = $this->createSampleCart();
        $cart->markAsExpired();

        // Expect
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot add items to a non-active cart');

        // Act
        $cart->addItem(ProductId::generate(), null, Quantity::fromInt(1), Money::fromScalars(1000, 'USD'));
    }

    // =============================================
    // Test Remove Item from Cart
    // =============================================

    public function testItRemovesItemFromCart(): void
    {
        // Arrange
        $cart = $this->createSampleCart();
        $productId = ProductId::generate();
        $cart->addItem($productId, null, Quantity::fromInt(2), Money::fromScalars(1999, 'USD'));
        $itemId = $cart->items()[0]->id();

        // Act
        $cart->removeItem($itemId);

        // Assert
        $this->assertCount(0, $cart->items());
    }

    public function testItRecordsItemRemovedEvent(): void
    {
        // Arrange
        $cart = $this->createSampleCart();
        $productId = ProductId::generate();
        $cart->addItem($productId, null, Quantity::fromInt(2), Money::fromScalars(1999, 'USD'));
        $itemId = $cart->items()[0]->id();
        $cart->popEvents(); // Clear previous events

        // Act
        $cart->removeItem($itemId);
        $events = $cart->popEvents();

        // Assert
        $this->assertCount(1, $events);
        $this->assertInstanceOf(ItemRemovedFromCart::class, $events[0]);
    }

    public function testItThrowsExceptionWhenRemovingNonExistentItem(): void
    {
        // Arrange
        $cart = $this->createSampleCart();
        $nonExistentItemId = \App\Cart\Domain\Model\CartItemId::generate();

        // Expect
        $this->expectException(CartItemNotFoundException::class);

        // Act
        $cart->removeItem($nonExistentItemId);
    }

    public function testItThrowsExceptionWhenRemovingFromNonActiveCart(): void
    {
        // Arrange
        $cart = $this->createSampleCart();
        $cart->addItem(ProductId::generate(), null, Quantity::fromInt(1), Money::fromScalars(1000, 'USD'));
        $itemId = $cart->items()[0]->id();
        $cart->markAsExpired();

        // Expect
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot remove items from a non-active cart');

        // Act
        $cart->removeItem($itemId);
    }

    // =============================================
    // Test Update Quantity
    // =============================================

    public function testItUpdatesItemQuantity(): void
    {
        // Arrange
        $cart = $this->createSampleCart();
        $cart->addItem(ProductId::generate(), null, Quantity::fromInt(2), Money::fromScalars(1999, 'USD'));
        $itemId = $cart->items()[0]->id();

        // Act
        $cart->updateQuantity($itemId, Quantity::fromInt(5));

        // Assert
        $this->assertEquals(5, $cart->items()[0]->quantity()->toInt());
    }

    public function testItRecordsQuantityUpdatedEvent(): void
    {
        // Arrange
        $cart = $this->createSampleCart();
        $cart->addItem(ProductId::generate(), null, Quantity::fromInt(2), Money::fromScalars(1999, 'USD'));
        $itemId = $cart->items()[0]->id();
        $cart->popEvents(); // Clear previous events

        // Act
        $cart->updateQuantity($itemId, Quantity::fromInt(5));
        $events = $cart->popEvents();

        // Assert
        $this->assertCount(1, $events);
        $this->assertInstanceOf(CartQuantityUpdated::class, $events[0]);
    }

    public function testItThrowsExceptionWhenUpdatingNonExistentItem(): void
    {
        // Arrange
        $cart = $this->createSampleCart();
        $nonExistentItemId = \App\Cart\Domain\Model\CartItemId::generate();

        // Expect
        $this->expectException(CartItemNotFoundException::class);

        // Act
        $cart->updateQuantity($nonExistentItemId, Quantity::fromInt(5));
    }

    // =============================================
    // Test Clear Cart
    // =============================================

    public function testItClearsAllItemsFromCart(): void
    {
        // Arrange
        $cart = $this->createSampleCart();
        $cart->addItem(ProductId::generate(), null, Quantity::fromInt(1), Money::fromScalars(1000, 'USD'));
        $cart->addItem(ProductId::generate(), null, Quantity::fromInt(2), Money::fromScalars(2000, 'USD'));

        // Act
        $cart->clear();

        // Assert
        $this->assertCount(0, $cart->items());
    }

    public function testItRecordsCartClearedEvent(): void
    {
        // Arrange
        $cart = $this->createSampleCart();
        $cart->addItem(ProductId::generate(), null, Quantity::fromInt(1), Money::fromScalars(1000, 'USD'));
        $cart->popEvents(); // Clear previous events

        // Act
        $cart->clear();
        $events = $cart->popEvents();

        // Assert
        $this->assertCount(1, $events);
        $this->assertInstanceOf(CartCleared::class, $events[0]);
    }

    public function testItDoesNotRecordEventWhenClearingEmptyCart(): void
    {
        // Arrange
        $cart = $this->createSampleCart();
        $cart->popEvents(); // Clear creation event

        // Act
        $cart->clear();
        $events = $cart->popEvents();

        // Assert
        $this->assertCount(0, $events);
    }

    // =============================================
    // Test Calculate Total
    // =============================================

    public function testItCalculatesTotalForEmptyCart(): void
    {
        // Arrange
        $cart = $this->createSampleCart();

        // Act
        $total = $cart->calculateTotal();

        // Assert
        $this->assertEquals(0, $total->getAmount());
    }

    public function testItCalculatesTotalForSingleItem(): void
    {
        // Arrange
        $cart = $this->createSampleCart();
        $cart->addItem(ProductId::generate(), null, Quantity::fromInt(2), Money::fromScalars(1999, 'USD'));

        // Act
        $total = $cart->calculateTotal();

        // Assert
        $this->assertEquals(3998, $total->getAmount()); // 2 × 1999 = 3998
    }

    public function testItCalculatesTotalForMultipleItems(): void
    {
        // Arrange
        $cart = $this->createSampleCart();
        $cart->addItem(ProductId::generate(), null, Quantity::fromInt(2), Money::fromScalars(1999, 'USD'));
        $cart->addItem(ProductId::generate(), null, Quantity::fromInt(1), Money::fromScalars(500, 'USD'));

        // Act
        $total = $cart->calculateTotal();

        // Assert
        $this->assertEquals(4498, $total->getAmount()); // (2 × 1999) + (1 × 500) = 4498
    }

    // =============================================
    // Test Cart Expiry
    // =============================================

    public function testItIsNotExpiredWhenJustCreated(): void
    {
        // Arrange
        $cart = $this->createSampleCart();

        // Act & Assert
        $this->assertFalse($cart->isExpired());
    }

    public function testItMarksCartAsExpired(): void
    {
        // Arrange
        $cart = $this->createSampleCart();

        // Act
        $cart->markAsExpired();

        // Assert
        $this->assertTrue($cart->status()->isExpired());
        $this->assertTrue($cart->isExpired());
    }

    public function testItDoesNotMarkAlreadyExpiredCart(): void
    {
        // Arrange
        $cart = $this->createSampleCart();
        $cart->markAsExpired();
        $updatedAtBefore = $cart->updatedAt();

        sleep(1); // Wait to see if updatedAt changes

        // Act
        $cart->markAsExpired();

        // Assert
        $this->assertEquals($updatedAtBefore, $cart->updatedAt()); // Should not change
    }

    // =============================================
    // Test Mark as Converted
    // =============================================

    public function testItMarksCartAsConverted(): void
    {
        // Arrange
        $cart = $this->createSampleCart();

        // Act
        $cart->markAsConverted();

        // Assert
        $this->assertTrue($cart->status()->isConverted());
    }

    public function testItThrowsExceptionWhenConvertingNonActiveCart(): void
    {
        // Arrange
        $cart = $this->createSampleCart();
        $cart->markAsExpired();

        // Expect
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Only active carts can be converted to orders');

        // Act
        $cart->markAsConverted();
    }

    // =============================================
    // Test Assign to Customer
    // =============================================

    public function testItAssignsGuestCartToCustomer(): void
    {
        // Arrange
        $cart = $this->createSampleCart();
        $customerId = CustomerId::generate();

        // Act
        $cart->assignToCustomer($customerId);

        // Assert
        $this->assertNotNull($cart->customerId());
        $this->assertTrue($cart->customerId()->equals($customerId));
    }

    public function testItReassignsCartToNewCustomer(): void
    {
        // Arrange
        $cart = $this->createSampleCart();
        $firstCustomerId = CustomerId::generate();
        $secondCustomerId = CustomerId::generate();
        $cart->assignToCustomer($firstCustomerId);

        // Act
        $cart->assignToCustomer($secondCustomerId);

        // Assert
        $this->assertTrue($cart->customerId()->equals($secondCustomerId));
        $this->assertFalse($cart->customerId()->equals($firstCustomerId));
    }

    public function testItUpdatesTimestampWhenAssigningToCustomer(): void
    {
        // Arrange
        $cart = $this->createSampleCart();
        $originalUpdatedAt = $cart->updatedAt();
        sleep(1); // Wait to ensure timestamp changes

        // Act
        $cart->assignToCustomer(CustomerId::generate());

        // Assert
        $this->assertGreaterThan($originalUpdatedAt, $cart->updatedAt());
    }

    // =============================================
    // Test Get Item Count
    // =============================================

    public function testItReturnsZeroItemCountForEmptyCart(): void
    {
        // Arrange
        $cart = $this->createSampleCart();

        // Act
        $count = $cart->getItemCount();

        // Assert
        $this->assertEquals(0, $count);
    }

    public function testItReturnsTotalItemCountAcrossAllCartItems(): void
    {
        // Arrange
        $cart = $this->createSampleCart();
        $cart->addItem(ProductId::generate(), null, Quantity::fromInt(2), Money::fromScalars(1000, 'USD'));
        $cart->addItem(ProductId::generate(), null, Quantity::fromInt(3), Money::fromScalars(2000, 'USD'));

        // Act
        $count = $cart->getItemCount();

        // Assert
        $this->assertEquals(5, $count); // 2 + 3 = 5
    }

    // =============================================
    // Test Edge Cases - Max Quantity
    // =============================================

    public function testItAddsItemWithMaxQuantity(): void
    {
        // Arrange
        $cart = $this->createSampleCart();
        $productId = ProductId::generate();
        $maxQuantity = Quantity::fromInt(999);
        $unitPrice = Money::fromScalars(1000, 'USD');

        // Act
        $cart->addItem($productId, null, $maxQuantity, $unitPrice);

        // Assert
        $this->assertCount(1, $cart->items());
        $this->assertEquals(999, $cart->items()[0]->quantity()->toInt());
    }

    public function testItThrowsWhenMergingQuantitiesExceedsMax(): void
    {
        // Arrange
        $cart = $this->createSampleCart();
        $productId = ProductId::generate();
        $unitPrice = Money::fromScalars(1000, 'USD');
        $cart->addItem($productId, null, Quantity::fromInt(500), $unitPrice);

        // Expect
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Quantity cannot exceed 999');

        // Act - try to add 500 more (total would be 1000)
        $cart->addItem($productId, null, Quantity::fromInt(500), $unitPrice);
    }

    // =============================================
    // Test Edge Cases - Update Quantity
    // =============================================

    public function testItThrowsWhenUpdatingQuantityOnNonActiveCart(): void
    {
        // Arrange
        $cart = $this->createSampleCart();
        $cart->addItem(ProductId::generate(), null, Quantity::fromInt(2), Money::fromScalars(1000, 'USD'));
        $itemId = $cart->items()[0]->id();
        $cart->markAsExpired();

        // Expect
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot update quantities in a non-active cart');

        // Act
        $cart->updateQuantity($itemId, Quantity::fromInt(5));
    }

    public function testItThrowsWhenUpdatingQuantityOnConvertedCart(): void
    {
        // Arrange
        $cart = $this->createSampleCart();
        $cart->addItem(ProductId::generate(), null, Quantity::fromInt(2), Money::fromScalars(1000, 'USD'));
        $itemId = $cart->items()[0]->id();
        $cart->markAsConverted();

        // Expect
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot update quantities in a non-active cart');

        // Act
        $cart->updateQuantity($itemId, Quantity::fromInt(5));
    }

    // =============================================
    // Test Edge Cases - Convert Cart
    // =============================================

    public function testItThrowsWhenConvertingAlreadyConvertedCart(): void
    {
        // Arrange
        $cart = $this->createSampleCart();
        $cart->markAsConverted();

        // Expect
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Only active carts can be converted to orders');

        // Act
        $cart->markAsConverted();
    }

    // =============================================
    // Test Edge Cases - Variants
    // =============================================

    public function testItAddsItemsWithDifferentVariantsAsSeparateItems(): void
    {
        // Arrange
        $cart = $this->createSampleCart();
        $productId = ProductId::generate();
        $unitPrice = Money::fromScalars(1000, 'USD');

        // Act
        $cart->addItem($productId, 'variant-1', Quantity::fromInt(2), $unitPrice);
        $cart->addItem($productId, 'variant-2', Quantity::fromInt(3), $unitPrice);

        // Assert
        $this->assertCount(2, $cart->items());
    }

    public function testItMergesItemsWithSameVariant(): void
    {
        // Arrange
        $cart = $this->createSampleCart();
        $productId = ProductId::generate();
        $variantId = 'variant-123';
        $unitPrice = Money::fromScalars(1000, 'USD');

        // Act
        $cart->addItem($productId, $variantId, Quantity::fromInt(2), $unitPrice);
        $cart->addItem($productId, $variantId, Quantity::fromInt(3), $unitPrice);

        // Assert
        $this->assertCount(1, $cart->items());
        $this->assertEquals(5, $cart->items()[0]->quantity()->toInt());
    }

    public function testItTreatsNullVariantAndStringVariantAsDifferent(): void
    {
        // Arrange
        $cart = $this->createSampleCart();
        $productId = ProductId::generate();
        $unitPrice = Money::fromScalars(1000, 'USD');

        // Act
        $cart->addItem($productId, null, Quantity::fromInt(2), $unitPrice);
        $cart->addItem($productId, 'variant-123', Quantity::fromInt(3), $unitPrice);

        // Assert
        $this->assertCount(2, $cart->items());
    }

    // =============================================
    // Test Edge Cases - Total Calculation
    // =============================================

    public function testItCalculatesTotalWithMaxQuantityItems(): void
    {
        // Arrange
        $cart = $this->createSampleCart();
        $cart->addItem(ProductId::generate(), null, Quantity::fromInt(999), Money::fromScalars(100, 'USD'));
        $cart->addItem(ProductId::generate(), null, Quantity::fromInt(999), Money::fromScalars(200, 'USD'));

        // Act
        $total = $cart->calculateTotal();

        // Assert
        // (999 × 100) + (999 × 200) = 99900 + 199800 = 299700
        $this->assertEquals(299700, $total->getAmount());
    }

    // =============================================
    // Test Edge Cases - Cart at Max Items Capacity
    // =============================================

    public function testItAllowsExactly100Items(): void
    {
        // Arrange
        $cart = $this->createSampleCart();
        $unitPrice = Money::fromScalars(1000, 'USD');

        // Act - Add exactly 100 items
        for ($i = 0; $i < 100; ++$i) {
            $cart->addItem(ProductId::generate(), null, Quantity::fromInt(1), $unitPrice);
        }

        // Assert
        $this->assertCount(100, $cart->items());
    }

    // =============================================
    // Test Edge Cases - IsEmpty
    // =============================================

    public function testIsEmptyReturnsTrueForNewCart(): void
    {
        // Arrange
        $cart = $this->createSampleCart();

        // Act & Assert
        $this->assertTrue($cart->isEmpty());
    }

    public function testIsEmptyReturnsFalseForCartWithItems(): void
    {
        // Arrange
        $cart = $this->createSampleCart();
        $cart->addItem(ProductId::generate(), null, Quantity::fromInt(1), Money::fromScalars(1000, 'USD'));

        // Act & Assert
        $this->assertFalse($cart->isEmpty());
    }

    public function testIsEmptyReturnsTrueAfterClearing(): void
    {
        // Arrange
        $cart = $this->createSampleCart();
        $cart->addItem(ProductId::generate(), null, Quantity::fromInt(1), Money::fromScalars(1000, 'USD'));
        $cart->clear();

        // Act & Assert
        $this->assertTrue($cart->isEmpty());
    }

    // =============================================
    // Test Edge Cases - IsActive
    // =============================================

    public function testIsActiveReturnsTrueForNewCart(): void
    {
        // Arrange
        $cart = $this->createSampleCart();

        // Act & Assert
        $this->assertTrue($cart->isActive());
    }

    public function testIsActiveReturnsFalseForExpiredCart(): void
    {
        // Arrange
        $cart = $this->createSampleCart();
        $cart->markAsExpired();

        // Act & Assert
        $this->assertFalse($cart->isActive());
    }

    public function testIsActiveReturnsFalseForConvertedCart(): void
    {
        // Arrange
        $cart = $this->createSampleCart();
        $cart->markAsConverted();

        // Act & Assert
        $this->assertFalse($cart->isActive());
    }

    // =============================================
    // Helper Methods
    // =============================================

    private function createSampleCart(): Cart
    {
        return Cart::create(
            CartId::generate(),
            TenantId::generate(),
            null,
            SessionId::generate()
        );
    }
}
