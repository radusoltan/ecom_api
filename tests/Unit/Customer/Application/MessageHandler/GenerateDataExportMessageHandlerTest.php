<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\MessageHandler;

use App\Customer\Application\Message\GenerateDataExportMessage;
use App\Customer\Application\MessageHandler\GenerateDataExportMessageHandler;
use App\Customer\Application\Service\DataExportService;
use App\Customer\Domain\Event\DataExportCompleted;
use App\Customer\Domain\Model\DataExportRequest;
use App\Customer\Domain\Repository\DataExportRequestRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\DataExportRequestId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Group('customer')]
final class GenerateDataExportMessageHandlerTest extends TestCase
{
    private DataExportRequestRepositoryInterface&MockObject $exportRequestRepository;
    private DataExportService&MockObject $dataExportService;
    private EventDispatcherInterface&MockObject $eventDispatcher;
    private LoggerInterface&MockObject $logger;
    private GenerateDataExportMessageHandler $handler;

    private string $requestId;
    private string $customerId;
    private string $tenantId;

    protected function setUp(): void
    {
        $this->exportRequestRepository = $this->createMock(DataExportRequestRepositoryInterface::class);
        $this->dataExportService = $this->createMock(DataExportService::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new GenerateDataExportMessageHandler(
            $this->exportRequestRepository,
            $this->dataExportService,
            $this->eventDispatcher,
            $this->logger,
        );

        $this->requestId = DataExportRequestId::generate()->toString();
        $this->customerId = CustomerId::generate()->toString();
        $this->tenantId = '00000000-0000-4000-8000-000000000001';
    }

    public function testReturnsEarlyWhenRequestNotFound(): void
    {
        $message = new GenerateDataExportMessage($this->requestId, $this->customerId, $this->tenantId);

        $this->exportRequestRepository->method('findById')->willReturn(null);
        $this->logger->expects(self::once())->method('error');

        ($this->handler)($message);
    }

    public function testProcessesExportSuccessfully(): void
    {
        $message = new GenerateDataExportMessage($this->requestId, $this->customerId, $this->tenantId);

        $request = $this->createMock(DataExportRequest::class);
        $this->exportRequestRepository->method('findById')->willReturn($request);

        $this->dataExportService->method('collectCustomerData')->willReturn(['name' => 'John']);
        $this->dataExportService->method('generateJsonExport')->willReturn('{"name":"John"}');
        $this->dataExportService->method('getExportFilePath')->willReturn('/tmp/export.json');
        $this->dataExportService->method('generateDownloadToken')->willReturn('token123');

        $this->exportRequestRepository->expects(self::atLeast(2))->method('save');
        $this->eventDispatcher->expects(self::once())->method('dispatch')
            ->with(self::isInstanceOf(DataExportCompleted::class));

        ($this->handler)($message);
    }

    public function testMarksAsFailedOnException(): void
    {
        $message = new GenerateDataExportMessage($this->requestId, $this->customerId, $this->tenantId);

        $request = $this->createMock(DataExportRequest::class);
        $this->exportRequestRepository->method('findById')->willReturn($request);

        $this->dataExportService->method('collectCustomerData')
            ->willThrowException(new \RuntimeException('DB error'));

        $request->expects(self::once())->method('markFailed')->with('DB error');
        $this->logger->expects(self::once())->method('error');

        ($this->handler)($message);
    }

    public function testLogsCriticalWhenMarkFailedAlsoFails(): void
    {
        $message = new GenerateDataExportMessage($this->requestId, $this->customerId, $this->tenantId);

        $request = $this->createMock(DataExportRequest::class);
        $this->exportRequestRepository->method('findById')->willReturn($request);

        $this->dataExportService->method('collectCustomerData')
            ->willThrowException(new \RuntimeException('DB error'));

        $request->method('markFailed')
            ->willThrowException(new \RuntimeException('Save error'));

        $this->logger->expects(self::once())->method('critical');

        ($this->handler)($message);
    }
}
