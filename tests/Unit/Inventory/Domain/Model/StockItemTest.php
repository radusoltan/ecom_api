<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Domain\Model;

use App\Catalog\Domain\Model\ProductId;
use App\Inventory\Domain\Event\StockAdjusted;
use App\Inventory\Domain\Event\StockAllocated;
use App\Inventory\Domain\Event\StockDepleted;
use App\Inventory\Domain\Event\StockReleased;
use App\Inventory\Domain\Event\StockReserved;
use App\Inventory\Domain\Model\Quantity;
use App\Inventory\Domain\Model\StockItem;
use App\Inventory\Domain\Model\StockItemId;
use App\Inventory\Domain\Model\WarehouseId;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class StockItemTest extends TestCase
{
    private StockItemId $stockItemId;
    private TenantId $tenantId;
    private ProductId $productId;
    private WarehouseId $warehouseId;

    protected function setUp(): void
    {
        $this->stockItemId = StockItemId::generate();
        $this->tenantId = TenantId::generate();
        $this->productId = ProductId::generate();
        $this->warehouseId = WarehouseId::generate();
    }

    public function testCreateStockItem(): void
    {
        $initialQuantity = Quantity::fromInt(100);
        $lowStockThreshold = Quantity::fromInt(10);

        $stockItem = StockItem::create(
            $this->stockItemId,
            $this->tenantId,
            $this->productId,
            $this->warehouseId,
            $initialQuantity,
            $lowStockThreshold
        );

        $this->assertTrue($stockItem->id()->equals($this->stockItemId));
        $this->assertTrue($stockItem->tenantId()->equals($this->tenantId));
        $this->assertTrue($stockItem->productId()->equals($this->productId));
        $this->assertTrue($stockItem->warehouseId()->equals($this->warehouseId));
        $this->assertTrue($stockItem->onHand()->equals($initialQuantity));
        $this->assertTrue($stockItem->reserved()->equals(Quantity::zero()));
        $this->assertTrue($stockItem->allocated()->equals(Quantity::zero()));
        $this->assertTrue($stockItem->lowStockThreshold()->equals($lowStockThreshold));
    }

    public function testCreateWithDefaultLowStockThreshold(): void
    {
        $stockItem = StockItem::create(
            $this->stockItemId,
            $this->tenantId,
            $this->productId,
            $this->warehouseId,
            Quantity::fromInt(100)
        );

        $this->assertEquals(10, $stockItem->lowStockThreshold()->value());
    }

    public function testReserveStockSuccess(): void
    {
        $stockItem = StockItem::create(
            $this->stockItemId,
            $this->tenantId,
            $this->productId,
            $this->warehouseId,
            Quantity::fromInt(100)
        );

        $stockItem->reserve(Quantity::fromInt(20), 'reservation-123');

        $this->assertEquals(20, $stockItem->reserved()->value());
        $this->assertEquals(80, $stockItem->calculateAvailable()->value());

        $events = $stockItem->popEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(StockReserved::class, $events[0]);
        $this->assertEquals(20, $events[0]->quantity->value());
        $this->assertEquals('reservation-123', $events[0]->reservationId);
    }

    public function testReserveStockFailsWhenInsufficientAvailable(): void
    {
        $stockItem = StockItem::create(
            $this->stockItemId,
            $this->tenantId,
            $this->productId,
            $this->warehouseId,
            Quantity::fromInt(50)
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Insufficient stock to reserve');

        $stockItem->reserve(Quantity::fromInt(60), 'reservation-123');
    }

    public function testAllocateStockSuccess(): void
    {
        $stockItem = StockItem::create(
            $this->stockItemId,
            $this->tenantId,
            $this->productId,
            $this->warehouseId,
            Quantity::fromInt(100)
        );

        $stockItem->allocate(Quantity::fromInt(30), 'order-456');

        $this->assertEquals(30, $stockItem->allocated()->value());
        $this->assertEquals(70, $stockItem->calculateAvailable()->value());

        $events = $stockItem->popEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(StockAllocated::class, $events[0]);
        $this->assertEquals(30, $events[0]->quantity->value());
        $this->assertEquals('order-456', $events[0]->orderId);
    }

    public function testAllocateReducesReservedWhenPreviouslyReserved(): void
    {
        $stockItem = StockItem::create(
            $this->stockItemId,
            $this->tenantId,
            $this->productId,
            $this->warehouseId,
            Quantity::fromInt(100)
        );

        $stockItem->reserve(Quantity::fromInt(40), 'reservation-123');
        $stockItem->allocate(Quantity::fromInt(40), 'order-456');

        $this->assertEquals(0, $stockItem->reserved()->value());
        $this->assertEquals(40, $stockItem->allocated()->value());
        $this->assertEquals(60, $stockItem->calculateAvailable()->value());
    }

    public function testAllocateFailsWhenInsufficientAvailable(): void
    {
        $stockItem = StockItem::create(
            $this->stockItemId,
            $this->tenantId,
            $this->productId,
            $this->warehouseId,
            Quantity::fromInt(50)
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Insufficient stock to allocate');

        $stockItem->allocate(Quantity::fromInt(60), 'order-456');
    }

    public function testReleaseFromAllocatedStock(): void
    {
        $stockItem = StockItem::create(
            $this->stockItemId,
            $this->tenantId,
            $this->productId,
            $this->warehouseId,
            Quantity::fromInt(100)
        );

        $stockItem->allocate(Quantity::fromInt(30), 'order-456');
        $stockItem->release(Quantity::fromInt(10), 'order-cancelled');

        $this->assertEquals(20, $stockItem->allocated()->value());
        $this->assertEquals(80, $stockItem->calculateAvailable()->value());

        $events = $stockItem->popEvents();
        $this->assertCount(2, $events);
        $this->assertInstanceOf(StockReleased::class, $events[1]);
        $this->assertEquals(10, $events[1]->quantity->value());
        $this->assertEquals('order-cancelled', $events[1]->reason);
    }

    public function testReleaseFromReservedStock(): void
    {
        $stockItem = StockItem::create(
            $this->stockItemId,
            $this->tenantId,
            $this->productId,
            $this->warehouseId,
            Quantity::fromInt(100)
        );

        $stockItem->reserve(Quantity::fromInt(25), 'reservation-123');
        $stockItem->release(Quantity::fromInt(15), 'cart-expired');

        $this->assertEquals(10, $stockItem->reserved()->value());
        $this->assertEquals(90, $stockItem->calculateAvailable()->value());
    }

    public function testReleaseFromBothAllocatedAndReserved(): void
    {
        $stockItem = StockItem::create(
            $this->stockItemId,
            $this->tenantId,
            $this->productId,
            $this->warehouseId,
            Quantity::fromInt(100)
        );

        $stockItem->allocate(Quantity::fromInt(20), 'order-1');
        $stockItem->reserve(Quantity::fromInt(15), 'reservation-1');
        $stockItem->release(Quantity::fromInt(30), 'bulk-release');

        $this->assertEquals(0, $stockItem->allocated()->value());
        $this->assertEquals(5, $stockItem->reserved()->value());
        $this->assertEquals(95, $stockItem->calculateAvailable()->value());
    }

    public function testReleaseFailsWhenExceedsReservedAndAllocated(): void
    {
        $stockItem = StockItem::create(
            $this->stockItemId,
            $this->tenantId,
            $this->productId,
            $this->warehouseId,
            Quantity::fromInt(100)
        );

        $stockItem->reserve(Quantity::fromInt(10), 'reservation-1');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot release more than reserved/allocated');

        $stockItem->release(Quantity::fromInt(20), 'invalid-release');
    }

    public function testAdjustStockSuccess(): void
    {
        $stockItem = StockItem::create(
            $this->stockItemId,
            $this->tenantId,
            $this->productId,
            $this->warehouseId,
            Quantity::fromInt(100)
        );

        $stockItem->adjust(Quantity::fromInt(150), 'inventory-count');

        $this->assertEquals(150, $stockItem->onHand()->value());
        $this->assertEquals(150, $stockItem->calculateAvailable()->value());

        $events = $stockItem->popEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(StockAdjusted::class, $events[0]);
        $this->assertEquals(100, $events[0]->previousQuantity->value());
        $this->assertEquals(150, $events[0]->newQuantity->value());
        $this->assertEquals('inventory-count', $events[0]->reason);
    }

    public function testAdjustFailsWhenNewQuantityLessThanCommitted(): void
    {
        $stockItem = StockItem::create(
            $this->stockItemId,
            $this->tenantId,
            $this->productId,
            $this->warehouseId,
            Quantity::fromInt(100)
        );

        $stockItem->reserve(Quantity::fromInt(20), 'reservation-1');
        $stockItem->allocate(Quantity::fromInt(30), 'order-1');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot adjust to 40. Reserved + Allocated = 50');

        $stockItem->adjust(Quantity::fromInt(40), 'invalid-adjustment');
    }

    public function testDecrementOnShipmentSuccess(): void
    {
        $stockItem = StockItem::create(
            $this->stockItemId,
            $this->tenantId,
            $this->productId,
            $this->warehouseId,
            Quantity::fromInt(100)
        );

        $stockItem->allocate(Quantity::fromInt(30), 'order-1');
        $stockItem->decrementOnShipment(Quantity::fromInt(30));

        $this->assertEquals(70, $stockItem->onHand()->value());
        $this->assertEquals(0, $stockItem->allocated()->value());
        $this->assertEquals(70, $stockItem->calculateAvailable()->value());
    }

    public function testDecrementOnShipmentFailsWhenExceedsAllocated(): void
    {
        $stockItem = StockItem::create(
            $this->stockItemId,
            $this->tenantId,
            $this->productId,
            $this->warehouseId,
            Quantity::fromInt(100)
        );

        $stockItem->allocate(Quantity::fromInt(20), 'order-1');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot ship more than allocated');

        $stockItem->decrementOnShipment(Quantity::fromInt(30));
    }

    public function testUpdateLowStockThreshold(): void
    {
        $stockItem = StockItem::create(
            $this->stockItemId,
            $this->tenantId,
            $this->productId,
            $this->warehouseId,
            Quantity::fromInt(100)
        );

        $stockItem->updateLowStockThreshold(Quantity::fromInt(25));

        $this->assertEquals(25, $stockItem->lowStockThreshold()->value());
    }

    public function testLowStockEventFiredWhenBelowThreshold(): void
    {
        $stockItem = StockItem::create(
            $this->stockItemId,
            $this->tenantId,
            $this->productId,
            $this->warehouseId,
            Quantity::fromInt(50),
            Quantity::fromInt(40)
        );

        $stockItem->allocate(Quantity::fromInt(20), 'order-1');

        $events = $stockItem->popEvents();
        $this->assertCount(2, $events); // StockAllocated + StockDepleted
        $this->assertInstanceOf(StockAllocated::class, $events[0]);
        $this->assertInstanceOf(StockDepleted::class, $events[1]);
        $this->assertEquals(30, $events[1]->availableQuantity->value());
        $this->assertEquals(40, $events[1]->threshold->value());
    }

    public function testCalculateAvailableWithReservedAndAllocated(): void
    {
        $stockItem = StockItem::create(
            $this->stockItemId,
            $this->tenantId,
            $this->productId,
            $this->warehouseId,
            Quantity::fromInt(200)
        );

        $stockItem->reserve(Quantity::fromInt(50), 'reservation-1');
        $stockItem->allocate(Quantity::fromInt(75), 'order-1');

        $available = $stockItem->calculateAvailable();

        $this->assertEquals(75, $available->value()); // 200 - 50 - 75 = 75
    }
}
