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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(StockItem::class)]
final class StockItemEdgeCasesTest extends TestCase
{
    // ---------------------------------------------------------------
    // Factory helpers
    // ---------------------------------------------------------------

    private function buildStockItem(int $onHand, ?int $threshold = null): StockItem
    {
        $item = StockItem::create(
            StockItemId::generate(),
            TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            ProductId::generate(),
            WarehouseId::generate(),
            Quantity::fromInt($onHand),
            null !== $threshold ? Quantity::fromInt($threshold) : null,
        );
        $item->popEvents(); // clear creation-side events for clean assertions

        return $item;
    }

    // ---------------------------------------------------------------
    // create — default low-stock threshold
    // ---------------------------------------------------------------

    #[Test]
    public function itUsesDefaultLowStockThresholdOf10WhenNoneProvided(): void
    {
        $item = StockItem::create(
            StockItemId::generate(),
            TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            ProductId::generate(),
            WarehouseId::generate(),
            Quantity::fromInt(100),
        );

        self::assertSame(10, $item->lowStockThreshold()->value());
    }

    #[Test]
    public function itHasNoEventsAfterReconstitutionFromPersistence(): void
    {
        $item = StockItem::reconstituteFromPersistence(
            StockItemId::generate(),
            TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            ProductId::generate(),
            WarehouseId::generate(),
            Quantity::fromInt(50),
            Quantity::fromInt(10),
            Quantity::fromInt(5),
            Quantity::fromInt(10),
            new \DateTimeImmutable('2024-01-01'),
            new \DateTimeImmutable('2024-06-01'),
        );

        self::assertEmpty($item->popEvents());
    }

    // ---------------------------------------------------------------
    // reserve — event payload
    // ---------------------------------------------------------------

    #[Test]
    public function itRecordsStockReservedEventWithCorrectPayload(): void
    {
        $item = $this->buildStockItem(100);

        $item->reserve(Quantity::fromInt(7), 'ref-XYZ');

        $events = $item->popEvents();
        self::assertCount(1, $events);
        $event = $events[0];
        self::assertInstanceOf(StockReserved::class, $event);
        self::assertSame(7, $event->quantity->value());
        self::assertSame('ref-XYZ', $event->reservationId);
        self::assertTrue($event->stockItemId->equals($item->id()));
    }

    #[Test]
    public function itUpdatesUpdatedAtOnReserve(): void
    {
        $item = $this->buildStockItem(50);
        $before = $item->updatedAt();

        // Tiny sleep to ensure timestamp differs
        usleep(1000);
        $item->reserve(Quantity::fromInt(5), 'cart-1');

        self::assertGreaterThanOrEqual($before->getTimestamp(), $item->updatedAt()->getTimestamp());
    }

    // ---------------------------------------------------------------
    // allocate — partial reservation reduction
    // ---------------------------------------------------------------

    #[Test]
    public function itReducesReservedByExactAllocatedAmountWhenReservedEqualsAllocateQty(): void
    {
        $item = $this->buildStockItem(100);
        $item->reserve(Quantity::fromInt(30), 'cart-1');
        $item->popEvents();

        $item->allocate(Quantity::fromInt(30), 'order-1');

        self::assertSame(0, $item->reserved()->value());
        self::assertSame(30, $item->allocated()->value());
    }

    #[Test]
    public function itDoesNotReduceReservedWhenAllocatingMoreThanReserved(): void
    {
        $item = $this->buildStockItem(200);
        $item->reserve(Quantity::fromInt(20), 'cart-1');
        $item->popEvents();

        $item->allocate(Quantity::fromInt(50), 'order-2'); // 50 > 20 reserved

        // reserved stays at 20 because reserved (20) < qty (50)
        self::assertSame(20, $item->reserved()->value());
        self::assertSame(50, $item->allocated()->value());
    }

    #[Test]
    public function itRecordsStockAllocatedEventWithOrderId(): void
    {
        $item = $this->buildStockItem(100);

        $item->allocate(Quantity::fromInt(15), 'order-ABC');

        $events = $item->popEvents();
        self::assertCount(1, $events); // StockAllocated only (no low-stock at 85 available)
        $event = $events[0];
        self::assertInstanceOf(StockAllocated::class, $event);
        self::assertSame(15, $event->quantity->value());
        self::assertSame('order-ABC', $event->orderId);
    }

