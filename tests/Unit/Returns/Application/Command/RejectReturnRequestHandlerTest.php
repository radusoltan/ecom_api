<?php

declare(strict_types=1);

namespace App\Tests\Unit\Returns\Application\Command;

use App\Order\Domain\Model\OrderId;
use App\Returns\Application\Command\RejectReturnRequest;
use App\Returns\Application\Command\RejectReturnRequestHandler;
use App\Returns\Domain\Model\ReturnRequest;
use App\Returns\Domain\Repository\ReturnRequestRepositoryInterface;
use App\Returns\Domain\ValueObject\ReturnReason;
use App\Returns\Domain\ValueObject\ReturnRequestId;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

/**
 * Tests for RejectReturnRequestHandler.
 */
final class RejectReturnRequestHandlerTest extends TestCase
{
    private ReturnRequestRepositoryInterface $repository;
    private RejectReturnRequestHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ReturnRequestRepositoryInterface::class);
        $this->handler = new RejectReturnRequestHandler($this->repository);
    }

    public function testHandleRejectsRequestedReturn(): void
    {
        $returnRequest = $this->createRequestedReturn();

        $this->repository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($returnRequest);

        $this->repository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (ReturnRequest $rr): bool {
                return $rr->status()->isRejected()
                    && $rr->rejectionReason() === 'Return window has expired (30 days)';
            }));

        ($this->handler)(new RejectReturnRequest(
            returnRequestId: $returnRequest->id()->toString(),
            rejectionReason: 'Return window has expired (30 days)',
        ));
    }

    public function testHandleThrowsWhenNotFound(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('findById')
            ->willReturn(null);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('not found');

        ($this->handler)(new RejectReturnRequest(
            returnRequestId: ReturnRequestId::generate()->toString(),
            rejectionReason: 'Expired',
        ));
    }

    private function createRequestedReturn(): ReturnRequest
    {
        return ReturnRequest::create(
            id: ReturnRequestId::generate(),
            tenantId: TenantId::generate(),
            orderId: OrderId::generate(),
            reason: ReturnReason::fromString('Changed my mind about this purchase'),
        );
    }
}
