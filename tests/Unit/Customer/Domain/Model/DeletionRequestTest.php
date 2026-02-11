<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Domain\Model;

use App\Customer\Domain\Event\AccountDeletionCancelled;
use App\Customer\Domain\Event\AccountDeletionCompleted;
use App\Customer\Domain\Event\AccountDeletionConfirmed;
use App\Customer\Domain\Event\AccountDeletionHeld;
use App\Customer\Domain\Event\AccountDeletionRequested;
use App\Customer\Domain\Model\DeletionRequest;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\DeletionRequestId;
use App\Customer\Domain\ValueObject\DeletionStatus;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class DeletionRequestTest extends TestCase
{
    private DeletionRequestId $id;
    private CustomerId $customerId;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->id = DeletionRequestId::generate();
        $this->customerId = CustomerId::generate();
        $this->tenantId = TenantId::generate();
    }

    public function testCreate(): void
    {
        $reason = 'No longer need the account';
        $request = DeletionRequest::create(
            id: $this->id,
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            reason: $reason
        );

        self::assertTrue($this->id->equals($request->id()));
        self::assertTrue($this->customerId->equals($request->customerId()));
        self::assertTrue($this->tenantId->equals($request->tenantId()));
        self::assertEquals(DeletionStatus::PENDING, $request->status());
        self::assertEquals($reason, $request->reason());
        self::assertNull($request->holdReason());
        self::assertNull($request->confirmedAt());
        self::assertNull($request->completedAt());
        self::assertInstanceOf(\DateTimeImmutable::class, $request->createdAt());
        self::assertInstanceOf(\DateTimeImmutable::class, $request->scheduledFor());

        // Verify event was recorded
        $events = $request->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(AccountDeletionRequested::class, $events[0]);
    }

    public function testConfirm(): void
    {
        $request = DeletionRequest::create($this->id, $this->customerId, $this->tenantId);

        $request->confirm();

        self::assertEquals(DeletionStatus::CONFIRMED, $request->status());
        self::assertInstanceOf(\DateTimeImmutable::class, $request->confirmedAt());
        self::assertInstanceOf(\DateTimeImmutable::class, $request->scheduledFor());

        // Verify scheduledFor is 30 days from confirmation
        $expectedDate = $request->confirmedAt()->modify('+30 days');
        $diff = $request->scheduledFor()->diff($expectedDate);
        self::assertEquals(0, $diff->days);

        // Verify event was recorded
        $events = $request->pullDomainEvents();
        self::assertCount(2, $events); // Created + Confirmed
        self::assertInstanceOf(AccountDeletionConfirmed::class, $events[1]);
    }

    public function testConfirmSetsScheduledFor30Days(): void
    {
        $request = DeletionRequest::create($this->id, $this->customerId, $this->tenantId);

        $beforeConfirm = new \DateTimeImmutable();
        $request->confirm();
        $afterConfirm = new \DateTimeImmutable();

        // Scheduled date should be 30 days from confirmation time
        $scheduledFor = $request->scheduledFor();
        $expectedMin = $beforeConfirm->modify('+30 days');
        $expectedMax = $afterConfirm->modify('+30 days');

        self::assertGreaterThanOrEqual($expectedMin->getTimestamp(), $scheduledFor->getTimestamp());
        self::assertLessThanOrEqual($expectedMax->getTimestamp(), $scheduledFor->getTimestamp());
    }

    public function testConfirmThrowsWhenNotPending(): void
    {
        $request = DeletionRequest::create($this->id, $this->customerId, $this->tenantId);
        $request->confirm();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot confirm deletion request in status: confirmed');

        $request->confirm();
    }

    public function testCancel(): void
    {
        $request = DeletionRequest::create($this->id, $this->customerId, $this->tenantId);

        $request->cancel();

        self::assertEquals(DeletionStatus::CANCELLED, $request->status());

        // Verify event was recorded
        $events = $request->pullDomainEvents();
        self::assertCount(2, $events);
        self::assertInstanceOf(AccountDeletionCancelled::class, $events[1]);
    }

    public function testCancelFromConfirmedStatus(): void
    {
        $request = DeletionRequest::create($this->id, $this->customerId, $this->tenantId);
        $request->confirm();

        $request->cancel();

        self::assertEquals(DeletionStatus::CANCELLED, $request->status());
    }

    public function testCancelFailsWhenProcessing(): void
    {
        $request = DeletionRequest::create($this->id, $this->customerId, $this->tenantId);
        $request->confirm();

        // Manipulate scheduled date to allow processing
        $pastDate = (new \DateTimeImmutable())->modify('-1 day');
        $reconstituted = DeletionRequest::reconstituteFromPersistence(
            id: $request->id(),
            customerId: $request->customerId(),
            tenantId: $request->tenantId(),
            status: DeletionStatus::PROCESSING,
            reason: $request->reason(),
            holdReason: null,
            scheduledFor: $pastDate,
            confirmedAt: $request->confirmedAt(),
            completedAt: null,
            createdAt: $request->createdAt()
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot cancel deletion request in status: processing');

        $reconstituted->cancel();
    }

    public function testPutOnHold(): void
    {
        $request = DeletionRequest::create($this->id, $this->customerId, $this->tenantId);
        $request->confirm();

        $holdReason = 'Legal investigation pending';
        $request->putOnHold($holdReason);

        self::assertEquals(DeletionStatus::ON_HOLD, $request->status());
        self::assertEquals($holdReason, $request->holdReason());
        self::assertTrue($request->isOnHold());

        // Verify event was recorded
        $events = $request->pullDomainEvents();
        self::assertCount(3, $events);
        self::assertInstanceOf(AccountDeletionHeld::class, $events[2]);
    }

    public function testPutOnHoldThrowsWhenReasonEmpty(): void
    {
        $request = DeletionRequest::create($this->id, $this->customerId, $this->tenantId);
        $request->confirm();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Hold reason is required');

        $request->putOnHold('');
    }

    public function testReleaseHold(): void
    {
        $request = DeletionRequest::create($this->id, $this->customerId, $this->tenantId);
        $request->confirm();
        $request->putOnHold('Legal hold');

        $request->releaseHold();

        self::assertEquals(DeletionStatus::CONFIRMED, $request->status());
        self::assertNull($request->holdReason());
        self::assertFalse($request->isOnHold());
    }

    public function testReleaseHoldThrowsWhenNotOnHold(): void
    {
        $request = DeletionRequest::create($this->id, $this->customerId, $this->tenantId);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Deletion request is not on hold');

        $request->releaseHold();
    }

    public function testProcess(): void
    {
        $request = DeletionRequest::create($this->id, $this->customerId, $this->tenantId);
        $request->confirm();

        // Manipulate scheduled date to be in the past
        $pastDate = (new \DateTimeImmutable())->modify('-1 day');
        $reconstituted = DeletionRequest::reconstituteFromPersistence(
            id: $request->id(),
            customerId: $request->customerId(),
            tenantId: $request->tenantId(),
            status: DeletionStatus::CONFIRMED,
            reason: $request->reason(),
            holdReason: null,
            scheduledFor: $pastDate,
            confirmedAt: $request->confirmedAt(),
            completedAt: null,
            createdAt: $request->createdAt()
        );

        $reconstituted->process();

        self::assertEquals(DeletionStatus::PROCESSING, $reconstituted->status());
    }

    public function testProcessThrowsWhenNotExecutable(): void
    {
        $request = DeletionRequest::create($this->id, $this->customerId, $this->tenantId);
        $request->confirm();

        // scheduledFor is 30 days in future, so not executable yet
        $this->expectException(\DomainException::class);

        $request->process();
    }

    public function testComplete(): void
    {
        $request = DeletionRequest::create($this->id, $this->customerId, $this->tenantId);
        $request->confirm();

        $pastDate = (new \DateTimeImmutable())->modify('-1 day');
        $reconstituted = DeletionRequest::reconstituteFromPersistence(
            id: $request->id(),
            customerId: $request->customerId(),
            tenantId: $request->tenantId(),
            status: DeletionStatus::PROCESSING,
            reason: $request->reason(),
            holdReason: null,
            scheduledFor: $pastDate,
            confirmedAt: $request->confirmedAt(),
            completedAt: null,
            createdAt: $request->createdAt()
        );

        $reconstituted->complete();

        self::assertEquals(DeletionStatus::COMPLETED, $reconstituted->status());
        self::assertInstanceOf(\DateTimeImmutable::class, $reconstituted->completedAt());

        // Verify event was recorded
        $events = $reconstituted->pullDomainEvents();
        self::assertCount(1, $events); // Only from reconstituted (create event not replayed)
        self::assertInstanceOf(AccountDeletionCompleted::class, $events[0]);
    }

    public function testCanBeCancelled(): void
    {
        $pending = DeletionRequest::create($this->id, $this->customerId, $this->tenantId);
        self::assertTrue($pending->canBeCancelled());

        $confirmed = DeletionRequest::create($this->id, $this->customerId, $this->tenantId);
        $confirmed->confirm();
        self::assertTrue($confirmed->canBeCancelled());

        $onHold = DeletionRequest::create($this->id, $this->customerId, $this->tenantId);
        $onHold->confirm();
        $onHold->putOnHold('Legal hold');
        self::assertTrue($onHold->canBeCancelled());

        $processing = DeletionRequest::reconstituteFromPersistence(
            id: $this->id,
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            status: DeletionStatus::PROCESSING,
            reason: null,
            holdReason: null,
            scheduledFor: new \DateTimeImmutable(),
            confirmedAt: new \DateTimeImmutable(),
            completedAt: null,
            createdAt: new \DateTimeImmutable()
        );
        self::assertFalse($processing->canBeCancelled());
    }

    public function testCanBeExecuted(): void
    {
        // Not confirmed yet
        $pending = DeletionRequest::create($this->id, $this->customerId, $this->tenantId);
        self::assertFalse($pending->canBeExecuted());

        // Confirmed but scheduled in future
        $confirmed = DeletionRequest::create($this->id, $this->customerId, $this->tenantId);
        $confirmed->confirm();
        self::assertFalse($confirmed->canBeExecuted());

        // Confirmed, scheduled in past
        $pastDate = (new \DateTimeImmutable())->modify('-1 day');
        $executable = DeletionRequest::reconstituteFromPersistence(
            id: $this->id,
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            status: DeletionStatus::CONFIRMED,
            reason: null,
            holdReason: null,
            scheduledFor: $pastDate,
            confirmedAt: new \DateTimeImmutable(),
            completedAt: null,
            createdAt: new \DateTimeImmutable()
        );
        self::assertTrue($executable->canBeExecuted());

        // On hold
        $onHold = DeletionRequest::reconstituteFromPersistence(
            id: $this->id,
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            status: DeletionStatus::ON_HOLD,
            reason: null,
            holdReason: 'Legal hold',
            scheduledFor: $pastDate,
            confirmedAt: new \DateTimeImmutable(),
            completedAt: null,
            createdAt: new \DateTimeImmutable()
        );
        self::assertFalse($onHold->canBeExecuted());
    }

    public function testIsOnHold(): void
    {
        $request = DeletionRequest::create($this->id, $this->customerId, $this->tenantId);
        self::assertFalse($request->isOnHold());

        $request->confirm();
        $request->putOnHold('Legal hold');
        self::assertTrue($request->isOnHold());

        $request->releaseHold();
        self::assertFalse($request->isOnHold());
    }

    public function testReconstituteFromPersistence(): void
    {
        $scheduledFor = new \DateTimeImmutable('+30 days');
        $confirmedAt = new \DateTimeImmutable('-1 day');
        $createdAt = new \DateTimeImmutable('-2 days');

        $request = DeletionRequest::reconstituteFromPersistence(
            id: $this->id,
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            status: DeletionStatus::CONFIRMED,
            reason: 'Test reason',
            holdReason: null,
            scheduledFor: $scheduledFor,
            confirmedAt: $confirmedAt,
            completedAt: null,
            createdAt: $createdAt
        );

        self::assertTrue($this->id->equals($request->id()));
        self::assertEquals(DeletionStatus::CONFIRMED, $request->status());
        self::assertEquals('Test reason', $request->reason());
        self::assertEquals($scheduledFor, $request->scheduledFor());
        self::assertEquals($confirmedAt, $request->confirmedAt());
        self::assertEquals($createdAt, $request->createdAt());
    }
}
