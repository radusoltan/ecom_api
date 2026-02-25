<?php

declare(strict_types=1);

namespace App\Tests\Unit\Privacy\Domain\Model;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Privacy\Domain\Event\DataErasureRequested;
use App\Privacy\Domain\Event\DataSubjectRequestCompleted;
use App\Privacy\Domain\Event\DataSubjectRequestSubmitted;
use App\Privacy\Domain\Model\DataSubjectRequest;
use App\Privacy\Domain\ValueObject\DataSubjectRequestId;
use App\Privacy\Domain\ValueObject\RequestStatus;
use App\Privacy\Domain\ValueObject\RequestType;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DataSubjectRequest::class)]
final class DataSubjectRequestTest extends TestCase
{
    private DataSubjectRequestId $requestId;
    private TenantId $tenantId;
    private CustomerId $customerId;

    protected function setUp(): void
    {
        $this->requestId = DataSubjectRequestId::generate();
        $this->tenantId = TenantId::generate();
        $this->customerId = CustomerId::generate();
    }

    #[Test]
    public function submitCreatesRequestWithCorrectState(): void
    {
        $request = $this->submitAccessRequest();

        self::assertTrue($request->id()->equals($this->requestId));
        self::assertTrue($request->tenantId()->equals($this->tenantId));
        self::assertTrue($request->customerId()->equals($this->customerId));
        self::assertTrue($request->requestType()->equals(RequestType::access()));
        self::assertTrue($request->status()->equals(RequestStatus::pending()));
        self::assertNull($request->reason());
        self::assertNull($request->reviewNotes());
        self::assertNull($request->rejectionReason());
        self::assertNull($request->exportData());
        self::assertNull($request->completedAt());
        self::assertFalse($request->isExtended());
    }

    #[Test]
    public function submitSetsDeadlineTo30Days(): void
    {
        $request = $this->submitAccessRequest();

        $expectedDeadline = $request->submittedAt()->modify('+30 days');
        self::assertSame(
            $expectedDeadline->format('Y-m-d'),
            $request->deadline()->format('Y-m-d')
        );
    }

    #[Test]
    public function submitRecordsSubmittedEvent(): void
    {
        $request = $this->submitAccessRequest();

        $events = $request->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(DataSubjectRequestSubmitted::class, $events[0]);
        self::assertTrue($events[0]->requestId->equals($this->requestId));
        self::assertTrue($events[0]->customerId->equals($this->customerId));
        self::assertTrue($events[0]->requestType->equals(RequestType::access()));
        self::assertTrue($events[0]->tenantId->equals($this->tenantId));
    }

    #[Test]
    public function submitErasureRequestRecordsBothEvents(): void
    {
        $request = DataSubjectRequest::submit(
            $this->requestId,
            $this->tenantId,
            $this->customerId,
            RequestType::erasure()
        );

        $events = $request->popEvents();
        self::assertCount(2, $events);
        self::assertInstanceOf(DataSubjectRequestSubmitted::class, $events[0]);
        self::assertInstanceOf(DataErasureRequested::class, $events[1]);
        self::assertTrue($events[0]->tenantId->equals($this->tenantId));
        self::assertTrue($events[1]->customerId->equals($this->customerId));
        self::assertTrue($events[1]->tenantId->equals($this->tenantId));
    }

    #[Test]
    public function submitWithValidReasonAcceptsIt(): void
    {
        $request = DataSubjectRequest::submit(
            $this->requestId,
            $this->tenantId,
            $this->customerId,
            RequestType::access(),
            'I want to know what data you have about me for personal review.'
        );

        self::assertSame('I want to know what data you have about me for personal review.', $request->reason());
    }

    #[Test]
    public function submitWithTooShortReasonThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('at least 10 characters');

