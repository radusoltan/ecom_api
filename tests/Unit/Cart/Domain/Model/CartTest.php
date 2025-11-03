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
use App\Cart\Domain\Model\CartStatus;
use App\Cart\Domain\Model\Quantity;
use App\Cart\Domain\Model\SessionId;
use App\Catalog\Domain\Model\ProductId;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use DateTimeImmutable;
use InvalidArgumentException;
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
        $beforeCreation = new DateTimeImmutable();

        // Act
        $cart = Cart::create($cartId, $tenantId, null, $sessionId);
        $afterCreation = new DateTimeImmutable();

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
        for ($i = 0; $i < 100; $i++) {
            $cart->addItem(ProductId::generate(), null, Quantity::fromInt(1), $unitPrice);
        }

        // Expect
        $this->expectException(InvalidArgumentException::class);
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
        $this->expectException(InvalidArgumentException::class);
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
        $this->expectException(InvalidArgumentException::class);
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
        $this->expectException(InvalidArgumentException::class);
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
