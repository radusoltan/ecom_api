<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Domain\Model;

use App\Catalog\Domain\Model\ProductId;
use App\Inventory\Domain\Event\StockDepleted;
use App\Inventory\Domain\Event\StockTransferred;
use App\Inventory\Domain\Model\Quantity;
use App\Inventory\Domain\Model\StockItem;
use App\Inventory\Domain\Model\StockItemId;
use App\Inventory\Domain\Model\WarehouseId;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(StockItem::class)]
final class StockItemTransferTest extends TestCase
{
    private StockItemId $stockItemId;
    private TenantId $tenantId;
    private ProductId $productId;
    private WarehouseId $sourceWarehouseId;
    private WarehouseId $destinationWarehouseId;

    protected function setUp(): void
    {
        $this->stockItemId = StockItemId::generate();
        $this->tenantId = TenantId::generate();
        $this->productId = ProductId::generate();
        $this->sourceWarehouseId = WarehouseId::generate();
        $this->destinationWarehouseId = WarehouseId::generate();
    }

    #[Test]
    public function itTransfersOutAndDeductsOnHand(): void
    {
        $stockItem = StockItem::create(
            $this->stockItemId,
            $this->tenantId,
            $this->productId,
            $this->sourceWarehouseId,
            Quantity::fromInt(100),
            Quantity::fromInt(5),
        );

        $stockItem->transferOut(Quantity::fromInt(30), $this->destinationWarehouseId);

        self::assertSame(70, $stockItem->onHand()->value());
        self::assertSame(70, $stockItem->calculateAvailable()->value());
    }

    #[Test]
    public function itRecordsStockTransferredEvent(): void
    {
        $stockItem = StockItem::create(
            $this->stockItemId,
            $this->tenantId,
            $this->productId,
            $this->sourceWarehouseId,
            Quantity::fromInt(100),
            Quantity::fromInt(5),
        );

        $stockItem->transferOut(Quantity::fromInt(40), $this->destinationWarehouseId);

        $events = $stockItem->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(StockTransferred::class, $events[0]);

        $event = $events[0];
        self::assertTrue($event->stockItemId->equals($this->stockItemId));
        self::assertTrue($event->productId->equals($this->productId));
        self::assertTrue($event->sourceWarehouseId->equals($this->sourceWarehouseId));
        self::assertTrue($event->destinationWarehouseId->equals($this->destinationWarehouseId));
        self::assertSame(40, $event->quantity->value());
        self::assertTrue($event->tenantId->equals($this->tenantId));
    }

    #[Test]
    public function itTransfersExactlyAvailableQuantity(): void
    {
        $stockItem = StockItem::create(
            $this->stockItemId,
            $this->tenantId,
            $this->productId,
            $this->sourceWarehouseId,
            Quantity::fromInt(50),
            Quantity::fromInt(5),
        );

        $stockItem->transferOut(Quantity::fromInt(50), $this->destinationWarehouseId);

        self::assertSame(0, $stockItem->onHand()->value());
        self::assertSame(0, $stockItem->calculateAvailable()->value());
    }

    #[Test]
    public function itThrowsWhenInsufficientAvailableStock(): void
    {
        $stockItem = StockItem::create(
            $this->stockItemId,
            $this->tenantId,
            $this->productId,
            $this->sourceWarehouseId,
            Quantity::fromInt(50),
            Quantity::fromInt(5),
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Insufficient available stock to transfer');

        $stockItem->transferOut(Quantity::fromInt(60), $this->destinationWarehouseId);
    }

    #[Test]
    public function itThrowsWhenInsufficientStockDueToReservations(): void
    {
        $stockItem = StockItem::create(
            $this->stockItemId,
            $this->tenantId,
            $this->productId,
            $this->sourceWarehouseId,
            Quantity::fromInt(100),
            Quantity::fromInt(5),
        );

        $stockItem->reserve(Quantity::fromInt(70), 'reservation-1');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Insufficient available stock to transfer. Available: 30, Requested: 50');

        $stockItem->transferOut(Quantity::fromInt(50), $this->destinationWarehouseId);
    }

    #[Test]
    public function itThrowsWhenInsufficientStockDueToAllocations(): void
    {
        $stockItem = StockItem::create(
            $this->stockItemId,
            $this->tenantId,
            $this->productId,
            $this->sourceWarehouseId,
            Quantity::fromInt(100),
            Quantity::fromInt(5),
        );

        $stockItem->allocate(Quantity::fromInt(80), 'order-1');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Insufficient available stock to transfer. Available: 20, Requested: 30');

        $stockItem->transferOut(Quantity::fromInt(30), $this->destinationWarehouseId);
    }

    #[Test]
    public function itThrowsWhenTransferQuantityIsZero(): void
    {
        $stockItem = StockItem::create(
            $this->stockItemId,
            $this->tenantId,
            $this->productId,
            $this->sourceWarehouseId,
            Quantity::fromInt(100),
            Quantity::fromInt(5),
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Transfer quantity must be greater than zero');

        $stockItem->transferOut(Quantity::zero(), $this->destinationWarehouseId);
    }

    #[Test]
    public function itFiresLowStockEventWhenTransferBringsStockBelowThreshold(): void
    {
        $stockItem = StockItem::create(
            $this->stockItemId,
            $this->tenantId,
            $this->productId,
            $this->sourceWarehouseId,
            Quantity::fromInt(50),
            Quantity::fromInt(40),
        );

        $stockItem->transferOut(Quantity::fromInt(20), $this->destinationWarehouseId);

        $events = $stockItem->popEvents();
        self::assertCount(2, $events);
        self::assertInstanceOf(StockTransferred::class, $events[0]);
        self::assertInstanceOf(StockDepleted::class, $events[1]);
        self::assertSame(30, $events[1]->availableQuantity->value());
        self::assertSame(40, $events[1]->threshold->value());
    }

    #[Test]
    public function itDoesNotFireLowStockEventWhenStockRemainsAboveThreshold(): void
    {
        $stockItem = StockItem::create(
            $this->stockItemId,
            $this->tenantId,
            $this->productId,
            $this->sourceWarehouseId,
            Quantity::fromInt(100),
            Quantity::fromInt(10),
        );

        $stockItem->transferOut(Quantity::fromInt(20), $this->destinationWarehouseId);

        $events = $stockItem->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(StockTransferred::class, $events[0]);
    }

    #[Test]
    public function transferInIncreasesOnHand(): void
    {
        $stockItem = StockItem::create(
            $this->stockItemId,
            $this->tenantId,
            $this->productId,
            $this->destinationWarehouseId,
            Quantity::fromInt(20),
            Quantity::fromInt(5),
        );

        $stockItem->transferIn(Quantity::fromInt(30));

        self::assertSame(50, $stockItem->onHand()->value());
        self::assertSame(50, $stockItem->calculateAvailable()->value());
    }

    #[Test]
    public function transferInDoesNotRecordEvent(): void
    {
        $stockItem = StockItem::create(
            $this->stockItemId,
            $this->tenantId,
            $this->productId,
            $this->destinationWarehouseId,
            Quantity::fromInt(20),
            Quantity::fromInt(5),
        );

        $stockItem->transferIn(Quantity::fromInt(30));

        $events = $stockItem->popEvents();
        self::assertCount(0, $events);
    }
}