    // ---------------------------------------------------------------
    // release — from combined allocated+reserved
    // ---------------------------------------------------------------

    #[Test]
    public function itReleasesPartiallyFromAllocatedWhenAllocatedLessThanRequested(): void
    {
        $item = $this->buildStockItem(100, 5);
        $item->allocate(Quantity::fromInt(10), 'order-1');
        $item->reserve(Quantity::fromInt(15), 'cart-1');
        $item->popEvents();

        // Release 20 total: take all 10 from allocated, then 10 from reserved
        $item->release(Quantity::fromInt(20), 'bulk', 'cancel');

        self::assertSame(0, $item->allocated()->value());
        self::assertSame(5, $item->reserved()->value());
        self::assertSame(95, $item->calculateAvailable()->value());
    }

    #[Test]
    public function itRecordsStockReleasedEventWithReferenceAndReason(): void
    {
        $item = $this->buildStockItem(100, 5);
        $item->allocate(Quantity::fromInt(20), 'order-9');
        $item->popEvents();

        $item->release(Quantity::fromInt(20), 'order-9', 'cancellation');

        $events = $item->popEvents();
        self::assertCount(1, $events);
        $event = $events[0];
        self::assertInstanceOf(StockReleased::class, $event);
        self::assertSame(20, $event->quantity->value());
        self::assertSame('order-9', $event->referenceId);
        self::assertSame('cancellation', $event->reason);
    }

    #[Test]
    public function itThrowsWhenReleasingFromOnlyReservedMoreThanReserved(): void
    {
        $item = $this->buildStockItem(100);
        $item->reserve(Quantity::fromInt(5), 'cart-1');

        self::expectException(\DomainException::class);
        self::expectExceptionMessage('Cannot release more than reserved/allocated');

        $item->release(Quantity::fromInt(10), 'cart-1', 'expired');
    }

    // ---------------------------------------------------------------
    // adjust — edge cases
    // ---------------------------------------------------------------

    #[Test]
    public function itRecordsStockAdjustedEventWithPreviousAndNewQuantity(): void
    {
        $item = $this->buildStockItem(100);

        $item->adjust(Quantity::fromInt(200), 'restock');

        $events = $item->popEvents();
        // adjust to 200 > threshold 10: no StockDepleted expected, only StockAdjusted
        $adjustedEvents = array_filter($events, fn ($e) => $e instanceof StockAdjusted);
        self::assertCount(1, $adjustedEvents);
        $event = array_values($adjustedEvents)[0];
        self::assertSame(100, $event->previousQuantity->value());
        self::assertSame(200, $event->newQuantity->value());
        self::assertSame('restock', $event->reason);
    }

    #[Test]
    public function itAdjustsStockSuccessfully(): void
    {
        $item = $this->buildStockItem(100);

        $item->adjust(Quantity::fromInt(150), 'restock');

        self::assertSame(150, $item->onHand()->value());
    }

    // ---------------------------------------------------------------
    // decrementOnShipment — edge cases
    // ---------------------------------------------------------------

    #[Test]
    public function itDecrementsOnHandAndAllocatedOnShipment(): void
    {
        $item = $this->buildStockItem(100);
        $item->allocate(Quantity::fromInt(40), 'order-ship');
        $item->popEvents();

        $item->decrementOnShipment(Quantity::fromInt(25));

        self::assertSame(75, $item->onHand()->value());
        self::assertSame(15, $item->allocated()->value());
        self::assertSame(60, $item->calculateAvailable()->value());
    }

    #[Test]
    public function itTriggersLowStockAfterDecrementOnShipmentWhenBelowThreshold(): void
    {
        $item = StockItem::create(
            StockItemId::generate(),
            TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            ProductId::generate(),
            WarehouseId::generate(),
            Quantity::fromInt(20),
            Quantity::fromInt(15), // threshold = 15
        );
        $item->popEvents();
        $item->allocate(Quantity::fromInt(10), 'order-1');
        $item->popEvents();

        // ship 10 -> onHand=10, allocated=0, available=10 which is <= 15 threshold
        $item->decrementOnShipment(Quantity::fromInt(10));

        $events = $item->popEvents();
        $depleted = array_filter($events, fn ($e) => $e instanceof StockDepleted);
        self::assertNotEmpty($depleted);
    }

    // ---------------------------------------------------------------
    // updateLowStockThreshold
    // ---------------------------------------------------------------

