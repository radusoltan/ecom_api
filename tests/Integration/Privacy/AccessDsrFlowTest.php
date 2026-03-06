<?php

declare(strict_types=1);

namespace App\Tests\Integration\Privacy;

use App\Customer\Application\EventSubscriber\PrivacyDataAccessSubscriber;
use App\Customer\Domain\Event\DataExportCompleted;
use App\Customer\Domain\Model\DataExportRequest;
use App\Customer\Domain\Repository\DataExportRequestRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\DataExportRequestId;
use App\Privacy\Application\EventSubscriber\CustomerDataExportCompletedSubscriber;
use App\Privacy\Domain\Event\DataSubjectRequestSubmitted;
use App\Privacy\Domain\Model\DataSubjectRequest;
use App\Privacy\Domain\Repository\DataSubjectRequestRepositoryInterface;
use App\Privacy\Domain\ValueObject\DataSubjectRequestId;
use App\Privacy\Domain\ValueObject\RequestStatus;
use App\Privacy\Domain\ValueObject\RequestType;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Integration tests for the Privacy→Customer access/portability DSR flow.
 *
 * Tests the full cross-context event chain:
 * DataSubjectRequest::submit(access/portability) → DataSubjectRequestSubmitted event
 *   → PrivacyDataAccessSubscriber → DataExportRequest created + message dispatched
 *   → (export generated) → DataExportCompleted event
 *   → CustomerDataExportCompletedSubscriber → DSR marked completed with export data
 */
final class AccessDsrFlowTest extends TestCase
{
    private const DEFAULT_TENANT_ID = '00000000-0000-4000-8000-000000000001';
    private const DEFAULT_CUSTOMER_ID = 'a0000000-0000-4000-8000-000000000001';

    private DataExportRequestRepositoryInterface $exportRequestRepository;
    private DataSubjectRequestRepositoryInterface $dsrRepository;
    private MessageBusInterface $messageBus;
    private LoggerInterface $accessLogger;
    private LoggerInterface $completionLogger;

    private PrivacyDataAccessSubscriber $accessSubscriber;
    private CustomerDataExportCompletedSubscriber $completionSubscriber;

    private TenantId $tenantId;
    private CustomerId $customerId;

    /** @var list<string> Temp files created during tests; cleaned up in tearDown */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        $this->exportRequestRepository = $this->createMock(DataExportRequestRepositoryInterface::class);
        $this->dsrRepository = $this->createMock(DataSubjectRequestRepositoryInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->accessLogger = $this->createMock(LoggerInterface::class);
        $this->completionLogger = $this->createMock(LoggerInterface::class);

        $this->accessSubscriber = new PrivacyDataAccessSubscriber(
            exportRequestRepository: $this->exportRequestRepository,
            messageBus: $this->messageBus,
            logger: $this->accessLogger,
        );

        $this->completionSubscriber = new CustomerDataExportCompletedSubscriber(
            dsrRepository: $this->dsrRepository,
            logger: $this->completionLogger,
        );

        $this->tenantId = TenantId::fromString(self::DEFAULT_TENANT_ID);
        $this->customerId = CustomerId::fromString(self::DEFAULT_CUSTOMER_ID);
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
        $this->tempFiles = [];
    }

