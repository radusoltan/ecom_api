<?php

declare(strict_types=1);

namespace App\Tests\Integration\Privacy;

use App\Customer\Application\EventSubscriber\PrivacyErasureRequestSubscriber;
use App\Customer\Domain\Event\AccountDeletionCompleted;
use App\Customer\Domain\Model\DeletionRequest;
use App\Customer\Domain\Repository\DeletionRequestRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\DeletionRequestId;
use App\Privacy\Application\EventSubscriber\CustomerDeletionCompletedSubscriber;
use App\Privacy\Domain\Event\DataErasureRequested;
use App\Privacy\Domain\Model\DataSubjectRequest;
use App\Privacy\Domain\Repository\DataSubjectRequestRepositoryInterface;
use App\Privacy\Domain\ValueObject\DataSubjectRequestId;
use App\Privacy\Domain\ValueObject\RequestStatus;
use App\Privacy\Domain\ValueObject\RequestType;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Integration tests for the Privacy→Customer erasure DSR flow.
 *
 * Tests the full cross-context event chain:
 * DataSubjectRequest::submit(erasure) → DataErasureRequested event
 *   → PrivacyErasureRequestSubscriber → DeletionRequest created
 *   → (deletion executes) → AccountDeletionCompleted event
 *   → CustomerDeletionCompletedSubscriber → DSR marked completed
 */
final class ErasureDsrFlowTest extends TestCase
{
    private const DEFAULT_TENANT_ID = '00000000-0000-4000-8000-000000000001';
    private const DEFAULT_CUSTOMER_ID = 'a0000000-0000-4000-8000-000000000001';

    private DeletionRequestRepositoryInterface $deletionRequestRepository;
    private DataSubjectRequestRepositoryInterface $dsrRepository;
    private LoggerInterface $erasureLogger;
    private LoggerInterface $completionLogger;

    private PrivacyErasureRequestSubscriber $erasureSubscriber;
    private CustomerDeletionCompletedSubscriber $completionSubscriber;

    private TenantId $tenantId;
    private CustomerId $customerId;

    protected function setUp(): void
    {
        $this->deletionRequestRepository = $this->createMock(DeletionRequestRepositoryInterface::class);
        $this->dsrRepository = $this->createMock(DataSubjectRequestRepositoryInterface::class);
        $this->erasureLogger = $this->createMock(LoggerInterface::class);
        $this->completionLogger = $this->createMock(LoggerInterface::class);

        $this->erasureSubscriber = new PrivacyErasureRequestSubscriber(
            deletionRequestRepository: $this->deletionRequestRepository,
            logger: $this->erasureLogger,
        );

        $this->completionSubscriber = new CustomerDeletionCompletedSubscriber(
            dsrRepository: $this->dsrRepository,
            logger: $this->completionLogger,
        );

        $this->tenantId = TenantId::fromString(self::DEFAULT_TENANT_ID);
        $this->customerId = CustomerId::fromString(self::DEFAULT_CUSTOMER_ID);
    }