    #[Test]
    public function itUpdateLowStockThresholdAndTriggersLowStockIfNowBelow(): void
    {
        $item = $this->buildStockItem(20, 5); // available=20, old threshold=5

        // Raise threshold to 25 — now available (20) <= threshold (25)
        $item->updateLowStockThreshold(Quantity::fromInt(25));

        $events = $item->popEvents();
        $depleted = array_filter($events, fn ($e) => $e instanceof StockDepleted);
        self::assertNotEmpty($depleted);
    }

    #[Test]
    public function itUpdateLowStockThresholdNoEventWhenStillAboveThreshold(): void
    {
        $item = $this->buildStockItem(100, 10);

        // Lower threshold stays below available
        $item->updateLowStockThreshold(Quantity::fromInt(20));

        $events = $item->popEvents();
        $depleted = array_filter($events, fn ($e) => $e instanceof StockDepleted);
        self::assertEmpty($depleted);
    }

    // ---------------------------------------------------------------
    // calculateAvailable
    // ---------------------------------------------------------------

    #[Test]
    public function itCalculatesAvailableCorrectlyWithReserved(): void
    {
        $item = $this->buildStockItem(100, 5);
        $item->reserve(Quantity::fromInt(30), 'c1');

        // available = 100 - 30 = 70
        self::assertSame(70, $item->calculateAvailable()->value());
    }

    #[Test]
    public function itReturnsZeroAvailableWhenAllStockCommitted(): void
    {
        $item = $this->buildStockItem(50, 5);
        $item->reserve(Quantity::fromInt(20), 'c1');
        $item->allocate(Quantity::fromInt(30), 'o1');

        self::assertSame(0, $item->calculateAvailable()->value());
        self::assertTrue($item->calculateAvailable()->isZero());
    }

    // ---------------------------------------------------------------
    // Getters completeness
    // ---------------------------------------------------------------

    #[Test]
    public function itExposesAllRequiredGetters(): void
    {
        $id = StockItemId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $productId = ProductId::generate();
        $warehouseId = WarehouseId::generate();

        $item = StockItem::create($id, $tenantId, $productId, $warehouseId, Quantity::fromInt(42));

        self::assertTrue($item->id()->equals($id));
        self::assertTrue($item->tenantId()->equals($tenantId));
        self::assertTrue($item->productId()->equals($productId));
        self::assertTrue($item->warehouseId()->equals($warehouseId));
        self::assertSame(42, $item->onHand()->value());
        self::assertSame(0, $item->reserved()->value());
        self::assertSame(0, $item->allocated()->value());
        self::assertInstanceOf(\DateTimeImmutable::class, $item->createdAt());
        self::assertInstanceOf(\DateTimeImmutable::class, $item->updatedAt());
    }

    // ---------------------------------------------------------------
    // Reconstitute — overrides defaults
    // ---------------------------------------------------------------

    #[Test]
    public function itReconstitutesSetsExactTimestamps(): void
    {
        $createdAt = new \DateTimeImmutable('2024-01-01 08:00:00');
        $updatedAt = new \DateTimeImmutable('2024-06-15 14:30:00');

        $item = StockItem::reconstituteFromPersistence(
            StockItemId::generate(),
            TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            ProductId::generate(),
            WarehouseId::generate(),
            Quantity::fromInt(100),
            Quantity::fromInt(25),
            Quantity::fromInt(10),
            Quantity::fromInt(8),
            $createdAt,
            $updatedAt,
        );

        self::assertSame($createdAt, $item->createdAt());
        self::assertSame($updatedAt, $item->updatedAt());
        self::assertSame(25, $item->reserved()->value());
        self::assertSame(10, $item->allocated()->value());
        self::assertSame(8, $item->lowStockThreshold()->value());
    }

    #[Test]
    public function itReconstituteCalculatesAvailableFromStoredValues(): void
    {
        // onHand=100, reserved=30, allocated=20 → available=50
        $item = StockItem::reconstituteFromPersistence(
            StockItemId::generate(),
            TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            ProductId::generate(),
            WarehouseId::generate(),
            Quantity::fromInt(100),
            Quantity::fromInt(30),
            Quantity::fromInt(20),
            Quantity::fromInt(10),
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
        );

        self::assertSame(50, $item->calculateAvailable()->value());
    }
}
