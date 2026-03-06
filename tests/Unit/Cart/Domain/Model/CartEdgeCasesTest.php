<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cart\Domain\Model;

use App\Cart\Domain\Model\Cart;
use App\Cart\Domain\Model\CartId;
use App\Cart\Domain\Model\CartStatus;
use App\Cart\Domain\Model\Quantity;
use App\Cart\Domain\Model\SessionId;
use App\Catalog\Domain\Model\ProductId;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Cart::class)]
final class CartEdgeCasesTest extends TestCase
{
    // -------------------------------------------------------
    // markAsConverted
    // -------------------------------------------------------

    #[Test]
    public function itMarksActiveCartAsConverted(): void
    {
        $cart = $this->createActiveCart();

        $cart->markAsConverted();

        self::assertSame(CartStatus::CONVERTED, $cart->status());
    }

    #[Test]
    public function itThrowsWhenMarkingNonActiveCartAsConverted(): void
    {
        $cart = $this->createActiveCart();
        $cart->markAsExpired();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Only active carts can be converted');

        $cart->markAsConverted();
    }

    #[Test]
    public function itThrowsWhenMarkingAlreadyConvertedCartAsConverted(): void
    {
        $cart = $this->createActiveCart();
        $cart->markAsConverted();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Only active carts can be converted');

        $cart->markAsConverted();
    }

    // -------------------------------------------------------
    // markAsExpired
    // -------------------------------------------------------

    #[Test]
    public function itDoesNothingWhenMarkingAlreadyExpiredCartAsExpired(): void
    {
        $cart = $this->createActiveCart();
        $cart->markAsExpired();

        // Calling again must be a no-op
        $cart->markAsExpired();

        self::assertSame(CartStatus::EXPIRED, $cart->status());
    }

    // -------------------------------------------------------
    // isExpired
    // -------------------------------------------------------

    #[Test]
    public function itReportsAlreadyExpiredCartAsExpired(): void
    {
        $cart = $this->createActiveCart();
        $cart->markAsExpired();

        self::assertTrue($cart->isExpired());
    }

    #[Test]
    public function itReportsActiveRecentCartAsNotExpired(): void
    {
        $cart = $this->createActiveCart();

        self::assertFalse($cart->isExpired());
    }

    // -------------------------------------------------------
    // clear on already-empty cart
    // -------------------------------------------------------

    #[Test]
    public function itClearsAnEmptyCartWithoutRecordingEvent(): void
    {
        $cart = $this->createActiveCart();
        $cart->popEvents(); // drain the CartCreated event

        // Cart is already empty - clear should be a no-op
        $cart->clear();

        self::assertEmpty($cart->popEvents());
    }

    // -------------------------------------------------------
    // removeItem on non-active cart
    // -------------------------------------------------------

    #[Test]
    public function itThrowsWhenRemovingItemFromConvertedCart(): void
    {
        $cart = $this->createCartWithOneItem();
        $itemId = $cart->items()[0]->id();
        $cart->markAsConverted();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot remove items from a non-active cart');

        $cart->removeItem($itemId);
    }

    // -------------------------------------------------------
    // addItem on non-active cart
    // -------------------------------------------------------

    #[Test]
    public function itThrowsWhenAddingItemToExpiredCart(): void
    {
        $cart = $this->createActiveCart();
        $cart->markAsExpired();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot add items to a non-active cart');

        $cart->addItem(
            ProductId::generate(),
            null,
            Quantity::fromInt(1),
            Money::fromScalars(1000, 'EUR'),
        );
    }

    // -------------------------------------------------------
    // updateQuantity on non-active cart
    // -------------------------------------------------------

    #[Test]
    public function itThrowsWhenUpdatingQuantityInConvertedCart(): void
    {
        $cart = $this->createCartWithOneItem();
        $itemId = $cart->items()[0]->id();
        $cart->markAsConverted();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot update quantities in a non-active cart');

        $cart->updateQuantity($itemId, Quantity::fromInt(5));
    }

    // -------------------------------------------------------
    // calculateTotal on empty cart
    // -------------------------------------------------------

    #[Test]
    public function itCalculatesTotalAsZeroForEmptyCart(): void
    {
        $cart = $this->createActiveCart();

        $total = $cart->calculateTotal();

        self::assertTrue($total->isZero());
    }

    // -------------------------------------------------------
    // getItemCount
    // -------------------------------------------------------

    #[Test]
    public function itCountsItemQuantitiesAcrossAllItems(): void
    {
        $cart = $this->createActiveCart();
        $cart->addItem(ProductId::generate(), null, Quantity::fromInt(2), Money::fromScalars(500, 'EUR'));
        $cart->addItem(ProductId::generate(), null, Quantity::fromInt(3), Money::fromScalars(300, 'EUR'));

        self::assertSame(5, $cart->getItemCount());
    }

    // -------------------------------------------------------
    // Helpers
    // -------------------------------------------------------

    private function createActiveCart(): Cart
    {
        return Cart::create(
            CartId::generate(),
            TenantId::generate(),
            CustomerId::generate(),
            SessionId::generate(),
        );
    }

    private function createCartWithOneItem(): Cart
    {
        $cart = $this->createActiveCart();
        $cart->addItem(
            ProductId::generate(),
            null,
            Quantity::fromInt(1),
            Money::fromScalars(1000, 'EUR'),
        );

        return $cart;
    }
}