    public function testErasureFlowFromDsrToCompletion(): void
    {
        // --- Phase 1: Submit erasure DSR → DataErasureRequested ---

        $dsrId = DataSubjectRequestId::generate();

        $dsr = DataSubjectRequest::submit(
            id: $dsrId,
            tenantId: $this->tenantId,
            customerId: $this->customerId,
            requestType: RequestType::erasure(),
        );

        // Erasure DSR emits two events: DataSubjectRequestSubmitted + DataErasureRequested
        $events = $dsr->popEvents();
        $this->assertCount(2, $events);

        $erasureRequestedEvent = null;
        foreach ($events as $event) {
            if ($event instanceof DataErasureRequested) {
                $erasureRequestedEvent = $event;
                break;
            }
        }

        $this->assertNotNull($erasureRequestedEvent, 'DataErasureRequested event must be emitted for erasure DSR');
        $this->assertSame(self::DEFAULT_CUSTOMER_ID, $erasureRequestedEvent->customerId->toString());
        $this->assertSame(self::DEFAULT_TENANT_ID, $erasureRequestedEvent->tenantId->toString());

        // --- Phase 2: PrivacyErasureRequestSubscriber creates DeletionRequest ---

        // No existing pending request
        $this->deletionRequestRepository
            ->expects($this->once())
            ->method('findPendingByCustomerId')
            ->with(
                $this->callback(fn (CustomerId $id) => self::DEFAULT_CUSTOMER_ID === $id->toString()),
                $this->callback(fn (TenantId $id) => self::DEFAULT_TENANT_ID === $id->toString()),
            )
            ->willReturn(null);

        // Capture the DeletionRequest that gets saved
        $savedDeletionRequest = null;
        $this->deletionRequestRepository
            ->expects($this->once())
            ->method('save')
            ->willReturnCallback(function (DeletionRequest $request) use (&$savedDeletionRequest): void {
                $savedDeletionRequest = $request;
            });

        $this->erasureSubscriber->onDataErasureRequested($erasureRequestedEvent);

        // Verify DeletionRequest was created and auto-confirmed
        $this->assertNotNull($savedDeletionRequest);
        $this->assertSame(self::DEFAULT_CUSTOMER_ID, $savedDeletionRequest->customerId()->toString());

        // The subscriber calls confirm() on the deletion request after creation
        // so status should be CONFIRMED
        $this->assertSame('confirmed', $savedDeletionRequest->status()->value);

        // Reason should reference the DSR ID
        $this->assertNotNull($savedDeletionRequest->reason());
        $this->assertStringContainsString($dsrId->toString(), $savedDeletionRequest->reason());

        // --- Phase 3: Simulate deletion completion → AccountDeletionCompleted ---

        $deletionRequestId = DeletionRequestId::generate();
        $accountDeletionCompleted = new AccountDeletionCompleted(
            requestId: $deletionRequestId,
            customerId: $this->customerId,
            tenantId: $this->tenantId,
        );

        // --- Phase 4: CustomerDeletionCompletedSubscriber completes the Privacy DSR ---

        // The subscriber looks up erasure DSRs for the customer
        // We use the real DSR we submitted above — it is still pending (popEvents cleared it)
        $this->dsrRepository
            ->expects($this->once())
            ->method('findByCustomerId')
            ->with($this->callback(fn (CustomerId $id) => self::DEFAULT_CUSTOMER_ID === $id->toString()))
            ->willReturn([$dsr]);

        // DSR must be saved after completion
        $completedDsr = null;
        $this->dsrRepository
            ->expects($this->once())
            ->method('save')
            ->willReturnCallback(function (DataSubjectRequest $request) use (&$completedDsr): void {
                $completedDsr = $request;
            });

        $this->completionSubscriber->onAccountDeletionCompleted($accountDeletionCompleted);

        // Assert the DSR is now completed
        $this->assertNotNull($completedDsr);
        $this->assertSame('completed', $completedDsr->status()->value());
        $this->assertNotNull($completedDsr->completedAt());
    }

    public function testErasureFlowIdempotent(): void
    {
        // Arrange: submit erasure DSR
        $dsr = DataSubjectRequest::submit(
            id: DataSubjectRequestId::generate(),
            tenantId: $this->tenantId,
            customerId: $this->customerId,
            requestType: RequestType::erasure(),
        );

        $events = $dsr->popEvents();
        $erasureEvent = null;
        foreach ($events as $event) {
            if ($event instanceof DataErasureRequested) {
                $erasureEvent = $event;
                break;
            }
        }
        $this->assertNotNull($erasureEvent);

        // Simulate existing pending DeletionRequest already present
        $existingDeletionRequest = DeletionRequest::create(
            id: DeletionRequestId::generate(),
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            reason: 'GDPR erasure request (previous)',
        );

        $this->deletionRequestRepository
            ->expects($this->once())
            ->method('findPendingByCustomerId')
            ->willReturn($existingDeletionRequest);

        // Because a pending request already exists, save must NOT be called a second time
        $this->deletionRequestRepository
            ->expects($this->never())
            ->method('save');

        $this->erasureLogger->expects($this->once())->method('info');

        // Act: fire the event again (simulates duplicate message / retry)
        $this->erasureSubscriber->onDataErasureRequested($erasureEvent);
    }

    public function testErasureFlowDoesNothingWhenNoPendingDsrFound(): void
    {
        // Arrange: AccountDeletionCompleted fired but no Privacy DSR exists
        $accountDeletionCompleted = new AccountDeletionCompleted(
            requestId: DeletionRequestId::generate(),
            customerId: $this->customerId,
            tenantId: $this->tenantId,
        );

        $this->dsrRepository
            ->expects($this->once())
            ->method('findByCustomerId')
            ->willReturn([]); // no DSRs at all

        // DSR repository save must NOT be called
        $this->dsrRepository->expects($this->never())->method('save');

        $this->completionLogger->expects($this->once())->method('debug');

        // Act
        $this->completionSubscriber->onAccountDeletionCompleted($accountDeletionCompleted);
    }