        DataSubjectRequest::submit(
            $this->requestId,
            $this->tenantId,
            $this->customerId,
            RequestType::access(),
            'Short'
        );
    }

    #[Test]
    public function submitWithTooLongReasonThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Maximum 1000 characters');

        DataSubjectRequest::submit(
            $this->requestId,
            $this->tenantId,
            $this->customerId,
            RequestType::access(),
            str_repeat('x', 1001)
        );
    }

    #[Test]
    public function startReviewTransitionsToUnderReview(): void
    {
        $request = $this->submitAccessRequest();
        $request->popEvents();

        $request->startReview('Reviewing the access request for compliance.');

        self::assertTrue($request->status()->equals(RequestStatus::underReview()));
        self::assertSame('Reviewing the access request for compliance.', $request->reviewNotes());
    }

    #[Test]
    public function startReviewWithEmptyNotesThrowsException(): void
    {
        $request = $this->submitAccessRequest();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Review notes cannot be empty');

        $request->startReview('');
    }

    #[Test]
    public function startReviewWithTooLongNotesThrowsException(): void
    {
        $request = $this->submitAccessRequest();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Maximum 2000 characters');

        $request->startReview(str_repeat('x', 2001));
    }

    #[Test]
    public function completeFromUnderReviewTransitionsToCompleted(): void
    {
        $request = $this->submitAndReviewRequest(RequestType::erasure());

        $request->complete();

        self::assertTrue($request->status()->equals(RequestStatus::completed()));
        self::assertNotNull($request->completedAt());
    }

    #[Test]
    public function completeRecordsCompletedEvent(): void
    {
        $request = $this->submitAndReviewRequest(RequestType::erasure());

        $request->complete();

        $events = $request->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(DataSubjectRequestCompleted::class, $events[0]);
        self::assertTrue($events[0]->customerId->equals($this->customerId));
        self::assertTrue($events[0]->requestType->equals(RequestType::erasure()));
    }

    #[Test]
    public function completeAccessRequestRequiresExportData(): void
    {
        $request = $this->submitAndReviewRequest(RequestType::access());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Export data is required');

        $request->complete();
    }

    #[Test]
    public function completePortabilityRequestRequiresExportData(): void
    {
        $request = $this->submitAndReviewRequest(RequestType::portability());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Export data is required');

        $request->complete(null);
    }

    #[Test]
    public function completeAccessRequestWithExportDataSucceeds(): void
    {
        $request = $this->submitAndReviewRequest(RequestType::access());
        $exportData = ['personal_data' => ['name' => 'John Doe', 'email' => 'john@example.com']];

        $request->complete($exportData);

        self::assertTrue($request->status()->equals(RequestStatus::completed()));
        self::assertSame($exportData, $request->exportData());
    }

    #[Test]
    public function rejectFromPendingTransitionsToRejected(): void
    {
        $request = $this->submitAccessRequest();
        $request->popEvents();

        $request->reject('The request cannot be fulfilled because the data is required for ongoing legal proceedings.');

        self::assertTrue($request->status()->equals(RequestStatus::rejected()));
        self::assertNotNull($request->completedAt());
        self::assertStringContainsString('legal proceedings', $request->rejectionReason());
    }

    #[Test]
    public function rejectWithEmptyReasonThrowsException(): void
    {
        $request = $this->submitAccessRequest();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Rejection reason cannot be empty');

        $request->reject('');
    }

    #[Test]
    public function rejectWithTooShortReasonThrowsException(): void
    {
        $request = $this->submitAccessRequest();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('at least 20 characters');

        $request->reject('Too short reason');
    }

    #[Test]
    public function rejectWithTooLongReasonThrowsException(): void
    {
        $request = $this->submitAccessRequest();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Maximum 2000 characters');

        $request->reject(str_repeat('x', 2001));
    }

    #[Test]
    public function completeFromPendingThrowsException(): void
    {
        $request = $this->submitAccessRequest();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot transition');

        $request->complete(['data' => 'value']);
    }

    #[Test]
    public function startReviewFromCompletedThrowsException(): void
    {
        $request = $this->submitAndReviewRequest(RequestType::erasure());
        $request->complete();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot transition');

        $request->startReview('Trying to re-review');
    }

    #[Test]
    public function rejectFromRejectedThrowsException(): void
    {
        $request = $this->submitAccessRequest();
        $request->reject('Data required for legal compliance and cannot be deleted per regulations.');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot transition');

        $request->reject('Another rejection reason that is long enough.');
    }

    #[Test]
    public function extendDeadlineSetsTo90Days(): void
    {
        $request = $this->submitAccessRequest();

        $request->extendDeadline();

        $expectedDeadline = $request->submittedAt()->modify('+90 days');
        self::assertSame(
            $expectedDeadline->format('Y-m-d'),
            $request->deadline()->format('Y-m-d')
        );
        self::assertTrue($request->isExtended());
    }

    #[Test]
    public function extendDeadlineTwiceThrowsException(): void
    {
        $request = $this->submitAccessRequest();
        $request->extendDeadline();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('already been extended once');

        $request->extendDeadline();
    }

    #[Test]
    public function extendDeadlineOnCompletedRequestThrowsException(): void
    {
        $request = $this->submitAndReviewRequest(RequestType::erasure());
        $request->complete();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot extend deadline for completed/rejected');

        $request->extendDeadline();
    }

    #[Test]
    public function daysUntilDeadlineReturnsPositiveForFutureDeadline(): void
    {
        $request = $this->submitAccessRequest();

        self::assertGreaterThan(0, $request->daysUntilDeadline());
        self::assertLessThanOrEqual(30, $request->daysUntilDeadline());
    }

    #[Test]
    public function isOverdueReturnsFalseForNewRequest(): void
    {
        $request = $this->submitAccessRequest();

        self::assertFalse($request->isOverdue());
    }

    #[Test]
    public function reconstituteFromPersistencePreservesAllFields(): void
    {
        $submittedAt = new \DateTimeImmutable('2025-01-01');
        $deadline = new \DateTimeImmutable('2025-01-31');
        $createdAt = new \DateTimeImmutable('2025-01-01');
        $updatedAt = new \DateTimeImmutable('2025-01-10');

        $request = DataSubjectRequest::reconstituteFromPersistence(
            $this->requestId,
            $this->tenantId,
            $this->customerId,
            RequestType::erasure(),
            RequestStatus::underReview(),
            'Personal data deletion',
            'Under review by DPO',
            null,
            null,
            $submittedAt,
            null,
            $deadline,
            false,
            $createdAt,
            $updatedAt
        );

        self::assertTrue($request->requestType()->equals(RequestType::erasure()));
        self::assertTrue($request->status()->equals(RequestStatus::underReview()));
        self::assertSame('Personal data deletion', $request->reason());
        self::assertSame('Under review by DPO', $request->reviewNotes());
        self::assertFalse($request->isExtended());
    }

    #[Test]
    public function reconstituteDoesNotRecordEvents(): void
    {
        $request = DataSubjectRequest::reconstituteFromPersistence(
            $this->requestId,
            $this->tenantId,
            $this->customerId,
            RequestType::access(),
            RequestStatus::pending(),
            null,
            null,
            null,
            null,
            new \DateTimeImmutable(),
            null,
            new \DateTimeImmutable('+30 days'),
            false,
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );

        self::assertEmpty($request->popEvents());
    }

    #[Test]
    public function nonDestructiveRequestTypesDoNotEmitErasureEvent(): void
    {
        $types = [
            RequestType::access(),
            RequestType::portability(),
            RequestType::restriction(),
            RequestType::rectification(),
            RequestType::objection(),
        ];

        foreach ($types as $type) {
            $request = DataSubjectRequest::submit(
                DataSubjectRequestId::generate(),
                $this->tenantId,
                $this->customerId,
                $type
            );

            $events = $request->popEvents();
            self::assertCount(1, $events, sprintf('Expected 1 event for type %s', $type->value()));
            self::assertInstanceOf(DataSubjectRequestSubmitted::class, $events[0]);
        }
    }

    private function submitAccessRequest(): DataSubjectRequest
    {
        return DataSubjectRequest::submit(
            $this->requestId,
            $this->tenantId,
            $this->customerId,
            RequestType::access()
        );
    }

    private function submitAndReviewRequest(RequestType $type): DataSubjectRequest
    {
        $request = DataSubjectRequest::submit(
            $this->requestId,
            $this->tenantId,
            $this->customerId,
            $type
        );
        $request->popEvents();
        $request->startReview('Reviewing for compliance verification.');

        return $request;
    }
}
