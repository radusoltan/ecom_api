<?php

declare(strict_types=1);

namespace App\Tests\Unit\Privacy\Application\EventSubscriber;

use App\Customer\Domain\Event\AccountDeletionCompleted;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\DeletionRequestId;
use App\Privacy\Application\EventSubscriber\CustomerDeletionCompletedSubscriber;
use App\Privacy\Domain\Model\DataSubjectRequest;
use App\Privacy\Domain\Repository\DataSubjectRequestRepositoryInterface;
use App\Privacy\Domain\ValueObject\DataSubjectRequestId;
use App\Privacy\Domain\ValueObject\RequestStatus;
use App\Privacy\Domain\ValueObject\RequestType;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

#[CoversClass(CustomerDeletionCompletedSubscriber::class)]
#[Group('privacy')]
final class CustomerDeletionCompletedSubscriberTest extends TestCase
{
    private DataSubjectRequestRepositoryInterface&MockObject $dsrRepository;
    private LoggerInterface&MockObject $logger;
    private CustomerDeletionCompletedSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->dsrRepository = $this->createMock(DataSubjectRequestRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->subscriber = new CustomerDeletionCompletedSubscriber(
            $this->dsrRepository,
            $this->logger,
        );
    }

    public function testImplementsEventSubscriberInterface(): void
    {
        $subscriber = new CustomerDeletionCompletedSubscriber(
            $this->createStub(DataSubjectRequestRepositoryInterface::class),
            $this->createStub(LoggerInterface::class),
        );

        self::assertInstanceOf(EventSubscriberInterface::class, $subscriber);
    }

    public function testSubscribesToAccountDeletionCompletedEvent(): void
    {
        $events = CustomerDeletionCompletedSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(AccountDeletionCompleted::class, $events);
        self::assertSame('onAccountDeletionCompleted', $events[AccountDeletionCompleted::class]);
    }

    public function testCompletesErasureDsrWhenDeletionCompleted(): void
    {
        // Arrange
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();

        $erasureDsr = $this->createErasureDsrUnderReview($customerId, $tenantId);

        $event = new AccountDeletionCompleted(
            DeletionRequestId::generate(),
            $customerId,
            $tenantId,
        );

        $this->dsrRepository
            ->expects(self::once())
            ->method('findByCustomerId')
            ->with($customerId)
            ->willReturn([$erasureDsr]);

        $this->dsrRepository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(function (DataSubjectRequest $saved): bool {
                return $saved->status()->equals(RequestStatus::completed())
                    && null !== $saved->completedAt();
            }));

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with(
                'Privacy erasure DSR completed after Customer deletion',
                self::callback(function (array $context) use ($erasureDsr, $customerId): bool {
                    return $context['dsrId'] === $erasureDsr->id()->toString()
                        && $context['customerId'] === $customerId->toString();
                }),
            );

        // Act
        $this->subscriber->onAccountDeletionCompleted($event);