    public function testAccessFlowFromDsrToCompletion(): void
    {
        // --- Phase 1: Submit access DSR → DataSubjectRequestSubmitted ---

        $dsrId = DataSubjectRequestId::generate();

        $dsr = DataSubjectRequest::submit(
            id: $dsrId,
            tenantId: $this->tenantId,
            customerId: $this->customerId,
            requestType: RequestType::access(),
        );

        // Access DSR emits only DataSubjectRequestSubmitted (no DataErasureRequested)
        $events = $dsr->popEvents();
        $this->assertCount(1, $events, 'Access DSR must emit exactly one event');

        $submittedEvent = $events[0];
        $this->assertInstanceOf(DataSubjectRequestSubmitted::class, $submittedEvent);
        $this->assertSame('access', $submittedEvent->requestType->value());
        $this->assertSame(self::DEFAULT_CUSTOMER_ID, $submittedEvent->customerId->toString());
        $this->assertSame(self::DEFAULT_TENANT_ID, $submittedEvent->tenantId->toString());

        // --- Phase 2: PrivacyDataAccessSubscriber creates DataExportRequest ---

        // No existing pending export
        $this->exportRequestRepository
            ->expects($this->once())
            ->method('findPendingByCustomerId')
            ->with(
                $this->callback(fn (CustomerId $id) => self::DEFAULT_CUSTOMER_ID === $id->toString()),
                $this->callback(fn (TenantId $id) => self::DEFAULT_TENANT_ID === $id->toString()),
            )
            ->willReturn(null);

        // Capture the saved DataExportRequest
        $savedExportRequest = null;
        $this->exportRequestRepository
            ->expects($this->once())
            ->method('save')
            ->willReturnCallback(function (DataExportRequest $request) use (&$savedExportRequest): void {
                $savedExportRequest = $request;
            });

        // Async message must be dispatched
        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($message) {
                return $message instanceof \App\Customer\Application\Message\GenerateDataExportMessage;
            }))
            ->willReturn(new Envelope(new \stdClass()));

        $this->accessSubscriber->onDataSubjectRequestSubmitted($submittedEvent);

        $this->assertNotNull($savedExportRequest);
        $this->assertSame(self::DEFAULT_CUSTOMER_ID, $savedExportRequest->customerId()->toString());
        $this->assertSame(self::DEFAULT_TENANT_ID, $savedExportRequest->tenantId()->toString());

        // --- Phase 3: Simulate export file generation ---

        $exportData = [
            'customer' => [
                'id' => self::DEFAULT_CUSTOMER_ID,
                'email' => 'user@example.com',
                'firstName' => 'John',
                'lastName' => 'Doe',
            ],
            'orders' => [],
        ];

        $exportFilePath = $this->createTempExportFile($exportData);

        $exportRequestId = DataExportRequestId::generate();
        $dataExportCompleted = new DataExportCompleted(
            requestId: $exportRequestId,
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            filePath: $exportFilePath,
            expiresAt: new \DateTimeImmutable('+24 hours'),
            completedAt: new \DateTimeImmutable(),
        );

        // --- Phase 4: CustomerDataExportCompletedSubscriber completes the Privacy DSR ---

        $this->dsrRepository
            ->expects($this->once())
            ->method('findByCustomerId')
            ->with($this->callback(fn (CustomerId $id) => self::DEFAULT_CUSTOMER_ID === $id->toString()))
            ->willReturn([$dsr]);

        $completedDsr = null;
        $this->dsrRepository
            ->expects($this->once())
            ->method('save')
            ->willReturnCallback(function (DataSubjectRequest $request) use (&$completedDsr): void {
                $completedDsr = $request;
            });

        $this->completionSubscriber->onDataExportCompleted($dataExportCompleted);

        // Assert DSR is completed with export data
        $this->assertNotNull($completedDsr);
        $this->assertSame('completed', $completedDsr->status()->value());
        $this->assertNotNull($completedDsr->completedAt());
        $this->assertNotNull($completedDsr->exportData());
        $this->assertSame('user@example.com', $completedDsr->exportData()['customer']['email']);
    }

    public function testPortabilityFlowFromDsrToCompletion(): void
    {
        // --- Phase 1: Submit portability DSR ---

        $dsr = DataSubjectRequest::submit(
            id: DataSubjectRequestId::generate(),
            tenantId: $this->tenantId,
            customerId: $this->customerId,
            requestType: RequestType::portability(),
        );

        $events = $dsr->popEvents();
        $this->assertCount(1, $events, 'Portability DSR must emit exactly one event');

        $submittedEvent = $events[0];
        $this->assertInstanceOf(DataSubjectRequestSubmitted::class, $submittedEvent);
        $this->assertSame('portability', $submittedEvent->requestType->value());

        // --- Phase 2: PrivacyDataAccessSubscriber handles portability type ---

        $this->exportRequestRepository
            ->expects($this->once())
            ->method('findPendingByCustomerId')
            ->willReturn(null);

        $this->exportRequestRepository->expects($this->once())->method('save');

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $this->accessSubscriber->onDataSubjectRequestSubmitted($submittedEvent);

        // --- Phase 3: Export completes ---

        $exportData = ['customer' => ['id' => self::DEFAULT_CUSTOMER_ID], 'format' => 'json'];
        $exportFilePath = $this->createTempExportFile($exportData);

        $dataExportCompleted = new DataExportCompleted(
            requestId: DataExportRequestId::generate(),
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            filePath: $exportFilePath,
            expiresAt: new \DateTimeImmutable('+24 hours'),
            completedAt: new \DateTimeImmutable(),
        );

        // --- Phase 4: DSR completed ---

        $this->dsrRepository->method('findByCustomerId')->willReturn([$dsr]);

        $completedDsr = null;
        $this->dsrRepository
            ->expects($this->once())
            ->method('save')
            ->willReturnCallback(function (DataSubjectRequest $request) use (&$completedDsr): void {
                $completedDsr = $request;
            });

        $this->completionSubscriber->onDataExportCompleted($dataExportCompleted);

        $this->assertNotNull($completedDsr);
        $this->assertSame('completed', $completedDsr->status()->value());
        $this->assertNotNull($completedDsr->exportData());
        $this->assertSame('json', $completedDsr->exportData()['format']);
    }

    public function testAccessSubscriberIgnoresErasureType(): void
    {
        // Arrange: erasure DSR submitted event
        $dsr = DataSubjectRequest::submit(
            id: DataSubjectRequestId::generate(),
            tenantId: $this->tenantId,
            customerId: $this->customerId,
            requestType: RequestType::erasure(),
        );

        $events = $dsr->popEvents();
        $submittedEvent = null;
        foreach ($events as $event) {
            if ($event instanceof DataSubjectRequestSubmitted) {
                $submittedEvent = $event;
                break;
            }
        }
        $this->assertNotNull($submittedEvent);

        // Subscriber must not create an export or dispatch anything for erasure type
        $this->exportRequestRepository->expects($this->never())->method('findPendingByCustomerId');
        $this->exportRequestRepository->expects($this->never())->method('save');
        $this->messageBus->expects($this->never())->method('dispatch');

        // Act
        $this->accessSubscriber->onDataSubjectRequestSubmitted($submittedEvent);
    }

    public function testAccessSubscriberIgnoresRectificationType(): void
    {
        // Arrange
        $dsr = DataSubjectRequest::submit(
            id: DataSubjectRequestId::generate(),
            tenantId: $this->tenantId,
            customerId: $this->customerId,
            requestType: RequestType::rectification(),
        );
        $submittedEvent = $dsr->popEvents()[0];

        $this->exportRequestRepository->expects($this->never())->method('findPendingByCustomerId');
        $this->exportRequestRepository->expects($this->never())->method('save');
        $this->messageBus->expects($this->never())->method('dispatch');

        // Act
        $this->accessSubscriber->onDataSubjectRequestSubmitted($submittedEvent);
    }

    public function testAccessFlowIsIdempotentWhenExportAlreadyPending(): void
    {
        // Arrange
        $dsr = DataSubjectRequest::submit(
            id: DataSubjectRequestId::generate(),
            tenantId: $this->tenantId,
            customerId: $this->customerId,
            requestType: RequestType::access(),
        );
        $submittedEvent = $dsr->popEvents()[0];

        // Simulate existing pending export request
        $existingExport = DataExportRequest::create(
            customerId: $this->customerId,
            tenantId: $this->tenantId,
        );

        $this->exportRequestRepository
            ->expects($this->once())
            ->method('findPendingByCustomerId')
            ->willReturn($existingExport);

        // Because a pending request already exists, save and dispatch must NOT be called
        $this->exportRequestRepository->expects($this->never())->method('save');
        $this->messageBus->expects($this->never())->method('dispatch');

        $this->accessLogger->expects($this->once())->method('info');

        // Act
        $this->accessSubscriber->onDataSubjectRequestSubmitted($submittedEvent);
    }

    public function testCompletionSubscriberDoesNothingWhenNoDsrFound(): void
    {
        // Arrange: export completed but no matching Privacy DSR
        $exportFilePath = $this->createTempExportFile(['data' => 'test']);

        $dataExportCompleted = new DataExportCompleted(
            requestId: DataExportRequestId::generate(),
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            filePath: $exportFilePath,
            expiresAt: new \DateTimeImmutable('+24 hours'),
            completedAt: new \DateTimeImmutable(),
        );

        $this->dsrRepository
            ->expects($this->once())
            ->method('findByCustomerId')
            ->willReturn([]);

        $this->dsrRepository->expects($this->never())->method('save');
        $this->completionLogger->expects($this->once())->method('debug');

        // Act
        $this->completionSubscriber->onDataExportCompleted($dataExportCompleted);
    }

    public function testCompletionSubscriberDoesNothingWhenExportFileIsMissing(): void
    {
        // Arrange: DSR exists but export file has been deleted
        $dsr = DataSubjectRequest::submit(
            id: DataSubjectRequestId::generate(),
            tenantId: $this->tenantId,
            customerId: $this->customerId,
            requestType: RequestType::access(),
        );
        $dsr->popEvents();

        $nonExistentFilePath = sys_get_temp_dir().'/non_existent_export_'.uniqid().'.json';

        $dataExportCompleted = new DataExportCompleted(
            requestId: DataExportRequestId::generate(),
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            filePath: $nonExistentFilePath,
            expiresAt: new \DateTimeImmutable('+24 hours'),
            completedAt: new \DateTimeImmutable(),
        );

        $this->dsrRepository->method('findByCustomerId')->willReturn([$dsr]);

        // File missing → cannot read export data → save must NOT be called
        $this->dsrRepository->expects($this->never())->method('save');
        $this->completionLogger->expects($this->once())->method('error');

        // Act
        $this->completionSubscriber->onDataExportCompleted($dataExportCompleted);
    }

    public function testCompletionSubscriberSkipsAlreadyCompletedDsr(): void
    {
        // Arrange: access DSR already in completed state
        $completedDsr = DataSubjectRequest::reconstituteFromPersistence(
            id: DataSubjectRequestId::generate(),
            tenantId: $this->tenantId,
            customerId: $this->customerId,
            requestType: RequestType::access(),
            status: RequestStatus::completed(),
            reason: null,
            reviewNotes: 'Auto-completed',
            rejectionReason: null,
            exportData: ['customer' => ['id' => 'prev']],
            submittedAt: new \DateTimeImmutable('-2 days'),
            completedAt: new \DateTimeImmutable('-1 day'),
            deadline: new \DateTimeImmutable('+28 days'),
            isExtended: false,
            createdAt: new \DateTimeImmutable('-2 days'),
            updatedAt: new \DateTimeImmutable('-1 day'),
        );

        $exportFilePath = $this->createTempExportFile(['new' => 'data']);

        $dataExportCompleted = new DataExportCompleted(
            requestId: DataExportRequestId::generate(),
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            filePath: $exportFilePath,
            expiresAt: new \DateTimeImmutable('+24 hours'),
            completedAt: new \DateTimeImmutable(),
        );

        $this->dsrRepository->method('findByCustomerId')->willReturn([$completedDsr]);

        // Already final → save must NOT be called
        $this->dsrRepository->expects($this->never())->method('save');

        // Act
        $this->completionSubscriber->onDataExportCompleted($dataExportCompleted);
    }

    public function testCompletionSubscriberTransitionsPendingDsrThroughReviewToCompleted(): void
    {
        // Arrange: pending access DSR
        $dsr = DataSubjectRequest::submit(
            id: DataSubjectRequestId::generate(),
            tenantId: $this->tenantId,
            customerId: $this->customerId,
            requestType: RequestType::access(),
        );
        $dsr->popEvents();

        $this->assertSame('pending', $dsr->status()->value());

        $exportData = ['customer' => ['email' => 'export@test.com']];
        $exportFilePath = $this->createTempExportFile($exportData);

        $dataExportCompleted = new DataExportCompleted(
            requestId: DataExportRequestId::generate(),
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            filePath: $exportFilePath,
            expiresAt: new \DateTimeImmutable('+24 hours'),
            completedAt: new \DateTimeImmutable(),
        );

        $this->dsrRepository->method('findByCustomerId')->willReturn([$dsr]);
        $this->dsrRepository->expects($this->once())->method('save');

        // Act
        $this->completionSubscriber->onDataExportCompleted($dataExportCompleted);

        // Assert: DSR auto-transitioned pending → under_review → completed
        $this->assertSame('completed', $dsr->status()->value());
        $this->assertNotNull($dsr->exportData());
        $this->assertSame('export@test.com', $dsr->exportData()['customer']['email']);
    }

    public function testGenerateDataExportMessageCarriesCorrectIds(): void
    {
        // Arrange
        $dsr = DataSubjectRequest::submit(
            id: DataSubjectRequestId::generate(),
            tenantId: $this->tenantId,
            customerId: $this->customerId,
            requestType: RequestType::access(),
        );
        $submittedEvent = $dsr->popEvents()[0];

        $this->exportRequestRepository->method('findPendingByCustomerId')->willReturn(null);

        $savedExportRequest = null;
        $this->exportRequestRepository
            ->method('save')
            ->willReturnCallback(function (DataExportRequest $r) use (&$savedExportRequest): void {
                $savedExportRequest = $r;
            });

        // Capture the dispatched message
        $dispatchedMessage = null;
        $this->messageBus
            ->method('dispatch')
            ->willReturnCallback(function ($message) use (&$dispatchedMessage) {
                $dispatchedMessage = $message;

                return new Envelope(new \stdClass());
            });

        // Act
        $this->accessSubscriber->onDataSubjectRequestSubmitted($submittedEvent);

        // Assert
        $this->assertNotNull($savedExportRequest);
        $this->assertNotNull($dispatchedMessage);
        $this->assertInstanceOf(\App\Customer\Application\Message\GenerateDataExportMessage::class, $dispatchedMessage);
        $this->assertSame($savedExportRequest->id()->toString(), $dispatchedMessage->getRequestId());
        $this->assertSame(self::DEFAULT_CUSTOMER_ID, $dispatchedMessage->getCustomerId());
        $this->assertSame(self::DEFAULT_TENANT_ID, $dispatchedMessage->getTenantId());
    }

    /**
     * Helper: write JSON export data to a temp file and register it for cleanup.
     *
     * @param array<string, mixed> $data
     */
    private function createTempExportFile(array $data): string
    {
        $path = sys_get_temp_dir().'/test_export_'.uniqid('', true).'.json';
        file_put_contents($path, json_encode($data, JSON_THROW_ON_ERROR));
        $this->tempFiles[] = $path;

        return $path;
    }
}
