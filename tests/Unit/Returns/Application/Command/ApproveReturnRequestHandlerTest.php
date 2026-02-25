<?php

declare(strict_types=1);

namespace App\Tests\Unit\Returns\Application\Command;

use App\Order\Domain\Model\OrderId;
use App\Returns\Application\Command\ApproveReturnRequest;
use App\Returns\Application\Command\ApproveReturnRequestHandler;
use App\Returns\Domain\Model\ReturnRequest;
use App\Returns\Domain\Repository\ReturnRequestRepositoryInterface;
use App\Returns\Domain\ValueObject\ReturnReason;
use App\Returns\Domain\ValueObject\ReturnRequestId;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

/**
 * Tests for ApproveReturnRequestHandler.
 */
final class ApproveReturnRequestHandlerTest extends TestCase
{
    private ReturnRequestRepositoryInterface $repository;
    private ApproveReturnRequestHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ReturnRequestRepositoryInterface::class);
        $this->handler = new ApproveReturnRequestHandler($this->repository);
    }

    public function testHandleApprovesExistingReturnRequest(): void
    {
        $returnRequest = $this->createRequestedReturn();
        $id = $returnRequest->id()->toString();

        $this->repository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($returnRequest);

        $this->repository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (ReturnRequest $rr): bool {
                return $rr->status()->isApproved();
            }));

        ($this->handler)(new ApproveReturnRequest($id));
    }

    public function testHandleThrowsWhenNotFound(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('findById')
            ->willReturn(null);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('not found');

        ($this->handler)(new ApproveReturnRequest(ReturnRequestId::generate()->toString()));
    }

    private function createRequestedReturn(): ReturnRequest
    {
        return ReturnRequest::create(
            id: ReturnRequestId::generate(),
            tenantId: TenantId::generate(),
            orderId: OrderId::generate(),
            reason: ReturnReason::fromString('Product arrived damaged and cannot be used'),
        );
    }
}
