<?php

declare(strict_types=1);

namespace App\Tests\Unit\Returns\Domain\Event;

use App\Returns\Domain\Event\ReturnRequestApproved;
use App\Returns\Domain\Event\ReturnRequestCompleted;
use App\Returns\Domain\Event\ReturnRequestCreated;
use App\Returns\Domain\Event\ReturnRequestInspected;
use App\Returns\Domain\Event\ReturnRequestReceived;
use App\Returns\Domain\Event\ReturnRequestRejected;
use App\Returns\Domain\ValueObject\ReturnRequestId;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReturnRequestCreated::class)]
#[CoversClass(ReturnRequestApproved::class)]
#[CoversClass(ReturnRequestRejected::class)]
#[CoversClass(ReturnRequestCompleted::class)]
#[CoversClass(ReturnRequestReceived::class)]
#[CoversClass(ReturnRequestInspected::class)]
final class ReturnRequestEventsTest extends TestCase
{
    private ReturnRequestId $returnRequestId;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->returnRequestId = ReturnRequestId::generate();
        $this->tenantId = TenantId::generate();
    }

    // -----------------------------------------------------------------------
    // ReturnRequestCreated
    // -----------------------------------------------------------------------

    #[Test]
    public function itConstructsReturnRequestCreatedWithAllProperties(): void
    {
        $occurredOn = new \DateTimeImmutable('2026-01-15 10:00:00');
        $orderId = '550e8400-e29b-41d4-a716-446655440000';
        $reason = 'Product arrived damaged and is not functional';

        $event = new ReturnRequestCreated(
            returnRequestId: $this->returnRequestId,
            tenantId: $this->tenantId,
            orderId: $orderId,
            reason: $reason,
            occurredOn: $occurredOn,
        );

        self::assertSame($this->returnRequestId, $event->returnRequestId);
        self::assertSame($this->tenantId, $event->tenantId);
        self::assertSame($orderId, $event->orderId);
        self::assertSame($reason, $event->reason);
        self::assertSame($occurredOn, $event->occurredOn);
    }

    #[Test]
    public function itCreatesReturnRequestCreatedViaFactory(): void
    {
        $before = new \DateTimeImmutable();
        $orderId = '550e8400-e29b-41d4-a716-446655440000';
        $reason = 'Wrong item sent by the warehouse team';

        $event = ReturnRequestCreated::create(
            returnRequestId: $this->returnRequestId,
            tenantId: $this->tenantId,
            orderId: $orderId,
            reason: $reason,
        );
        $after = new \DateTimeImmutable();

        self::assertInstanceOf(ReturnRequestCreated::class, $event);
        self::assertSame($this->returnRequestId, $event->returnRequestId);
        self::assertSame($this->tenantId, $event->tenantId);
        self::assertSame($orderId, $event->orderId);
        self::assertSame($reason, $event->reason);
        self::assertGreaterThanOrEqual($before, $event->occurredOn);
        self::assertLessThanOrEqual($after, $event->occurredOn);
    }

    #[Test]
    public function itSetsOccurredOnAutomaticallyForReturnRequestCreated(): void
    {
        $before = new \DateTimeImmutable();

        $event = ReturnRequestCreated::create(
            returnRequestId: $this->returnRequestId,
            tenantId: $this->tenantId,
            orderId: '550e8400-e29b-41d4-a716-446655440000',
            reason: 'Item quality does not match description',
        );

        $after = new \DateTimeImmutable();

        self::assertInstanceOf(\DateTimeImmutable::class, $event->occurredOn);
        self::assertGreaterThanOrEqual($before->getTimestamp(), $event->occurredOn->getTimestamp());
        self::assertLessThanOrEqual($after->getTimestamp(), $event->occurredOn->getTimestamp());
    }

    // -----------------------------------------------------------------------
    // ReturnRequestApproved
    // -----------------------------------------------------------------------

    #[Test]
    public function itConstructsReturnRequestApprovedWithAllProperties(): void
    {
        $occurredOn = new \DateTimeImmutable('2026-01-16 11:00:00');

        $event = new ReturnRequestApproved(
            returnRequestId: $this->returnRequestId,
            tenantId: $this->tenantId,
            occurredOn: $occurredOn,
        );

        self::assertSame($this->returnRequestId, $event->returnRequestId);
        self::assertSame($this->tenantId, $event->tenantId);
        self::assertSame($occurredOn, $event->occurredOn);
    }

    #[Test]
    public function itCreatesReturnRequestApprovedViaFactory(): void
    {
        $before = new \DateTimeImmutable();

        $event = ReturnRequestApproved::create(
            returnRequestId: $this->returnRequestId,
            tenantId: $this->tenantId,
        );

        $after = new \DateTimeImmutable();

        self::assertInstanceOf(ReturnRequestApproved::class, $event);
        self::assertSame($this->returnRequestId, $event->returnRequestId);
        self::assertSame($this->tenantId, $event->tenantId);
        self::assertGreaterThanOrEqual($before->getTimestamp(), $event->occurredOn->getTimestamp());
        self::assertLessThanOrEqual($after->getTimestamp(), $event->occurredOn->getTimestamp());
    }

    #[Test]
    public function itSetsOccurredOnAutomaticallyForReturnRequestApproved(): void
    {
        $event = ReturnRequestApproved::create(
            returnRequestId: $this->returnRequestId,
            tenantId: $this->tenantId,
        );

        self::assertInstanceOf(\DateTimeImmutable::class, $event->occurredOn);
    }

    // -----------------------------------------------------------------------
    // ReturnRequestRejected
    // -----------------------------------------------------------------------

    #[Test]
    public function itConstructsReturnRequestRejectedWithAllProperties(): void
    {
        $occurredOn = new \DateTimeImmutable('2026-01-17 14:00:00');
        $rejectionReason = 'Return window of 30 days has expired';

        $event = new ReturnRequestRejected(
            returnRequestId: $this->returnRequestId,
            tenantId: $this->tenantId,
            rejectionReason: $rejectionReason,
            occurredOn: $occurredOn,
        );

        self::assertSame($this->returnRequestId, $event->returnRequestId);
        self::assertSame($this->tenantId, $event->tenantId);
        self::assertSame($rejectionReason, $event->rejectionReason);
        self::assertSame($occurredOn, $event->occurredOn);
    }

    #[Test]
    public function itCreatesReturnRequestRejectedViaFactory(): void
    {
        $rejectionReason = 'Item shows clear signs of deliberate damage';
        $before = new \DateTimeImmutable();

        $event = ReturnRequestRejected::create(
            returnRequestId: $this->returnRequestId,
            tenantId: $this->tenantId,
            rejectionReason: $rejectionReason,
        );

        $after = new \DateTimeImmutable();

        self::assertInstanceOf(ReturnRequestRejected::class, $event);
        self::assertSame($this->returnRequestId, $event->returnRequestId);
        self::assertSame($this->tenantId, $event->tenantId);
        self::assertSame($rejectionReason, $event->rejectionReason);
        self::assertGreaterThanOrEqual($before->getTimestamp(), $event->occurredOn->getTimestamp());
        self::assertLessThanOrEqual($after->getTimestamp(), $event->occurredOn->getTimestamp());
    }

    #[Test]
    public function itPreservesRejectionReasonInEvent(): void
    {
        $reason = 'Purchased item is not eligible for return per store policy section 4.2';

        $event = ReturnRequestRejected::create(
            returnRequestId: $this->returnRequestId,
            tenantId: $this->tenantId,
            rejectionReason: $reason,
        );

        self::assertSame($reason, $event->rejectionReason);
    }

    // -----------------------------------------------------------------------
    // ReturnRequestCompleted
    // -----------------------------------------------------------------------

    #[Test]
    public function itConstructsReturnRequestCompletedWithAllProperties(): void
    {
        $occurredOn = new \DateTimeImmutable('2026-01-20 09:30:00');
        $refundAmount = 4999;
        $refundCurrency = 'USD';

        $event = new ReturnRequestCompleted(
            returnRequestId: $this->returnRequestId,
            tenantId: $this->tenantId,
            refundAmount: $refundAmount,
            refundCurrency: $refundCurrency,
            occurredOn: $occurredOn,
        );

        self::assertSame($this->returnRequestId, $event->returnRequestId);
        self::assertSame($this->tenantId, $event->tenantId);
        self::assertSame($refundAmount, $event->refundAmount);
        self::assertSame($refundCurrency, $event->refundCurrency);
        self::assertSame($occurredOn, $event->occurredOn);
    }

    #[Test]
    public function itCreatesReturnRequestCompletedViaFactory(): void
    {
        $refundAmount = 12500;
        $refundCurrency = 'EUR';
        $before = new \DateTimeImmutable();

        $event = ReturnRequestCompleted::create(
            returnRequestId: $this->returnRequestId,
            tenantId: $this->tenantId,
            refundAmount: $refundAmount,
            refundCurrency: $refundCurrency,
        );

        $after = new \DateTimeImmutable();

        self::assertInstanceOf(ReturnRequestCompleted::class, $event);
        self::assertSame($this->returnRequestId, $event->returnRequestId);
        self::assertSame($this->tenantId, $event->tenantId);
        self::assertSame($refundAmount, $event->refundAmount);
        self::assertSame($refundCurrency, $event->refundCurrency);
        self::assertGreaterThanOrEqual($before->getTimestamp(), $event->occurredOn->getTimestamp());
        self::assertLessThanOrEqual($after->getTimestamp(), $event->occurredOn->getTimestamp());
    }

    #[Test]
    public function itHandlesZeroRefundAmountInCompletedEvent(): void
    {
        $event = ReturnRequestCompleted::create(
            returnRequestId: $this->returnRequestId,
            tenantId: $this->tenantId,
            refundAmount: 0,
            refundCurrency: 'USD',
        );

        self::assertSame(0, $event->refundAmount);
    }

    #[Test]
    public function itHandlesDifferentCurrenciesInCompletedEvent(): void
    {
        foreach (['USD', 'EUR', 'GBP', 'RON'] as $currency) {
            $event = ReturnRequestCompleted::create(
                returnRequestId: $this->returnRequestId,
                tenantId: $this->tenantId,
                refundAmount: 5000,
                refundCurrency: $currency,
            );

            self::assertSame($currency, $event->refundCurrency);
        }
    }

    // -----------------------------------------------------------------------
    // ReturnRequestReceived
    // -----------------------------------------------------------------------

    #[Test]
    public function itConstructsReturnRequestReceivedWithAllProperties(): void
    {
        $occurredOn = new \DateTimeImmutable('2026-01-18 13:00:00');
        $warehouseId = 'WH-EAST-001';

        $event = new ReturnRequestReceived(
            returnRequestId: $this->returnRequestId,
            tenantId: $this->tenantId,
            warehouseId: $warehouseId,
            occurredOn: $occurredOn,
        );

        self::assertSame($this->returnRequestId, $event->returnRequestId);
        self::assertSame($this->tenantId, $event->tenantId);
        self::assertSame($warehouseId, $event->warehouseId);
        self::assertSame($occurredOn, $event->occurredOn);
    }

    #[Test]
    public function itCreatesReturnRequestReceivedViaFactory(): void
    {
        $warehouseId = 'WH-CENTRAL-002';
        $before = new \DateTimeImmutable();

        $event = ReturnRequestReceived::create(
            returnRequestId: $this->returnRequestId,
            tenantId: $this->tenantId,
            warehouseId: $warehouseId,
        );

        $after = new \DateTimeImmutable();

        self::assertInstanceOf(ReturnRequestReceived::class, $event);
        self::assertSame($this->returnRequestId, $event->returnRequestId);
        self::assertSame($this->tenantId, $event->tenantId);
        self::assertSame($warehouseId, $event->warehouseId);
        self::assertGreaterThanOrEqual($before->getTimestamp(), $event->occurredOn->getTimestamp());
        self::assertLessThanOrEqual($after->getTimestamp(), $event->occurredOn->getTimestamp());
    }

    #[Test]
    public function itPreservesWarehouseIdInReceivedEvent(): void
    {
        $warehouseId = 'WH-UK-NORTH-007';

        $event = ReturnRequestReceived::create(
            returnRequestId: $this->returnRequestId,
            tenantId: $this->tenantId,
            warehouseId: $warehouseId,
        );

        self::assertSame($warehouseId, $event->warehouseId);
    }

    // -----------------------------------------------------------------------
    // ReturnRequestInspected
    // -----------------------------------------------------------------------

    #[Test]
    public function itConstructsReturnRequestInspectedWithAllProperties(): void
    {
        $occurredOn = new \DateTimeImmutable('2026-01-19 16:45:00');
        $isResellable = true;
        $inspectionNotes = 'Item in perfect condition, original packaging intact';

        $event = new ReturnRequestInspected(
            returnRequestId: $this->returnRequestId,
            tenantId: $this->tenantId,
            isResellable: $isResellable,
            inspectionNotes: $inspectionNotes,
            occurredOn: $occurredOn,
        );

        self::assertSame($this->returnRequestId, $event->returnRequestId);
        self::assertSame($this->tenantId, $event->tenantId);
        self::assertTrue($event->isResellable);
        self::assertSame($inspectionNotes, $event->inspectionNotes);
        self::assertSame($occurredOn, $event->occurredOn);
    }

    #[Test]
    public function itCreatesReturnRequestInspectedViaFactoryWhenResellable(): void
    {
        $inspectionNotes = 'Minor cosmetic scratches but fully functional';
        $before = new \DateTimeImmutable();

        $event = ReturnRequestInspected::create(
            returnRequestId: $this->returnRequestId,
            tenantId: $this->tenantId,
            isResellable: true,
            inspectionNotes: $inspectionNotes,
        );

        $after = new \DateTimeImmutable();

        self::assertInstanceOf(ReturnRequestInspected::class, $event);
        self::assertSame($this->returnRequestId, $event->returnRequestId);
        self::assertSame($this->tenantId, $event->tenantId);
        self::assertTrue($event->isResellable);
        self::assertSame($inspectionNotes, $event->inspectionNotes);
        self::assertGreaterThanOrEqual($before->getTimestamp(), $event->occurredOn->getTimestamp());
        self::assertLessThanOrEqual($after->getTimestamp(), $event->occurredOn->getTimestamp());
    }

    #[Test]
    public function itCreatesReturnRequestInspectedViaFactoryWhenNotResellable(): void
    {
        $inspectionNotes = 'Item heavily damaged, cracked screen and bent frame';

        $event = ReturnRequestInspected::create(
            returnRequestId: $this->returnRequestId,
            tenantId: $this->tenantId,
            isResellable: false,
            inspectionNotes: $inspectionNotes,
        );

        self::assertInstanceOf(ReturnRequestInspected::class, $event);
        self::assertFalse($event->isResellable);
        self::assertSame($inspectionNotes, $event->inspectionNotes);
    }

    #[Test]
    public function itPreservesInspectionNotesInInspectedEvent(): void
    {
        $notes = 'Detailed inspection report: item shows wear on all four corners, battery health at 62%, charger missing from package.';

        $event = ReturnRequestInspected::create(
            returnRequestId: $this->returnRequestId,
            tenantId: $this->tenantId,
            isResellable: false,
            inspectionNotes: $notes,
        );

        self::assertSame($notes, $event->inspectionNotes);
    }

    // -----------------------------------------------------------------------
    // Cross-event: identity preservation
    // -----------------------------------------------------------------------

    #[Test]
    public function itPreservesReturnRequestIdAcrossAllEvents(): void
    {
        $fixedId = $this->returnRequestId;

        $created = ReturnRequestCreated::create($fixedId, $this->tenantId, '550e8400-e29b-41d4-a716-446655440000', 'Item not as described in listing');
        $approved = ReturnRequestApproved::create($fixedId, $this->tenantId);
        $received = ReturnRequestReceived::create($fixedId, $this->tenantId, 'WH001');
        $inspected = ReturnRequestInspected::create($fixedId, $this->tenantId, true, 'Good condition');
        $completed = ReturnRequestCompleted::create($fixedId, $this->tenantId, 5000, 'USD');
        $rejected = ReturnRequestRejected::create($fixedId, $this->tenantId, 'Policy violation reason');

        self::assertSame($fixedId->toString(), $created->returnRequestId->toString());
        self::assertSame($fixedId->toString(), $approved->returnRequestId->toString());
        self::assertSame($fixedId->toString(), $received->returnRequestId->toString());
        self::assertSame($fixedId->toString(), $inspected->returnRequestId->toString());
        self::assertSame($fixedId->toString(), $completed->returnRequestId->toString());
        self::assertSame($fixedId->toString(), $rejected->returnRequestId->toString());
    }

    #[Test]
    public function itPreservesTenantIdAcrossAllEvents(): void
    {
        $fixedTenantId = $this->tenantId;

        $created = ReturnRequestCreated::create($this->returnRequestId, $fixedTenantId, '550e8400-e29b-41d4-a716-446655440000', 'Item not as described in listing');
        $approved = ReturnRequestApproved::create($this->returnRequestId, $fixedTenantId);
        $received = ReturnRequestReceived::create($this->returnRequestId, $fixedTenantId, 'WH001');
        $inspected = ReturnRequestInspected::create($this->returnRequestId, $fixedTenantId, true, 'Good condition');
        $completed = ReturnRequestCompleted::create($this->returnRequestId, $fixedTenantId, 5000, 'USD');
        $rejected = ReturnRequestRejected::create($this->returnRequestId, $fixedTenantId, 'Policy violation reason');

        self::assertSame($fixedTenantId->toString(), $created->tenantId->toString());
        self::assertSame($fixedTenantId->toString(), $approved->tenantId->toString());
        self::assertSame($fixedTenantId->toString(), $received->tenantId->toString());
        self::assertSame($fixedTenantId->toString(), $inspected->tenantId->toString());
        self::assertSame($fixedTenantId->toString(), $completed->tenantId->toString());
        self::assertSame($fixedTenantId->toString(), $rejected->tenantId->toString());
    }
}