        // Assert: verify final state
        self::assertTrue($erasureDsr->status()->equals(RequestStatus::completed()));
    }

    public function testTransitionsPendingDsrThroughReviewBeforeComplete(): void
    {
        // Arrange: DSR in pending status (not yet under review)
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();

        $pendingErasureDsr = DataSubjectRequest::submit(
            DataSubjectRequestId::generate(),
            $tenantId,
            $customerId,
            RequestType::erasure(),
        );

        // Confirm it starts as pending
        self::assertTrue($pendingErasureDsr->status()->equals(RequestStatus::pending()));

        $event = new AccountDeletionCompleted(
            DeletionRequestId::generate(),
            $customerId,
            $tenantId,
        );

        $this->dsrRepository
            ->method('findByCustomerId')
            ->willReturn([$pendingErasureDsr]);

        $this->dsrRepository
            ->expects(self::once())
            ->method('save');

        $this->logger->method('info');

        // Act
        $this->subscriber->onAccountDeletionCompleted($event);

        // Assert: startReview was called (moved through under_review) then complete was called
        self::assertTrue($pendingErasureDsr->status()->equals(RequestStatus::completed()));
        self::assertSame('Auto-completed via Customer anonymization', $pendingErasureDsr->reviewNotes());
        self::assertNotNull($pendingErasureDsr->completedAt());
    }

    public function testHandlesNoPendingDsr(): void
    {
        // Arrange: no DSRs for this customer
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();

        $event = new AccountDeletionCompleted(
            DeletionRequestId::generate(),
            $customerId,
            $tenantId,
        );

        $this->dsrRepository
            ->expects(self::once())
            ->method('findByCustomerId')
            ->with($customerId)
            ->willReturn([]);

        $this->dsrRepository
            ->expects(self::never())
            ->method('save');

        $this->logger
            ->expects(self::once())
            ->method('debug')
            ->with(
                'No pending Privacy erasure DSR found for completed deletion',
                self::callback(function (array $context) use ($customerId): bool {
                    return $context['customerId'] === $customerId->toString();
                }),
            );

        // Act
        $this->subscriber->onAccountDeletionCompleted($event);
    }

    public function testHandlesNoPendingDsrWhenOnlyFinalDsrsExist(): void
    {
        // Arrange: only a completed erasure DSR exists (isFinal = true)
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();

        $completedErasureDsr = $this->createCompletedErasureDsr($customerId, $tenantId);

        $event = new AccountDeletionCompleted(
            DeletionRequestId::generate(),
            $customerId,
            $tenantId,
        );

        $this->dsrRepository
            ->method('findByCustomerId')
            ->willReturn([$completedErasureDsr]);

        $this->dsrRepository
            ->expects(self::never())
            ->method('save');

        $this->logger
            ->expects(self::once())
            ->method('debug');

        // Act
        $this->subscriber->onAccountDeletionCompleted($event);
    }

    public function testIgnoresNonErasureDsrs(): void
    {
        // Arrange: only access and portability DSRs exist (not erasure)
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();

        $accessDsr = DataSubjectRequest::submit(
            DataSubjectRequestId::generate(),
            $tenantId,
            $customerId,
            RequestType::access(),
        );

        $portabilityDsr = DataSubjectRequest::submit(
            DataSubjectRequestId::generate(),
            $tenantId,
            $customerId,
            RequestType::portability(),
        );

        $event = new AccountDeletionCompleted(
            DeletionRequestId::generate(),
            $customerId,
            $tenantId,
        );

        $this->dsrRepository
            ->method('findByCustomerId')
            ->willReturn([$accessDsr, $portabilityDsr]);

        $this->dsrRepository
            ->expects(self::never())
            ->method('save');

        $this->logger
            ->expects(self::once())
            ->method('debug')
            ->with('No pending Privacy erasure DSR found for completed deletion', self::anything());

        // Act
        $this->subscriber->onAccountDeletionCompleted($event);

        // Assert: non-erasure DSRs remain untouched
        self::assertTrue($accessDsr->status()->equals(RequestStatus::pending()));
        self::assertTrue($portabilityDsr->status()->equals(RequestStatus::pending()));
    }

    public function testUsesFirstMatchingErasureDsrWhenMultipleExist(): void
    {
        // Arrange: multiple DSRs, only the first non-final erasure should be processed
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();

        $accessDsr = DataSubjectRequest::submit(
            DataSubjectRequestId::generate(),
            $tenantId,
            $customerId,
            RequestType::access(),
        );

        $erasureDsr = $this->createErasureDsrUnderReview($customerId, $tenantId);

        $event = new AccountDeletionCompleted(
            DeletionRequestId::generate(),
            $customerId,
            $tenantId,
        );

        $this->dsrRepository
            ->method('findByCustomerId')
            ->willReturn([$accessDsr, $erasureDsr]);

        $this->dsrRepository
            ->expects(self::once())
            ->method('save');

        $this->logger
            ->method('info');

        // Act
        $this->subscriber->onAccountDeletionCompleted($event);

        // Assert: only the erasure DSR was completed; access DSR untouched
        self::assertTrue($erasureDsr->status()->equals(RequestStatus::completed()));
        self::assertTrue($accessDsr->status()->equals(RequestStatus::pending()));
    }

    // --- Helpers ---

    private function createErasureDsrUnderReview(CustomerId $customerId, TenantId $tenantId): DataSubjectRequest
    {
        $dsr = DataSubjectRequest::submit(
            DataSubjectRequestId::generate(),
            $tenantId,
            $customerId,
            RequestType::erasure(),
        );
        $dsr->startReview('Under review for erasure compliance.');

        return $dsr;
    }

    private function createCompletedErasureDsr(CustomerId $customerId, TenantId $tenantId): DataSubjectRequest
    {
        $dsr = DataSubjectRequest::submit(
            DataSubjectRequestId::generate(),
            $tenantId,
            $customerId,
            RequestType::erasure(),
        );
        $dsr->startReview('Reviewed for erasure compliance.');
        $dsr->complete();

        return $dsr;
    }
}
