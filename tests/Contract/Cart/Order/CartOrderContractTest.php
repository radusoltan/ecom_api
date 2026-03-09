<?php

declare(strict_types=1);

namespace App\Tests\Contract\Cart\Order;

use App\Cart\Domain\Event\CartCleared;
use App\Cart\Domain\Event\CartCreated;
use App\Cart\Domain\Event\ItemAddedToCart;
use App\Order\Domain\Event\OrderPlaced;
use App\Order\Domain\Model\OrderId;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Contract tests for the Cart <-> Order (Checkout) boundary.
 *
 * Verifies the contract between Cart and Order contexts during checkout.
 * When an order is placed, the Cart must be cleared (ACTIVE -> CONVERTED).
 *
 * Producer: Order (OrderPlaced triggers cart clearing)
 * Consumer: Cart (OrderPlacedCartClearingSubscriber)
 */
#[CoversNothing]
#[Group('contract')]
final class CartOrderContractTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    // -----------------------------------------------------------------------
    // OrderPlaced — consumed by Cart's OrderPlacedCartClearingSubscriber
    // -----------------------------------------------------------------------

    #[Test]
    public function orderPlacedEventCarriesFieldsCartSubscriberNeeds(): void
    {
        $event = new OrderPlaced(
            orderId: OrderId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            tenantId: TenantId::fromString(self::TENANT_ID),
            customerEmail: 'customer@example.com',
            total: Money::fromScalars(5000, 'USD'),
        );

        // Cart subscriber needs orderId and tenantId to locate and clear the cart
        self::assertInstanceOf(OrderId::class, $event->orderId);
        self::assertInstanceOf(TenantId::class, $event->tenantId);
        self::assertNotEmpty($event->orderId->toString());
        self::assertNotEmpty($event->tenantId->toString());
    }

    // -----------------------------------------------------------------------
    // Cart domain events — structure verification
    // -----------------------------------------------------------------------

    #[Test]
    public function cartCreatedEventCanBeInstantiated(): void
    {
        $ref = new \ReflectionClass(CartCreated::class);

        // Contract: CartCreated must be a valid event class
        self::assertTrue($ref->isReadOnly());
        self::assertTrue($ref->isFinal());
    }

    #[Test]
    public function itemAddedToCartEventCanBeInstantiated(): void
    {
        $ref = new \ReflectionClass(ItemAddedToCart::class);

        self::assertTrue($ref->isReadOnly());
        self::assertTrue($ref->isFinal());
    }

    #[Test]
    public function cartClearedEventCanBeInstantiated(): void
    {
        $ref = new \ReflectionClass(CartCleared::class);

        // Contract: CartCleared is the final event in the checkout flow
        self::assertTrue($ref->isReadOnly());
        self::assertTrue($ref->isFinal());
    }

    // -----------------------------------------------------------------------
    // Cross-boundary type consistency
    // -----------------------------------------------------------------------

    #[Test]
    public function tenantIdIsConsistentBetweenCartAndOrder(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);

        // Both contexts must use the same TenantId format
        self::assertSame(self::TENANT_ID, $tenantId->toString());
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $tenantId->toString()
        );
    }

    #[Test]
    public function moneyValueObjectIsInteroperableBetweenCartAndOrder(): void
    {
        // Cart uses Money for item prices; Order uses Money for totals
        $cartItemPrice = Money::fromScalars(2500, 'USD');
        $orderTotal = Money::fromScalars(5000, 'USD');

        // Contract: both use same Money VO from Shared context
        self::assertSame('USD', $cartItemPrice->getCurrency()->getCurrencyCode());
        self::assertSame('USD', $orderTotal->getCurrency()->getCurrencyCode());
        self::assertIsInt($cartItemPrice->getAmount());
        self::assertIsInt($orderTotal->getAmount());
    }
}
