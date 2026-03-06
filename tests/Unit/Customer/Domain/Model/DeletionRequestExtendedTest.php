<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Domain\Model;

use App\Customer\Domain\Model\DeletionRequest;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\DeletionRequestId;
use App\Customer\Domain\ValueObject\DeletionStatus;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeletionRequest::class)]
final class DeletionRequestExtendedTest extends TestCase
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

    // -----------------------------------------------------------------------
    // create — with optional fields
    // -----------------------------------------------------------------------

    #[Test]
    public function itCreatesWithPrivacyRequestId(): void
    {
        $privacyRequestId = 'privacy-req-abc-123';

        $request = DeletionRequest::create(
            id: $this->id,
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            reason: 'No longer needed',
            privacyRequestId: $privacyRequestId,
        );

        self::assertSame($privacyRequestId, $request->privacyRequestId());
        self::assertSame('No longer needed', $request->reason());
    }

    #[Test]
    public function itCreatesWithNullPrivacyRequestId(): void
    {
        $request = DeletionRequest::create(
            id: $this->id,
            customerId: $this->customerId,
            tenantId: $this->tenantId,
        );

        self::assertNull($request->privacyRequestId());
        self::assertNull($request->reason());
    }

    #[Test]
    public function itSetsScheduledForThirtyDaysFromCreation(): void
    {
        $before = new \DateTimeImmutable();
        $request = DeletionRequest::create($this->id, $this->customerId, $this->tenantId);
        $after = new \DateTimeImmutable();

        $scheduledFor = $request->scheduledFor();

        $expectedMin = $before->modify('+30 days');
        $expectedMax = $after->modify('+30 days');

        self::assertGreaterThanOrEqual($expectedMin->getTimestamp(), $scheduledFor->getTimestamp());
        self::assertLessThanOrEqual($expectedMax->getTimestamp(), $scheduledFor->getTimestamp());
    }

    // -----------------------------------------------------------------------
    // putOnHold — whitespace reason
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsWhenHoldReasonIsOnlyWhitespace(): void
    {
        $request = DeletionRequest::create($this->id, $this->customerId, $this->tenantId);
        $request->confirm();

        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Hold reason is required');

        $request->putOnHold('   ');
    }

    #[Test]
    public function itThrowsWhenPuttingPendingRequestOnHold(): void
    {
        // PENDING -> ON_HOLD is not a valid transition
        $request = DeletionRequest::create($this->id, $this->customerId, $this->tenantId);

        self::expectException(\DomainException::class);
        self::expectExceptionMessage('Cannot put deletion request on hold in status: pending');

        $request->putOnHold('Legal hold reason');
    }

    // -----------------------------------------------------------------------
    // cancel — from ON_HOLD
    // -----------------------------------------------------------------------

    #[Test]
    public function itCancelsFromOnHoldStatus(): void
    {
        $request = DeletionRequest::create($this->id, $this->customerId, $this->tenantId);
        $request->confirm();
        $request->putOnHold('Legal issue');

        $request->cancel();

        self::assertSame(DeletionStatus::CANCELLED, $request->status());
    }

    #[Test]
    public function itCannotCancelFromCompletedStatus(): void
    {
        $pastDate = (new \DateTimeImmutable())->modify('-1 day');
        $completed = DeletionRequest::reconstituteFromPersistence(
            id: $this->id,
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            status: DeletionStatus::COMPLETED,
            reason: null,
            holdReason: null,
            scheduledFor: $pastDate,
            confirmedAt: new \DateTimeImmutable(),
            completedAt: new \DateTimeImmutable(),
            createdAt: new \DateTimeImmutable(),
        );

        self::assertFalse($completed->canBeCancelled());

        self::expectException(\DomainException::class);
        self::expectExceptionMessage('Cannot cancel deletion request in status: completed');

        $completed->cancel();
    }

    #[Test]
    public function itCannotCancelFromCancelledStatus(): void
    {
        $cancelled = DeletionRequest::reconstituteFromPersistence(
            id: $this->id,
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            status: DeletionStatus::CANCELLED,
            reason: null,
            holdReason: null,
            scheduledFor: new \DateTimeImmutable(),
            confirmedAt: null,
            completedAt: null,
            createdAt: new \DateTimeImmutable(),
        );

        self::assertFalse($cancelled->canBeCancelled());

        self::expectException(\DomainException::class);
        $cancelled->cancel();
    }

    // -----------------------------------------------------------------------
    // process — invalid status paths
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsWhenProcessingPendingRequest(): void
    {
        $request = DeletionRequest::create($this->id, $this->customerId, $this->tenantId);

        self::expectException(\DomainException::class);

        $request->process();
    }

    #[Test]
    public function itThrowsWhenProcessingOnHoldRequest(): void
    {
        $pastDate = (new \DateTimeImmutable())->modify('-1 day');
        $onHold = DeletionRequest::reconstituteFromPersistence(
            id: $this->id,
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            status: DeletionStatus::ON_HOLD,
            reason: null,
            holdReason: 'Active hold',
            scheduledFor: $pastDate,
            confirmedAt: new \DateTimeImmutable(),
            completedAt: null,
            createdAt: new \DateTimeImmutable(),
        );

        // ON_HOLD status means canBeExecuted() = false (isOnHold() returns true)
        self::assertFalse($onHold->canBeExecuted());

        self::expectException(\DomainException::class);

        $onHold->process();
    }

    // -----------------------------------------------------------------------
    // complete — invalid status path
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsWhenCompletingConfirmedRequest(): void
    {
        $request = DeletionRequest::create($this->id, $this->customerId, $this->tenantId);
        $request->confirm();

        // CONFIRMED -> COMPLETED is not valid (must go through PROCESSING)
        self::expectException(\DomainException::class);
        self::expectExceptionMessage('Cannot complete deletion request in status: confirmed');

        $request->complete();
    }

    #[Test]
    public function itThrowsWhenCompletingAlreadyCompletedRequest(): void
    {
        $completed = DeletionRequest::reconstituteFromPersistence(
            id: $this->id,
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            status: DeletionStatus::COMPLETED,
            reason: null,
            holdReason: null,
            scheduledFor: new \DateTimeImmutable(),
            confirmedAt: new \DateTimeImmutable(),
            completedAt: new \DateTimeImmutable(),
            createdAt: new \DateTimeImmutable(),
        );

        self::expectException(\DomainException::class);
        self::expectExceptionMessage('Cannot complete deletion request in status: completed');

        $completed->complete();
    }

    // -----------------------------------------------------------------------
    // reconstituteFromPersistence — with privacyRequestId
    // -----------------------------------------------------------------------

    #[Test]
    public function itReconstituteFromPersistenceWithPrivacyRequestId(): void
    {
        $privacyId = 'priv-123';
        $request = DeletionRequest::reconstituteFromPersistence(
            id: $this->id,
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            status: DeletionStatus::PENDING,
            reason: 'Test',
            holdReason: null,
            scheduledFor: new \DateTimeImmutable('+30 days'),
            confirmedAt: null,
            completedAt: null,
            createdAt: new \DateTimeImmutable(),
            privacyRequestId: $privacyId,
        );

        self::assertSame($privacyId, $request->privacyRequestId());
        // Reconstitution should not record events
        self::assertFalse($request->hasEvents());
    }

    // -----------------------------------------------------------------------
    // canBeExecuted — with ON_HOLD status (isOnHold check)
    // -----------------------------------------------------------------------

    #[Test]
    public function itCannotBeExecutedWhenOnHoldEvenIfScheduledInPast(): void
    {
        $pastDate = (new \DateTimeImmutable())->modify('-1 day');

        $request = DeletionRequest::reconstituteFromPersistence(
            id: $this->id,
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            status: DeletionStatus::ON_HOLD,
            reason: null,
            holdReason: 'Legal hold',
            scheduledFor: $pastDate,
            confirmedAt: new \DateTimeImmutable('-2 days'),
            completedAt: null,
            createdAt: new \DateTimeImmutable('-5 days'),
        );

        self::assertTrue($request->isOnHold());
        self::assertFalse($request->canBeExecuted());
    }

    // -----------------------------------------------------------------------
    // canBeExecuted — scheduledFor exactly now
    // -----------------------------------------------------------------------

    #[Test]
    public function itCanBeExecutedWhenScheduledForIsExactlyNow(): void
    {
        // A datetime slightly in the past ensures the <= comparison is true
        $justPast = (new \DateTimeImmutable())->modify('-1 second');

        $request = DeletionRequest::reconstituteFromPersistence(
            id: $this->id,
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            status: DeletionStatus::CONFIRMED,
            reason: null,
            holdReason: null,
            scheduledFor: $justPast,
            confirmedAt: new \DateTimeImmutable('-31 days'),
            completedAt: null,
            createdAt: new \DateTimeImmutable('-31 days'),
        );

        self::assertTrue($request->canBeExecuted());
    }

    // -----------------------------------------------------------------------
    // Getters coverage
    // -----------------------------------------------------------------------

    #[Test]
    public function itExposesAllGetters(): void
    {
        $scheduledFor = new \DateTimeImmutable('+30 days');
        $confirmedAt = new \DateTimeImmutable();
        $completedAt = new \DateTimeImmutable('+1 day');
        $createdAt = new \DateTimeImmutable('-1 day');

        $request = DeletionRequest::reconstituteFromPersistence(
            id: $this->id,
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            status: DeletionStatus::COMPLETED,
            reason: 'Done',
            holdReason: null,
            scheduledFor: $scheduledFor,
            confirmedAt: $confirmedAt,
            completedAt: $completedAt,
            createdAt: $createdAt,
            privacyRequestId: 'priv-xyz',
        );

        self::assertTrue($this->id->equals($request->id()));
        self::assertTrue($this->customerId->equals($request->customerId()));
        self::assertTrue($this->tenantId->equals($request->tenantId()));
        self::assertSame(DeletionStatus::COMPLETED, $request->status());
        self::assertSame('Done', $request->reason());
        self::assertNull($request->holdReason());
        self::assertEquals($scheduledFor, $request->scheduledFor());
        self::assertEquals($confirmedAt, $request->confirmedAt());
        self::assertEquals($completedAt, $request->completedAt());
        self::assertEquals($createdAt, $request->createdAt());
        self::assertSame('priv-xyz', $request->privacyRequestId());
    }
}
