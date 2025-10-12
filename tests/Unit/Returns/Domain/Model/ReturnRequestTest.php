<?php

declare(strict_types=1);

namespace App\Tests\Unit\Returns\Domain\Model;

use App\Order\Domain\Model\OrderId;
use App\Returns\Domain\Event\ReturnRequestApproved;
use App\Returns\Domain\Event\ReturnRequestCompleted;
use App\Returns\Domain\Event\ReturnRequestCreated;
use App\Returns\Domain\Event\ReturnRequestInspected;
use App\Returns\Domain\Event\ReturnRequestReceived;
use App\Returns\Domain\Event\ReturnRequestRejected;
use App\Returns\Domain\Model\ReturnRequest;
use App\Returns\Domain\ValueObject\ReturnReason;
use App\Returns\Domain\ValueObject\ReturnRequestId;
use App\Returns\Domain\ValueObject\ReturnStatus;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class ReturnRequestTest extends TestCase
{
    private TenantId $tenantId;
    private ReturnRequestId $id;
    private OrderId $orderId;
    private ReturnReason $reason;

    protected function setUp(): void
    {
        $this->tenantId = TenantId::generate();
        $this->id = ReturnRequestId::generate();
        $this->orderId = OrderId::generate();
        $this->reason = ReturnReason::fromString('Product arrived damaged and not functional');
    }

    // Creation Tests

    public function testCreateReturnsNewReturnRequest(): void
    {
        $returnRequest = ReturnRequest::create(
            id: $this->id,
            tenantId: $this->tenantId,
            orderId: $this->orderId,
            reason: $this->reason
        );

        $this->assertInstanceOf(ReturnRequest::class, $returnRequest);
        $this->assertEquals($this->id, $returnRequest->id());
        $this->assertEquals($this->tenantId, $returnRequest->tenantId());
        $this->assertEquals($this->orderId, $returnRequest->orderId());
        $this->assertEquals($this->reason, $returnRequest->reason());
        $this->assertTrue($returnRequest->status()->isRequested());
        $this->assertNull($returnRequest->warehouseId());
        $this->assertNull($returnRequest->refundAmount());
        $this->assertNull($returnRequest->inspectionNotes());
        $this->assertNull($returnRequest->rejectionReason());
        $this->assertNull($returnRequest->isResellable());
    }

    public function testCreateRecordsReturnRequestCreatedEvent(): void
    {
        $returnRequest = ReturnRequest::create(
            id: $this->id,
            tenantId: $this->tenantId,
            orderId: $this->orderId,
            reason: $this->reason
        );

        $events = $returnRequest->getRecordedEvents();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(ReturnRequestCreated::class, $events[0]);
        $this->assertEquals($this->id, $events[0]->returnRequestId);
        $this->assertEquals($this->tenantId, $events[0]->tenantId);
    }

    // Approve Tests

    public function testApproveChangesStatusToApproved(): void
    {
        $returnRequest = $this->createRequestedReturn();

        $returnRequest->approve();

        $this->assertTrue($returnRequest->status()->isApproved());
    }

    public function testApproveRecordsReturnRequestApprovedEvent(): void
    {
        $returnRequest = $this->createRequestedReturn();
        $returnRequest->clearRecordedEvents();

        $returnRequest->approve();

        $events = $returnRequest->getRecordedEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(ReturnRequestApproved::class, $events[0]);
    }

    public function testApproveThrowsExceptionWhenNotInRequestedStatus(): void
    {
        $returnRequest = $this->createRequestedReturn();
        $returnRequest->approve();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot approve return request in status "approved". Must be "requested".');

        $returnRequest->approve();
    }

    // MarkAsReceived Tests

    public function testMarkAsReceivedChangesStatusToReceived(): void
    {
        $returnRequest = $this->createApprovedReturn();

        $returnRequest->markAsReceived('WH001');

        $this->assertTrue($returnRequest->status()->isReceived());
        $this->assertEquals('WH001', $returnRequest->warehouseId());
    }

    public function testMarkAsReceivedRecordsReturnRequestReceivedEvent(): void
    {
        $returnRequest = $this->createApprovedReturn();
        $returnRequest->clearRecordedEvents();

        $returnRequest->markAsReceived('WH001');

        $events = $returnRequest->getRecordedEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(ReturnRequestReceived::class, $events[0]);
        $this->assertEquals('WH001', $events[0]->warehouseId);
    }

    public function testMarkAsReceivedThrowsExceptionWhenNotInApprovedStatus(): void
    {
        $returnRequest = $this->createRequestedReturn();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot mark return as received in status "requested". Must be "approved".');

        $returnRequest->markAsReceived('WH001');
    }

    public function testMarkAsReceivedThrowsExceptionForEmptyWarehouseId(): void
    {
        $returnRequest = $this->createApprovedReturn();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Warehouse ID is required');

        $returnRequest->markAsReceived('');
    }

    // Inspect Tests

    public function testInspectWithResellableItemChangesStatusToInspected(): void
    {
        $returnRequest = $this->createReceivedReturn();

        $returnRequest->inspect(isResellable: true, inspectionNotes: 'Item in perfect condition');

        $this->assertTrue($returnRequest->status()->isInspected());
        $this->assertTrue($returnRequest->isResellable());
        $this->assertEquals('Item in perfect condition', $returnRequest->inspectionNotes());
    }

    public function testInspectWithNonResellableItemChangesStatusToInspected(): void
    {
        $returnRequest = $this->createReceivedReturn();

        $returnRequest->inspect(isResellable: false, inspectionNotes: 'Item heavily damaged');

        $this->assertTrue($returnRequest->status()->isInspected());
        $this->assertFalse($returnRequest->isResellable());
        $this->assertEquals('Item heavily damaged', $returnRequest->inspectionNotes());
    }

    public function testInspectRecordsReturnRequestInspectedEvent(): void
    {
        $returnRequest = $this->createReceivedReturn();
        $returnRequest->clearRecordedEvents();

        $returnRequest->inspect(isResellable: true, inspectionNotes: 'Good condition');

        $events = $returnRequest->getRecordedEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(ReturnRequestInspected::class, $events[0]);
        $this->assertTrue($events[0]->isResellable);
        $this->assertEquals('Good condition', $events[0]->inspectionNotes);
    }

    public function testInspectThrowsExceptionWhenNotInReceivedStatus(): void
    {
        $returnRequest = $this->createRequestedReturn();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot inspect return in status "requested". Must be "received".');

        $returnRequest->inspect(isResellable: true, inspectionNotes: 'Test');
    }

    public function testInspectThrowsExceptionForEmptyInspectionNotes(): void
    {
        $returnRequest = $this->createReceivedReturn();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Inspection notes are required.');

        $returnRequest->inspect(isResellable: true, inspectionNotes: '');
    }

    // Complete Tests

    public function testCompleteChangesStatusToCompleted(): void
    {
        $returnRequest = $this->createInspectedReturn();
        $refundAmount = Money::fromScalars(5000, 'USD');

        $returnRequest->complete($refundAmount);

        $this->assertTrue($returnRequest->status()->isCompleted());
        $this->assertEquals($refundAmount, $returnRequest->refundAmount());
    }

    public function testCompleteRecordsReturnRequestCompletedEvent(): void
    {
        $returnRequest = $this->createInspectedReturn();
        $returnRequest->clearRecordedEvents();
        $refundAmount = Money::fromScalars(5000, 'USD');

        $returnRequest->complete($refundAmount);

        $events = $returnRequest->getRecordedEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(ReturnRequestCompleted::class, $events[0]);
        $this->assertEquals(5000, $events[0]->refundAmount);
        $this->assertEquals('USD', $events[0]->refundCurrency);
    }

    public function testCompleteThrowsExceptionWhenNotInInspectedStatus(): void
    {
        $returnRequest = $this->createRequestedReturn();
        $refundAmount = Money::fromScalars(5000, 'USD');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot complete return in status "requested". Must be "inspected".');

        $returnRequest->complete($refundAmount);
    }

    public function testCompleteThrowsExceptionForNegativeRefundAmount(): void
    {
        $returnRequest = $this->createInspectedReturn();
        $refundAmount = Money::fromScalars(-100, 'USD');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Refund amount must be positive.');

        $returnRequest->complete($refundAmount);
    }

    // Reject Tests

    public function testRejectFromRequestedStatusChangesStatusToRejected(): void
    {
        $returnRequest = $this->createRequestedReturn();

        $returnRequest->reject('Outside return window');

        $this->assertTrue($returnRequest->status()->isRejected());
        $this->assertEquals('Outside return window', $returnRequest->rejectionReason());
    }

    public function testRejectFromInspectedStatusChangesStatusToRejected(): void
    {
        $returnRequest = $this->createInspectedReturn();

        $returnRequest->reject('Item shows signs of misuse');

        $this->assertTrue($returnRequest->status()->isRejected());
        $this->assertEquals('Item shows signs of misuse', $returnRequest->rejectionReason());
    }

    public function testRejectRecordsReturnRequestRejectedEvent(): void
    {
        $returnRequest = $this->createRequestedReturn();
        $returnRequest->clearRecordedEvents();

        $returnRequest->reject('Policy violation');

        $events = $returnRequest->getRecordedEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(ReturnRequestRejected::class, $events[0]);
        $this->assertEquals('Policy violation', $events[0]->rejectionReason);
    }

    public function testRejectThrowsExceptionWhenInApprovedStatus(): void
    {
        $returnRequest = $this->createApprovedReturn();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot reject return in status "approved".');

        $returnRequest->reject('Test reason');
    }

    public function testRejectThrowsExceptionWhenInReceivedStatus(): void
    {
        $returnRequest = $this->createReceivedReturn();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot reject return in status "received".');

        $returnRequest->reject('Test reason');
    }

    public function testRejectThrowsExceptionWhenAlreadyCompleted(): void
    {
        $returnRequest = $this->createInspectedReturn();
        $returnRequest->complete(Money::fromScalars(5000, 'USD'));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot reject return in status "completed".');

        $returnRequest->reject('Test reason');
    }

    public function testRejectThrowsExceptionWhenAlreadyRejected(): void
    {
        $returnRequest = $this->createRequestedReturn();
        $returnRequest->reject('First rejection');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot reject return in status "rejected".');

        $returnRequest->reject('Second rejection');
    }

    public function testRejectThrowsExceptionForEmptyRejectionReason(): void
    {
        $returnRequest = $this->createRequestedReturn();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Rejection reason is required.');

        $returnRequest->reject('');
    }

    // Reconstitution Tests

    public function testReconstituteFromPersistenceCreatesValidReturnRequest(): void
    {
        $createdAt = new \DateTimeImmutable('2025-01-01 10:00:00');
        $updatedAt = new \DateTimeImmutable('2025-01-02 15:30:00');
        $refundAmount = Money::fromScalars(7500, 'EUR');

        $returnRequest = ReturnRequest::reconstituteFromPersistence(
            id: $this->id,
            tenantId: $this->tenantId,
            orderId: $this->orderId,
            reason: ReturnReason::fromString('Wrong item sent - customer error'),
            status: ReturnStatus::inspected(),
            warehouseId: 'WH001',
            refundAmount: $refundAmount,
            isResellable: true,
            inspectionNotes: 'Item is correct size, customer error',
            rejectionReason: null,
            createdAt: $createdAt,
            updatedAt: $updatedAt
        );

        $this->assertInstanceOf(ReturnRequest::class, $returnRequest);
        $this->assertEquals($this->id, $returnRequest->id());
        $this->assertEquals($this->tenantId, $returnRequest->tenantId());
        $this->assertEquals($this->orderId, $returnRequest->orderId());
        $this->assertTrue($returnRequest->status()->isInspected());
        $this->assertEquals('WH001', $returnRequest->warehouseId());
        $this->assertEquals($refundAmount, $returnRequest->refundAmount());
        $this->assertEquals('Item is correct size, customer error', $returnRequest->inspectionNotes());
        $this->assertTrue($returnRequest->isResellable());
        $this->assertNull($returnRequest->rejectionReason());
        $this->assertEquals($createdAt, $returnRequest->createdAt());
        $this->assertEquals($updatedAt, $returnRequest->updatedAt());
        $this->assertEmpty($returnRequest->getRecordedEvents());
    }

    // Event Management Tests

    public function testClearRecordedEventsClearsAllEvents(): void
    {
        $returnRequest = $this->createRequestedReturn();

        $this->assertNotEmpty($returnRequest->getRecordedEvents());

        $returnRequest->clearRecordedEvents();

        $this->assertEmpty($returnRequest->getRecordedEvents());
    }

    public function testMultipleActionsRecordMultipleEvents(): void
    {
        $returnRequest = $this->createRequestedReturn();
        $returnRequest->clearRecordedEvents();

        $returnRequest->approve();
        $returnRequest->markAsReceived('WH001');

        $events = $returnRequest->getRecordedEvents();
        $this->assertCount(2, $events);
        $this->assertInstanceOf(ReturnRequestApproved::class, $events[0]);
        $this->assertInstanceOf(ReturnRequestReceived::class, $events[1]);
    }

    // Complete Workflow Tests

    public function testCompleteHappyPathWorkflow(): void
    {
        $returnRequest = ReturnRequest::create(
            id: $this->id,
            tenantId: $this->tenantId,
            orderId: $this->orderId,
            reason: ReturnReason::fromString('Item arrived broken')
        );

        $this->assertTrue($returnRequest->status()->isRequested());

        $returnRequest->approve();
        $this->assertTrue($returnRequest->status()->isApproved());

        $returnRequest->markAsReceived('WH001');
        $this->assertTrue($returnRequest->status()->isReceived());

        $returnRequest->inspect(isResellable: false, inspectionNotes: 'Confirmed defective');
        $this->assertTrue($returnRequest->status()->isInspected());

        $returnRequest->complete(Money::fromScalars(9999, 'USD'));
        $this->assertTrue($returnRequest->status()->isCompleted());

        $events = $returnRequest->getRecordedEvents();
        $this->assertCount(5, $events);
    }

    public function testEarlyRejectionWorkflow(): void
    {
        $returnRequest = ReturnRequest::create(
            id: $this->id,
            tenantId: $this->tenantId,
            orderId: $this->orderId,
            reason: ReturnReason::fromString('Changed my mind about the purchase')
        );

        $this->assertTrue($returnRequest->status()->isRequested());

        $returnRequest->reject('Return window expired');
        $this->assertTrue($returnRequest->status()->isRejected());
        $this->assertEquals('Return window expired', $returnRequest->rejectionReason());

        $events = $returnRequest->getRecordedEvents();
        $this->assertCount(2, $events);
        $this->assertInstanceOf(ReturnRequestCreated::class, $events[0]);
        $this->assertInstanceOf(ReturnRequestRejected::class, $events[1]);
    }

    public function testLateRejectionAfterInspectionWorkflow(): void
    {
        $returnRequest = $this->createInspectedReturn();
        $returnRequest->clearRecordedEvents();

        $returnRequest->reject('Customer damaged the item');

        $this->assertTrue($returnRequest->status()->isRejected());

        $events = $returnRequest->getRecordedEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(ReturnRequestRejected::class, $events[0]);
    }

    // Helper Methods

    private function createRequestedReturn(): ReturnRequest
    {
        return ReturnRequest::create(
            id: $this->id,
            tenantId: $this->tenantId,
            orderId: $this->orderId,
            reason: $this->reason
        );
    }

    private function createApprovedReturn(): ReturnRequest
    {
        $returnRequest = $this->createRequestedReturn();
        $returnRequest->approve();
        return $returnRequest;
    }

    private function createReceivedReturn(): ReturnRequest
    {
        $returnRequest = $this->createApprovedReturn();
        $returnRequest->markAsReceived('WH001');
        return $returnRequest;
    }

    private function createInspectedReturn(): ReturnRequest
    {
        $returnRequest = $this->createReceivedReturn();
        $returnRequest->inspect(isResellable: true, inspectionNotes: 'Good condition');
        return $returnRequest;
    }
}