    public function testErasureFlowSkipsAlreadyCompletedDsr(): void
    {
        // Arrange: DSR that is already in a final state (completed)
        // We need a reconstituted DSR in completed state
        $completedDsr = DataSubjectRequest::reconstituteFromPersistence(
            id: DataSubjectRequestId::generate(),
            tenantId: $this->tenantId,
            customerId: $this->customerId,
            requestType: RequestType::erasure(),
            status: RequestStatus::completed(),
            reason: null,
            reviewNotes: 'Previously completed',
            rejectionReason: null,
            exportData: null,
            submittedAt: new \DateTimeImmutable('-2 days'),
            completedAt: new \DateTimeImmutable('-1 day'),
            deadline: new \DateTimeImmutable('+28 days'),
            isExtended: false,
            createdAt: new \DateTimeImmutable('-2 days'),
            updatedAt: new \DateTimeImmutable('-1 day'),
        );

        $accountDeletionCompleted = new AccountDeletionCompleted(
            requestId: DeletionRequestId::generate(),
            customerId: $this->customerId,
            tenantId: $this->tenantId,
        );

        $this->dsrRepository
            ->method('findByCustomerId')
            ->willReturn([$completedDsr]);

        // Already final → save must NOT be called
        $this->dsrRepository->expects($this->never())->method('save');

        // Act: subscriber finds only final DSRs, so it logs debug and returns
        $this->completionSubscriber->onAccountDeletionCompleted($accountDeletionCompleted);
    }

    public function testDataErasureRequestedEventHasCorrectProperties(): void
    {
        // Arrange
        $dsrId = DataSubjectRequestId::generate();

        $dsr = DataSubjectRequest::submit(
            id: $dsrId,
            tenantId: $this->tenantId,
            customerId: $this->customerId,
            requestType: RequestType::erasure(),
        );

        $events = $dsr->popEvents();

        // Assert event structure
        $erasureEvent = null;
        foreach ($events as $event) {
            if ($event instanceof DataErasureRequested) {
                $erasureEvent = $event;
                break;
            }
        }

        $this->assertNotNull($erasureEvent);
        $this->assertTrue($erasureEvent->requestId->equals($dsrId));
        $this->assertSame(self::DEFAULT_CUSTOMER_ID, $erasureEvent->customerId->toString());
        $this->assertSame(self::DEFAULT_TENANT_ID, $erasureEvent->tenantId->toString());
        $this->assertInstanceOf(\DateTimeImmutable::class, $erasureEvent->occurredOn);
    }

    public function testNonErasureRequestTypeDoesNotEmitDataErasureRequested(): void
    {
        // Access requests must NOT emit DataErasureRequested
        $dsr = DataSubjectRequest::submit(
            id: DataSubjectRequestId::generate(),
            tenantId: $this->tenantId,
            customerId: $this->customerId,
            requestType: RequestType::access(),
        );

        $events = $dsr->popEvents();

        $erasureEvents = array_filter($events, fn ($e) => $e instanceof DataErasureRequested);
        $this->assertCount(0, $erasureEvents, 'Access DSR must not emit DataErasureRequested');
    }

    public function testCompletionSubscriberTransitionsPendingDsrThroughReviewToCompleted(): void
    {
        // Arrange: pending DSR (never reviewed)
        $dsr = DataSubjectRequest::submit(
            id: DataSubjectRequestId::generate(),
            tenantId: $this->tenantId,
            customerId: $this->customerId,
            requestType: RequestType::erasure(),
        );
        $dsr->popEvents(); // clear events

        $this->assertSame('pending', $dsr->status()->value());

        $accountDeletionCompleted = new AccountDeletionCompleted(
            requestId: DeletionRequestId::generate(),
            customerId: $this->customerId,
            tenantId: $this->tenantId,
        );

        $this->dsrRepository->method('findByCustomerId')->willReturn([$dsr]);
        $this->dsrRepository->expects($this->once())->method('save');

        // Act
        $this->completionSubscriber->onAccountDeletionCompleted($accountDeletionCompleted);

        // Assert: DSR auto-transitioned pending → under_review → completed
        $this->assertSame('completed', $dsr->status()->value());
        $this->assertNotNull($dsr->completedAt());
    }
}
