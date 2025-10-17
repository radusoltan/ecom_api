<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Domain\Model;

use App\Inventory\Domain\Model\WarehouseId;
use App\Order\Domain\Event\FulfillmentCompleted;
use App\Order\Domain\Event\FulfillmentFailed;
use App\Order\Domain\Event\FulfillmentStarted;
use App\Order\Domain\Event\FulfillmentStatusChanged;
use App\Order\Domain\Model\Fulfillment;
use App\Order\Domain\Model\OrderId;
use App\Order\Domain\ValueObject\FulfillmentId;
use App\Order\Domain\ValueObject\FulfillmentStatus;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class FulfillmentTest extends TestCase
{
    private FulfillmentId $fulfillmentId;
    private OrderId $orderId;
    private WarehouseId $warehouseId;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->fulfillmentId = FulfillmentId::generate();
        $this->orderId = OrderId::generate();
        $this->warehouseId = WarehouseId::generate();
        $this->tenantId = TenantId::generate();
    }

    public function testCanStartFulfillment(): void
    {
        $fulfillment = Fulfillment::start(
            $this->fulfillmentId,
            $this->orderId,
            $this->warehouseId,
            $this->tenantId
        );

        $this->assertTrue($fulfillment->status()->equals(FulfillmentStatus::assigned()));
        $this->assertNotNull($fulfillment->assignedAt());

        $events = $fulfillment->popEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(FulfillmentStarted::class, $events[0]);
    }

    public function testCanStartPicking(): void
    {
        $fulfillment = Fulfillment::start(
            $this->fulfillmentId,
            $this->orderId,
            $this->warehouseId,
            $this->tenantId
        );
        $fulfillment->popEvents(); // Clear start event

        $fulfillment->startPicking();

        $this->assertTrue($fulfillment->status()->equals(FulfillmentStatus::picking()));
        $this->assertNotNull($fulfillment->pickingStartedAt());

        $events = $fulfillment->popEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(FulfillmentStatusChanged::class, $events[0]);
    }

    public function testCanStartPacking(): void
    {
        $fulfillment = Fulfillment::start(
            $this->fulfillmentId,
            $this->orderId,
            $this->warehouseId,
            $this->tenantId
        );
        $fulfillment->startPicking();
        $fulfillment->popEvents();

        $fulfillment->startPacking();

        $this->assertTrue($fulfillment->status()->equals(FulfillmentStatus::packing()));
        $this->assertNotNull($fulfillment->packingStartedAt());

        $events = $fulfillment->popEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(FulfillmentStatusChanged::class, $events[0]);
    }

    public function testCanShipWithTrackingInfo(): void
    {
        $fulfillment = Fulfillment::start(
            $this->fulfillmentId,
            $this->orderId,
            $this->warehouseId,
            $this->tenantId
        );
        $fulfillment->startPicking();
        $fulfillment->startPacking();
        $fulfillment->popEvents();

        $fulfillment->ship('UPS', '1Z999AA10123456784');

        $this->assertTrue($fulfillment->status()->equals(FulfillmentStatus::shipping()));
        $this->assertSame('UPS', $fulfillment->carrier());
        $this->assertSame('1Z999AA10123456784', $fulfillment->trackingNumber());
        $this->assertNotNull($fulfillment->shippedAt());

        $events = $fulfillment->popEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(FulfillmentStatusChanged::class, $events[0]);
    }

    public function testShipRequiresCarrier(): void
    {
        $fulfillment = Fulfillment::start(
            $this->fulfillmentId,
            $this->orderId,
            $this->warehouseId,
            $this->tenantId
        );
        $fulfillment->startPicking();
        $fulfillment->startPacking();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Carrier name is required for shipping');

        $fulfillment->ship('', '1Z999AA10123456784');
    }

    public function testShipRequiresTrackingNumber(): void
    {
        $fulfillment = Fulfillment::start(
            $this->fulfillmentId,
            $this->orderId,
            $this->warehouseId,
            $this->tenantId
        );
        $fulfillment->startPicking();
        $fulfillment->startPacking();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Tracking number is required for shipping');

        $fulfillment->ship('UPS', '');
    }

    public function testCanMarkAsDelivered(): void
    {
        $fulfillment = Fulfillment::start(
            $this->fulfillmentId,
            $this->orderId,
            $this->warehouseId,
            $this->tenantId
        );
        $fulfillment->startPicking();
        $fulfillment->startPacking();
        $fulfillment->ship('UPS', '1Z999AA10123456784');
        $fulfillment->popEvents();

        $fulfillment->markAsDelivered();

        $this->assertTrue($fulfillment->status()->equals(FulfillmentStatus::delivered()));
        $this->assertNotNull($fulfillment->deliveredAt());

        $events = $fulfillment->popEvents();
        $this->assertCount(2, $events);
        $this->assertInstanceOf(FulfillmentStatusChanged::class, $events[0]);
        $this->assertInstanceOf(FulfillmentCompleted::class, $events[1]);
    }

    public function testCanCancel(): void
    {
        $fulfillment = Fulfillment::start(
            $this->fulfillmentId,
            $this->orderId,
            $this->warehouseId,
            $this->tenantId
        );
        $fulfillment->startPicking();
        $fulfillment->popEvents();

        $fulfillment->cancel();

        $this->assertTrue($fulfillment->status()->equals(FulfillmentStatus::cancelled()));
        $this->assertNotNull($fulfillment->cancelledAt());

        $events = $fulfillment->popEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(FulfillmentStatusChanged::class, $events[0]);
    }

    public function testCanMarkAsFailed(): void
    {
        $fulfillment = Fulfillment::start(
            $this->fulfillmentId,
            $this->orderId,
            $this->warehouseId,
            $this->tenantId
        );
        $fulfillment->startPicking();
        $fulfillment->popEvents();

        $fulfillment->markAsFailed('Package damaged during transit');

        $this->assertTrue($fulfillment->status()->equals(FulfillmentStatus::failed()));
        $this->assertSame('Package damaged during transit', $fulfillment->failureReason());
        $this->assertNotNull($fulfillment->failedAt());

        $events = $fulfillment->popEvents();
        $this->assertCount(2, $events);
        $this->assertInstanceOf(FulfillmentStatusChanged::class, $events[0]);
        $this->assertInstanceOf(FulfillmentFailed::class, $events[1]);
    }

    public function testMarkAsFailedRequiresReason(): void
    {
        $fulfillment = Fulfillment::start(
            $this->fulfillmentId,
            $this->orderId,
            $this->warehouseId,
            $this->tenantId
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Failure reason is required');

        $fulfillment->markAsFailed('');
    }

    public function testCannotModifyDeliveredFulfillment(): void
    {
        $fulfillment = Fulfillment::start(
            $this->fulfillmentId,
            $this->orderId,
            $this->warehouseId,
            $this->tenantId
        );
        $fulfillment->startPicking();
        $fulfillment->startPacking();
        $fulfillment->ship('UPS', '1Z999AA10123456784');
        $fulfillment->markAsDelivered();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot modify fulfillment in final state: delivered');

        $fulfillment->cancel();
    }

    public function testCannotModifyCancelledFulfillment(): void
    {
        $fulfillment = Fulfillment::start(
            $this->fulfillmentId,
            $this->orderId,
            $this->warehouseId,
            $this->tenantId
        );
        $fulfillment->cancel();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot modify fulfillment in final state: cancelled');

        $fulfillment->startPicking();
    }

    public function testCannotSkipWorkflowSteps(): void
    {
        $fulfillment = Fulfillment::start(
            $this->fulfillmentId,
            $this->orderId,
            $this->warehouseId,
            $this->tenantId
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Invalid status transition from assigned to packing');

        $fulfillment->startPacking(); // Cannot go directly from assigned to packing
    }

    public function testCalculateDuration(): void
    {
        $fulfillment = Fulfillment::start(
            $this->fulfillmentId,
            $this->orderId,
            $this->warehouseId,
            $this->tenantId
        );
        $fulfillment->startPicking();
        $fulfillment->startPacking();
        $fulfillment->ship('UPS', '1Z999AA10123456784');
        $fulfillment->markAsDelivered();

        $duration = $fulfillment->calculateDuration();

        $this->assertInstanceOf(\DateInterval::class, $duration);
    }

    public function testCalculateDurationReturnsNullForIncompleteFulfillment(): void
    {
        $fulfillment = Fulfillment::start(
            $this->fulfillmentId,
            $this->orderId,
            $this->warehouseId,
            $this->tenantId
        );

        $duration = $fulfillment->calculateDuration();

        $this->assertNull($duration);
    }

    public function testCanReconstituteFromPersistence(): void
    {
        $now = new \DateTimeImmutable();

        $fulfillment = Fulfillment::reconstituteFromPersistence(
            $this->fulfillmentId,
            $this->orderId,
            $this->warehouseId,
            $this->tenantId,
            FulfillmentStatus::shipping(),
            '1Z999AA10123456784',
            'UPS',
            $now,
            $now,
            $now,
            $now,
            null,
            null,
            null,
            null,
            $now,
            $now
        );

        $this->assertTrue($fulfillment->id()->equals($this->fulfillmentId));
        $this->assertTrue($fulfillment->orderId()->equals($this->orderId));
        $this->assertTrue($fulfillment->status()->equals(FulfillmentStatus::shipping()));
        $this->assertSame('UPS', $fulfillment->carrier());
        $this->assertSame('1Z999AA10123456784', $fulfillment->trackingNumber());
        $this->assertCount(0, $fulfillment->popEvents()); // No events on reconstitution
    }
}
