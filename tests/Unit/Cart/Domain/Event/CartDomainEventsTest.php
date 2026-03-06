<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cart\Domain\Event;

use App\Cart\Domain\Event\CartAbandoned;
use App\Cart\Domain\Event\CartCleared;
use App\Cart\Domain\Event\CartCreated;
use App\Cart\Domain\Event\CartQuantityUpdated;
use App\Cart\Domain\Event\ItemAddedToCart;
use App\Cart\Domain\Event\ItemRemovedFromCart;
use App\Cart\Domain\Model\CartId;
use App\Cart\Domain\Model\CartItemId;
use App\Cart\Domain\Model\Quantity;
use App\Cart\Domain\Model\SessionId;
use App\Catalog\Domain\Model\ProductId;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CartCreated::class)]
#[CoversClass(ItemAddedToCart::class)]
#[CoversClass(CartQuantityUpdated::class)]
#[CoversClass(ItemRemovedFromCart::class)]
#[CoversClass(CartAbandoned::class)]
#[CoversClass(CartCleared::class)]
final class CartDomainEventsTest extends TestCase
{
    private CartId $cartId;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->cartId = CartId::generate();
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
    }

    #[Test]
    public function cartCreatedWithCustomer(): void
    {
        $customerId = CustomerId::generate();
        $event = new CartCreated($this->cartId, $this->tenantId, $customerId, null);

        self::assertSame($this->cartId, $event->cartId);
        self::assertSame($this->tenantId, $event->tenantId);
        self::assertSame($customerId, $event->customerId);
        self::assertNull($event->sessionId);
    }

    #[Test]
    public function cartCreatedWithSession(): void
    {
        $sessionId = SessionId::generate();
        $event = new CartCreated($this->cartId, $this->tenantId, null, $sessionId);

        self::assertNull($event->customerId);
        self::assertSame($sessionId, $event->sessionId);
    }

    #[Test]
    public function itemAddedToCartEvent(): void
    {
        $cartItemId = CartItemId::generate();
        $productId = ProductId::generate();
        $quantity = Quantity::fromInt(3);
        $unitPrice = Money::of('29.99', 'USD');

        $event = new ItemAddedToCart(
            $this->cartId,
            $this->tenantId,
            $cartItemId,
            $productId,
            'variant-123',
            $quantity,
            $unitPrice,
        );

        self::assertSame($this->cartId, $event->cartId);
        self::assertSame($this->tenantId, $event->tenantId);
        self::assertSame($cartItemId, $event->cartItemId);
        self::assertSame($productId, $event->productId);
        self::assertSame('variant-123', $event->variantId);
        self::assertSame($quantity, $event->quantity);
        self::assertSame($unitPrice, $event->unitPrice);
    }

    #[Test]
    public function itemAddedWithoutVariant(): void
    {
        $event = new ItemAddedToCart(
            $this->cartId,
            $this->tenantId,
            CartItemId::generate(),
            ProductId::generate(),
            null,
            Quantity::fromInt(1),
            Money::of('10.00', 'USD'),
        );

        self::assertNull($event->variantId);
    }

    #[Test]
    public function cartQuantityUpdatedEvent(): void
    {
        $cartItemId = CartItemId::generate();
        $productId = ProductId::generate();
        $newQuantity = Quantity::fromInt(5);

        $event = new CartQuantityUpdated(
            $this->cartId,
            $this->tenantId,
            $cartItemId,
            $productId,
            'variant-abc',
            $newQuantity,
        );

        self::assertSame($this->cartId, $event->cartId);
        self::assertSame($cartItemId, $event->cartItemId);
        self::assertSame($productId, $event->productId);
        self::assertSame('variant-abc', $event->variantId);
        self::assertSame($newQuantity, $event->newQuantity);
    }

    #[Test]
    public function itemRemovedFromCartEvent(): void
    {
        $cartItemId = CartItemId::generate();
        $productId = ProductId::generate();

        $event = new ItemRemovedFromCart(
            $this->cartId,
            $this->tenantId,
            $cartItemId,
            $productId,
            null,
        );

        self::assertSame($this->cartId, $event->cartId);
        self::assertSame($this->tenantId, $event->tenantId);
        self::assertSame($cartItemId, $event->cartItemId);
        self::assertSame($productId, $event->productId);
        self::assertNull($event->variantId);
    }

    #[Test]
    public function cartAbandonedEvent(): void
    {
        $customerId = CustomerId::generate();
        $cartTotal = Money::of('150.00', 'USD');
        $abandonedAt = new \DateTimeImmutable();

        $event = new CartAbandoned(
            $this->cartId,
            $this->tenantId,
            $customerId,
            'john@example.com',
            $cartTotal,
            3,
            $abandonedAt,
        );

        self::assertSame($this->cartId, $event->cartId);
        self::assertSame($this->tenantId, $event->tenantId);
        self::assertSame($customerId, $event->customerId);
        self::assertSame('john@example.com', $event->customerEmail);
        self::assertSame($cartTotal, $event->cartTotal);
        self::assertSame(3, $event->itemCount);
        self::assertSame($abandonedAt, $event->abandonedAt);
    }

    #[Test]
    public function cartClearedEvent(): void
    {
        $event = new CartCleared($this->cartId, $this->tenantId);

        self::assertSame($this->cartId, $event->cartId);
        self::assertSame($this->tenantId, $event->tenantId);
    }
}
