<?php

declare(strict_types=1);

namespace App\Tests\Unit\Privacy\Application\EventSubscriber;

use App\Customer\Domain\Event\DataExportCompleted;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\DataExportRequestId;
use App\Privacy\Application\EventSubscriber\CustomerDataExportCompletedSubscriber;
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

#[CoversClass(CustomerDataExportCompletedSubscriber::class)]
#[Group('privacy')]
final class CustomerDataExportCompletedSubscriberTest extends TestCase
{
    private DataSubjectRequestRepositoryInterface&MockObject $dsrRepository;
    private LoggerInterface&MockObject $logger;
    private CustomerDataExportCompletedSubscriber $subscriber;
    private string $tempFilePath;

    protected function setUp(): void
    {
        $this->dsrRepository = $this->createMock(DataSubjectRequestRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->subscriber = new CustomerDataExportCompletedSubscriber(
            $this->dsrRepository,
            $this->logger,
        );

        // Create a temporary JSON export file for use in tests
        $this->tempFilePath = tempnam(sys_get_temp_dir(), 'dsr_export_test_');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFilePath)) {
            unlink($this->tempFilePath);
        }
    }

    public function testImplementsEventSubscriberInterface(): void
    {
        self::assertInstanceOf(EventSubscriberInterface::class, $this->subscriber);
    }

    public function testSubscribesToDataExportCompletedEvent(): void
    {
        $events = CustomerDataExportCompletedSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(DataExportCompleted::class, $events);
        self::assertSame('onDataExportCompleted', $events[DataExportCompleted::class]);
    }

    public function testCompletesDsrWithExportData(): void
    {
        // Arrange
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();
        $exportData = ['name' => 'Jane Doe', 'email' => 'jane@example.com', 'orders' => []];

        file_put_contents($this->tempFilePath, json_encode($exportData));

        $accessDsr = $this->createAccessDsrUnderReview($customerId, $tenantId);

        $event = $this->createDataExportCompletedEvent($customerId, $tenantId, $this->tempFilePath);

        $this->dsrRepository
            ->expects(self::once())
            ->method('findByCustomerId')
            ->with($customerId)
            ->willReturn([$accessDsr]);

        $this->dsrRepository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(function (DataSubjectRequest $saved) use ($exportData): bool {
                return $saved->status()->equals(RequestStatus::completed())
                    && $saved->exportData() === $exportData
                    && null !== $saved->completedAt();
            }));

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with(
                'Privacy access/portability DSR completed with export data',
                self::callback(function (array $context) use ($accessDsr, $customerId): bool {
                    return $context['dsrId'] === $accessDsr->id()->toString()
                        && $context['customerId'] === $customerId->toString()
                        && $context['filePath'] === $this->tempFilePath;
                }),
            );

        // Act
        $this->subscriber->onDataExportCompleted($event);

        // Assert final state
        self::assertTrue($accessDsr->status()->equals(RequestStatus::completed()));
        self::assertSame($exportData, $accessDsr->exportData());
    }

    public function testTransitionsPendingDsrThroughReviewBeforeComplete(): void
    {
        // Arrange: DSR starts in pending status
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();
        $exportData = ['profile' => ['firstName' => 'John']];

        file_put_contents($this->tempFilePath, json_encode($exportData));

        $pendingAccessDsr = DataSubjectRequest::submit(
            DataSubjectRequestId::generate(),
            $tenantId,
            $customerId,
            RequestType::access(),
        );

        self::assertTrue($pendingAccessDsr->status()->equals(RequestStatus::pending()));

        $event = $this->createDataExportCompletedEvent($customerId, $tenantId, $this->tempFilePath);

        $this->dsrRepository
            ->method('findByCustomerId')
            ->willReturn([$pendingAccessDsr]);

        $this->dsrRepository
            ->expects(self::once())
            ->method('save');

        $this->logger->method('info');

        // Act
        $this->subscriber->onDataExportCompleted($event);

        // Assert: passed through pending -> under_review -> completed
        self::assertTrue($pendingAccessDsr->status()->equals(RequestStatus::completed()));
        self::assertSame('Auto-completed via Customer data export', $pendingAccessDsr->reviewNotes());
        self::assertSame($exportData, $pendingAccessDsr->exportData());
        self::assertNotNull($pendingAccessDsr->completedAt());
    }

    public function testHandlesMissingExportFile(): void
    {
        // Arrange: file path that does not exist
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();
        $nonExistentPath = '/tmp/dsr_test_does_not_exist_' . uniqid() . '.json';

        $accessDsr = $this->createAccessDsrUnderReview($customerId, $tenantId);

        $event = $this->createDataExportCompletedEvent($customerId, $tenantId, $nonExistentPath);

        $this->dsrRepository
            ->method('findByCustomerId')
            ->willReturn([$accessDsr]);

        $this->dsrRepository
            ->expects(self::never())
            ->method('save');

        $this->logger
            ->expects(self::once())
            ->method('error')
            ->with(
                'Failed to read export file for Privacy DSR completion',
                self::callback(function (array $context) use ($nonExistentPath, $accessDsr): bool {
                    return $context['filePath'] === $nonExistentPath
                        && $context['dsrId'] === $accessDsr->id()->toString();
                }),
            );

        // Act - must not throw
        $this->subscriber->onDataExportCompleted($event);

        // Assert: DSR was not modified
        self::assertFalse($accessDsr->status()->equals(RequestStatus::completed()));
    }

    public function testHandlesNoPendingDsr(): void
    {
        // Arrange: no DSRs for this customer
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();

        $event = $this->createDataExportCompletedEvent($customerId, $tenantId, $this->tempFilePath);

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
                'No pending Privacy access/portability DSR found for export',
                self::callback(function (array $context) use ($customerId): bool {
                    return $context['customerId'] === $customerId->toString();
                }),
            );

        // Act
        $this->subscriber->onDataExportCompleted($event);
    }

    public function testHandlesNoPendingDsrWhenOnlyFinalDsrsExist(): void
    {
        // Arrange: only a completed access DSR (isFinal = true)
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();

        $completedAccessDsr = $this->createCompletedAccessDsr($customerId, $tenantId);

        $event = $this->createDataExportCompletedEvent($customerId, $tenantId, $this->tempFilePath);

        $this->dsrRepository
            ->method('findByCustomerId')
            ->willReturn([$completedAccessDsr]);

        $this->dsrRepository
            ->expects(self::never())
            ->method('save');

        $this->logger
            ->expects(self::once())
            ->method('debug');

        // Act
        $this->subscriber->onDataExportCompleted($event);
    }

    public function testMatchesPortabilityDsr(): void
    {
        // Arrange: portability DSR (not just access)
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();
        $exportData = ['portability' => ['format' => 'json', 'data' => ['field' => 'value']]];

        file_put_contents($this->tempFilePath, json_encode($exportData));

        $portabilityDsr = $this->createPortabilityDsrUnderReview($customerId, $tenantId);

        $event = $this->createDataExportCompletedEvent($customerId, $tenantId, $this->tempFilePath);

        $this->dsrRepository
            ->method('findByCustomerId')
            ->willReturn([$portabilityDsr]);

        $this->dsrRepository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(function (DataSubjectRequest $saved): bool {
                return $saved->status()->equals(RequestStatus::completed());
            }));

        $this->logger->method('info');

        // Act
        $this->subscriber->onDataExportCompleted($event);

        // Assert: portability DSR completed with export data
        self::assertTrue($portabilityDsr->status()->equals(RequestStatus::completed()));
        self::assertSame($exportData, $portabilityDsr->exportData());
    }

    public function testIgnoresErasureAndOtherNonMatchingDsrs(): void
    {
        // Arrange: only erasure DSRs (not access/portability)
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();

        $erasureDsr = DataSubjectRequest::submit(
            DataSubjectRequestId::generate(),
            $tenantId,
            $customerId,
            RequestType::erasure(),
        );

        $event = $this->createDataExportCompletedEvent($customerId, $tenantId, $this->tempFilePath);

        $this->dsrRepository
            ->method('findByCustomerId')
            ->willReturn([$erasureDsr]);

        $this->dsrRepository
            ->expects(self::never())
            ->method('save');

        $this->logger
            ->expects(self::once())
            ->method('debug')
            ->with('No pending Privacy access/portability DSR found for export', self::anything());

        // Act
        $this->subscriber->onDataExportCompleted($event);

        // Assert: erasure DSR untouched
        self::assertTrue($erasureDsr->status()->equals(RequestStatus::pending()));
    }

    public function testUsesFirstMatchingDsrWhenMultipleExist(): void
    {
        // Arrange: multiple DSRs - erasure (skipped) followed by access (matched)
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();
        $exportData = ['email' => 'customer@test.com'];

        file_put_contents($this->tempFilePath, json_encode($exportData));

        $erasureDsr = DataSubjectRequest::submit(
            DataSubjectRequestId::generate(),
            $tenantId,
            $customerId,
            RequestType::erasure(),
        );

        $accessDsr = $this->createAccessDsrUnderReview($customerId, $tenantId);

        $event = $this->createDataExportCompletedEvent($customerId, $tenantId, $this->tempFilePath);

        $this->dsrRepository
            ->method('findByCustomerId')
            ->willReturn([$erasureDsr, $accessDsr]);

        $this->dsrRepository
            ->expects(self::once())
            ->method('save');

        $this->logger->method('info');

        // Act
        $this->subscriber->onDataExportCompleted($event);

        // Assert: only access DSR completed; erasure DSR untouched
        self::assertTrue($accessDsr->status()->equals(RequestStatus::completed()));
        self::assertTrue($erasureDsr->status()->equals(RequestStatus::pending()));
    }

    // --- Helpers ---

    private function createAccessDsrUnderReview(CustomerId $customerId, TenantId $tenantId): DataSubjectRequest
    {
        $dsr = DataSubjectRequest::submit(
            DataSubjectRequestId::generate(),
            $tenantId,
            $customerId,
            RequestType::access(),
        );
        $dsr->startReview('Reviewing access request for compliance.');

        return $dsr;
    }

    private function createPortabilityDsrUnderReview(CustomerId $customerId, TenantId $tenantId): DataSubjectRequest
    {
        $dsr = DataSubjectRequest::submit(
            DataSubjectRequestId::generate(),
            $tenantId,
            $customerId,
            RequestType::portability(),
        );
        $dsr->startReview('Reviewing portability request for compliance.');

        return $dsr;
    }

    private function createCompletedAccessDsr(CustomerId $customerId, TenantId $tenantId): DataSubjectRequest
    {
        $dsr = DataSubjectRequest::submit(
            DataSubjectRequestId::generate(),
            $tenantId,
            $customerId,
            RequestType::access(),
        );
        $dsr->startReview('Reviewed for access compliance.');
        $dsr->complete(['profile' => ['name' => 'Test User']]);

        return $dsr;
    }

    private function createDataExportCompletedEvent(
        CustomerId $customerId,
        TenantId $tenantId,
        string $filePath,
    ): DataExportCompleted {
        return new DataExportCompleted(
            requestId: DataExportRequestId::generate(),
            customerId: $customerId,
            tenantId: $tenantId,
            filePath: $filePath,
            expiresAt: new \DateTimeImmutable('+7 days'),
            completedAt: new \DateTimeImmutable(),
        );
    }
}
