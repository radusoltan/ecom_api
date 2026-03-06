<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\Query\GetDataExportStatus;

use App\Customer\Application\DTO\DataExportStatusDTO;
use App\Customer\Application\Query\GetDataExportStatus\GetDataExportStatusQuery;
use App\Customer\Application\Query\GetDataExportStatus\GetDataExportStatusQueryHandler;
use App\Customer\Domain\Model\DataExportRequest;
use App\Customer\Domain\Repository\DataExportRequestRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\DataExportRequestId;
use App\Customer\Domain\ValueObject\ExportStatus;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class GetDataExportStatusQueryHandlerTest extends TestCase
{
    private DataExportRequestRepositoryInterface&MockObject $repository;
    private GetDataExportStatusQueryHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(DataExportRequestRepositoryInterface::class);
        $this->handler = new GetDataExportStatusQueryHandler($this->repository);
    }

    public function testReturnsNullWhenRequestNotFound(): void
    {
        $this->repository->method('findById')->willReturn(null);

        $query = new GetDataExportStatusQuery(
            requestId: '00000000-0000-4000-8000-000000000010',
            customerId: '00000000-0000-4000-8000-000000000020',
            tenantId: '00000000-0000-4000-8000-000000000001',
        );

        $result = ($this->handler)($query);

        $this->assertNull($result);
    }

    public function testThrowsWhenCustomerDoesNotOwnRequest(): void
    {
        $requestId = DataExportRequestId::fromString('00000000-0000-4000-8000-000000000010');
        $ownerCustomerId = CustomerId::fromString('00000000-0000-4000-8000-000000000030');

        $request = $this->createMock(DataExportRequest::class);
        $request->method('customerId')->willReturn($ownerCustomerId);

        $this->repository->method('findById')->willReturn($request);

        $query = new GetDataExportStatusQuery(
            requestId: '00000000-0000-4000-8000-000000000010',
            customerId: '00000000-0000-4000-8000-000000000020',
            tenantId: '00000000-0000-4000-8000-000000000001',
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Export request does not belong to this customer');

        ($this->handler)($query);
    }

    public function testReturnsStatusDTOWhenRequestFoundAndOwned(): void
    {
        $requestIdStr = '00000000-0000-4000-8000-000000000010';
        $customerIdStr = '00000000-0000-4000-8000-000000000020';

        $requestId = DataExportRequestId::fromString($requestIdStr);
        $customerId = CustomerId::fromString($customerIdStr);

        $createdAt = new \DateTimeImmutable('2026-01-01');
        $completedAt = new \DateTimeImmutable('2026-01-02');
        $expiresAt = new \DateTimeImmutable('2026-01-10');

        $request = $this->createMock(DataExportRequest::class);
        $request->method('customerId')->willReturn($customerId);
        $request->method('id')->willReturn($requestId);
        $request->method('status')->willReturn(ExportStatus::READY);
        $request->method('expiresAt')->willReturn($expiresAt);
        $request->method('createdAt')->willReturn($createdAt);
        $request->method('completedAt')->willReturn($completedAt);
        $request->method('isDownloadable')->willReturn(true);
        $request->method('errorMessage')->willReturn(null);

        $this->repository->method('findById')->willReturn($request);

        $query = new GetDataExportStatusQuery(
            requestId: $requestIdStr,
            customerId: $customerIdStr,
            tenantId: '00000000-0000-4000-8000-000000000001',
        );

        $result = ($this->handler)($query);

        $this->assertInstanceOf(DataExportStatusDTO::class, $result);
        $this->assertSame($requestIdStr, $result->id);
        $this->assertSame('ready', $result->status);
        $this->assertSame($expiresAt, $result->expiresAt);
        $this->assertSame($createdAt, $result->createdAt);
        $this->assertSame($completedAt, $result->completedAt);
        $this->assertTrue($result->isDownloadable);
        $this->assertNull($result->errorMessage);
    }

    public function testReturnsStatusDTOWithErrorMessage(): void
    {
        $requestIdStr = '00000000-0000-4000-8000-000000000010';
        $customerIdStr = '00000000-0000-4000-8000-000000000020';

        $requestId = DataExportRequestId::fromString($requestIdStr);
        $customerId = CustomerId::fromString($customerIdStr);

        $request = $this->createMock(DataExportRequest::class);
        $request->method('customerId')->willReturn($customerId);
        $request->method('id')->willReturn($requestId);
        $request->method('status')->willReturn(ExportStatus::FAILED);
        $request->method('expiresAt')->willReturn(null);
        $request->method('createdAt')->willReturn(new \DateTimeImmutable('2026-01-01'));
        $request->method('completedAt')->willReturn(null);
        $request->method('isDownloadable')->willReturn(false);
        $request->method('errorMessage')->willReturn('Export generation failed');

        $this->repository->method('findById')->willReturn($request);

        $query = new GetDataExportStatusQuery(
            requestId: $requestIdStr,
            customerId: $customerIdStr,
            tenantId: '00000000-0000-4000-8000-000000000001',
        );

        $result = ($this->handler)($query);

        $this->assertInstanceOf(DataExportStatusDTO::class, $result);
        $this->assertSame('failed', $result->status);
        $this->assertFalse($result->isDownloadable);
        $this->assertSame('Export generation failed', $result->errorMessage);
    }
}
