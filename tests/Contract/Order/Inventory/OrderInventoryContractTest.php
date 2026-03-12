<?php

declare(strict_types=1);

namespace App\Tests\Contract\Order\Inventory;

use App\Catalog\Domain\Model\ProductId;
use App\Inventory\Domain\Event\StockAllocated;
use App\Inventory\Domain\Event\StockDepleted;
use App\Inventory\Domain\Event\StockReleased;
use App\Inventory\Domain\Event\StockReserved;
use App\Inventory\Domain\Model\Quantity;
use App\Inventory\Domain\Model\StockItemId;
use App\Inventory\Domain\Model\WarehouseId;
use App\Order\Domain\Event\OrderCancelled;
use App\Order\Domain\Model\OrderId;
use App\Order\Domain\Model\OrderStatus;
use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Contract tests for the Order <-> Inventory boundary.
 *
 * Verifies event structure and value object contracts between
 * Order cancellation and Inventory stock management.
 *
 * Producer: Order (OrderCancelled)
 * Consumer: Inventory (StockReserved, StockAllocated, StockReleased, StockDepleted)
 */
#[CoversNothing]
#[Group('contract')]
final class OrderInventoryContractTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    #[Test]
    public function orderCancelledEventHasRequiredFields(): void
    {
        $event = new OrderCancelled(
            orderId: OrderId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            tenantId: TenantId::fromString(self::TENANT_ID),
            previousStatus: OrderStatus::processing(),
            reason: 'Customer requested cancellation',
        );

        self::assertInstanceOf(OrderId::class, $event->orderId);
        self::assertInstanceOf(TenantId::class, $event->tenantId);
        self::assertInstanceOf(OrderStatus::class, $event->previousStatus);
    }

    #[Test]
    public function orderCancelledReasonIsOptional(): void
    {
        $event = new OrderCancelled(
            orderId: OrderId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            tenantId: TenantId::fromString(self::TENANT_ID),
            previousStatus: OrderStatus::pending(),
        );

        self::assertNull($event->reason);
    }

    #[Test]
    public function orderCancelledPreviousStatusIsEnumType(): void
    {
        $event = new OrderCancelled(
            orderId: OrderId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            tenantId: TenantId::fromString(self::TENANT_ID),
            previousStatus: OrderStatus::processing(),
        );

        self::assertSame('processing', $event->previousStatus->value());
    }

    #[Test]
    public function stockReservedImplementsDomainEvent(): void
    {
        $event = new StockReserved(
            stockItemId: StockItemId::generate(),
            tenantId: TenantId::fromString(self::TENANT_ID),
            productId: ProductId::generate(),
            quantity: Quantity::fromInt(5),
            reservationId: 'res-12345',
            occurredOn: new \DateTimeImmutable(),
        );

        self::assertInstanceOf(DomainEvent::class, $event);
        self::assertInstanceOf(\DateTimeImmutable::class, $event->occurredOn());
    }

    #[Test]
    public function stockReservedHasRequiredFields(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $productId = ProductId::generate();

        $event = new StockReserved(
            stockItemId: StockItemId::generate(),
            tenantId: $tenantId,
            productId: $productId,
            quantity: Quantity::fromInt(3),
            reservationId: 'order-550e8400',
            occurredOn: new \DateTimeImmutable(),
        );

        self::assertInstanceOf(StockItemId::class, $event->stockItemId);
        self::assertSame($tenantId, $event->tenantId);
        self::assertSame($productId, $event->productId);
        self::assertInstanceOf(Quantity::class, $event->quantity);
        self::assertIsString($event->reservationId);
        self::assertNotEmpty($event->reservationId);
    }

    #[Test]
    public function stockAllocatedHasOrderReference(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $productId = ProductId::generate();

        $event = new StockAllocated(
            stockItemId: StockItemId::generate(),
            tenantId: $tenantId,
            productId: $productId,
            quantity: Quantity::fromInt(2),
            orderId: '550e8400-e29b-41d4-a716-446655440000',
            occurredOn: new \DateTimeImmutable(),
        );

        self::assertSame($tenantId, $event->tenantId);
        self::assertSame($productId, $event->productId);
        self::assertIsString($event->orderId);
        self::assertNotEmpty($event->orderId);
        self::assertInstanceOf(Quantity::class, $event->quantity);
    }

    #[Test]
    public function stockReleasedHasRequiredFields(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $productId = ProductId::generate();

        $event = new StockReleased(
            stockItemId: StockItemId::generate(),
            tenantId: $tenantId,
            productId: $productId,
            quantity: Quantity::fromInt(5),
            referenceId: '550e8400-e29b-41d4-a716-446655440000',
            reason: 'Order cancelled by customer',
            occurredOn: new \DateTimeImmutable(),
        );

        self::assertInstanceOf(StockItemId::class, $event->stockItemId);
        self::assertSame($tenantId, $event->tenantId);
        self::assertSame($productId, $event->productId);
        self::assertInstanceOf(Quantity::class, $event->quantity);
        self::assertIsString($event->referenceId);
        self::assertIsString($event->reason);
        self::assertNotEmpty($event->reason);
    }

    #[Test]
    public function stockDepletedCrossesIntoCatalogBoundary(): void
    {
        $event = new StockDepleted(
            stockItemId: StockItemId::generate(),
            productId: ProductId::generate(),
            warehouseId: WarehouseId::generate(),
            tenantId: TenantId::fromString(self::TENANT_ID),
            availableQuantity: Quantity::fromInt(0),
            threshold: Quantity::fromInt(10),
            occurredOn: new \DateTimeImmutable(),
        );

        self::assertInstanceOf(ProductId::class, $event->productId);
        self::assertInstanceOf(WarehouseId::class, $event->warehouseId);
        self::assertInstanceOf(TenantId::class, $event->tenantId);
    }

    #[Test]
    public function quantityValueObjectEnforcesRange(): void
    {
        $quantity = Quantity::fromInt(0);
        self::assertSame(0, $quantity->value());

        $max = Quantity::fromInt(999999);
        self::assertSame(999999, $max->value());
    }

    #[Test]
    public function quantityRejectsNegativeValues(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Quantity::fromInt(-1);
    }
}
