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

    // Additional Edge Case Tests (Week 5, Day 11)

    public function testCompleteWithZeroRefundAmountIsAllowed(): void
    {
        // Note: Zero refund is technically allowed by domain model
        // as isNegative() returns false for zero
        $returnRequest = $this->createInspectedReturn();

        $returnRequest->complete(Money::fromScalars(0, 'USD'));

        $this->assertTrue($returnRequest->status()->isCompleted());
        $this->assertEquals(0, $returnRequest->refundAmount()->getAmount());
    }

    public function testCompleteWithDifferentCurrencies(): void
    {
        $returnRequest = $this->createInspectedReturn();

        $refundAmount = Money::fromScalars(10000, 'EUR');
        $returnRequest->complete($refundAmount);

        $this->assertTrue($returnRequest->status()->isCompleted());
        $this->assertEquals($refundAmount, $returnRequest->refundAmount());
        $this->assertEquals('EUR', $returnRequest->refundAmount()->getCurrency()->getCurrencyCode());
    }

    public function testCompleteWithMaximumRefundAmount(): void
    {
        $returnRequest = $this->createInspectedReturn();

        // Test with a very large amount (e.g., $100,000)
        $refundAmount = Money::fromScalars(10000000, 'USD');
        $returnRequest->complete($refundAmount);

        $this->assertTrue($returnRequest->status()->isCompleted());
        $this->assertEquals(10000000, $returnRequest->refundAmount()->getAmount());
    }

    public function testCompleteWithMinimumValidRefundAmount(): void
    {
        $returnRequest = $this->createInspectedReturn();

        // Test with minimum valid amount (1 cent)
        $refundAmount = Money::fromScalars(1, 'USD');
        $returnRequest->complete($refundAmount);

        $this->assertTrue($returnRequest->status()->isCompleted());
        $this->assertEquals(1, $returnRequest->refundAmount()->getAmount());
    }

    public function testMarkAsReceivedWithDifferentWarehouseCodes(): void
    {
        $returnRequest = $this->createApprovedReturn();

        $returnRequest->markAsReceived('WH-CENTRAL-001');

        $this->assertTrue($returnRequest->status()->isReceived());
        $this->assertEquals('WH-CENTRAL-001', $returnRequest->warehouseId());
    }

    public function testMarkAsReceivedWithVeryLongWarehouseId(): void
    {
        $returnRequest = $this->createApprovedReturn();

        $longWarehouseId = 'WH-'.str_repeat('A', 100);
        $returnRequest->markAsReceived($longWarehouseId);

        $this->assertEquals($longWarehouseId, $returnRequest->warehouseId());
    }

    public function testInspectWithVeryLongInspectionNotes(): void
    {
        $returnRequest = $this->createReceivedReturn();

        $longNotes = str_repeat('Item has minor scratches on the surface. ', 50); // ~2000 chars
        $returnRequest->inspect(isResellable: false, inspectionNotes: $longNotes);

        $this->assertEquals($longNotes, $returnRequest->inspectionNotes());
    }

    public function testInspectWithSpecialCharactersInNotes(): void
    {
        $returnRequest = $this->createReceivedReturn();

        $notes = 'Item has defects: <tag>, "quotes", & special chars @ #, 日本語, émoji 😀';
        $returnRequest->inspect(isResellable: false, inspectionNotes: $notes);

        $this->assertEquals($notes, $returnRequest->inspectionNotes());
    }

    public function testRejectWithVeryLongRejectionReason(): void
    {
        $returnRequest = $this->createRequestedReturn();

        $longReason = str_repeat('This return request violates our return policy. ', 20);
        $returnRequest->reject($longReason);

        $this->assertEquals($longReason, $returnRequest->rejectionReason());
    }

    public function testMultipleWarehouseReceiptScenario(): void
    {
        $returnRequest1 = $this->createApprovedReturn();
        $returnRequest1->markAsReceived('WH001');

        $returnRequest2 = ReturnRequest::create(
            id: ReturnRequestId::generate(),
            tenantId: $this->tenantId,
            orderId: $this->orderId,
            reason: ReturnReason::fromString('Different item for same order')
        );
        $returnRequest2->approve();
        $returnRequest2->markAsReceived('WH002');

        $this->assertEquals('WH001', $returnRequest1->warehouseId());
        $this->assertEquals('WH002', $returnRequest2->warehouseId());
    }

    public function testReconstituteWithAllOptionalFieldsPopulated(): void
    {
        $createdAt = new \DateTimeImmutable('2025-01-01 10:00:00');
        $updatedAt = new \DateTimeImmutable('2025-01-05 15:30:00');
        $refundAmount = Money::fromScalars(12999, 'GBP');

        $returnRequest = ReturnRequest::reconstituteFromPersistence(
            id: $this->id,
            tenantId: $this->tenantId,
            orderId: $this->orderId,
            reason: ReturnReason::fromString('Customer ordered wrong size'),
            status: ReturnStatus::completed(),
            warehouseId: 'WH-UK-001',
            refundAmount: $refundAmount,
            isResellable: true,
            inspectionNotes: 'Item in original packaging, tags attached',
            rejectionReason: null,
            createdAt: $createdAt,
            updatedAt: $updatedAt
        );

        $this->assertInstanceOf(ReturnRequest::class, $returnRequest);
        $this->assertTrue($returnRequest->status()->isCompleted());
        $this->assertEquals('WH-UK-001', $returnRequest->warehouseId());
        $this->assertEquals($refundAmount, $returnRequest->refundAmount());
        $this->assertTrue($returnRequest->isResellable());
        $this->assertEquals('Item in original packaging, tags attached', $returnRequest->inspectionNotes());
        $this->assertNull($returnRequest->rejectionReason());
    }

    public function testReconstituteWithRejectedStatus(): void
    {
        $createdAt = new \DateTimeImmutable('2025-01-01 10:00:00');
        $updatedAt = new \DateTimeImmutable('2025-01-02 11:00:00');

        $returnRequest = ReturnRequest::reconstituteFromPersistence(
            id: $this->id,
            tenantId: $this->tenantId,
            orderId: $this->orderId,
            reason: ReturnReason::fromString('Return after 30 days'),
            status: ReturnStatus::rejected(),
            warehouseId: null,
            refundAmount: null,
            isResellable: null,
            inspectionNotes: null,
            rejectionReason: 'Return window of 30 days has expired',
            createdAt: $createdAt,
            updatedAt: $updatedAt
        );

        $this->assertTrue($returnRequest->status()->isRejected());
        $this->assertEquals('Return window of 30 days has expired', $returnRequest->rejectionReason());
        $this->assertNull($returnRequest->refundAmount());
        $this->assertNull($returnRequest->isResellable());
    }

    public function testGettersReturnCorrectValues(): void
    {
        $returnRequest = $this->createInspectedReturn();

        $this->assertInstanceOf(ReturnRequestId::class, $returnRequest->id());
        $this->assertInstanceOf(TenantId::class, $returnRequest->tenantId());
        $this->assertInstanceOf(OrderId::class, $returnRequest->orderId());
        $this->assertInstanceOf(ReturnReason::class, $returnRequest->reason());
        $this->assertInstanceOf(ReturnStatus::class, $returnRequest->status());
        $this->assertInstanceOf(\DateTimeImmutable::class, $returnRequest->createdAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $returnRequest->updatedAt());
        $this->assertIsString($returnRequest->warehouseId());
        $this->assertIsBool($returnRequest->isResellable());
        $this->assertIsString($returnRequest->inspectionNotes());
    }

    public function testInspectTogglesResellableFlag(): void
    {
        $returnRequest = $this->createReceivedReturn();

        // First mark as resellable
        $returnRequest->inspect(isResellable: true, inspectionNotes: 'Perfect condition');
        $this->assertTrue($returnRequest->isResellable());

        // Note: In real implementation, you cannot re-inspect after already inspected
        // This test validates the flag is set correctly
    }
}
