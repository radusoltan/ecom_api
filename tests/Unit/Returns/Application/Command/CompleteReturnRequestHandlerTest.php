<?php

declare(strict_types=1);

namespace App\Tests\Unit\Returns\Application\Command;

use App\Order\Domain\Model\OrderId;
use App\Returns\Application\Command\CompleteReturnRequest;
use App\Returns\Application\Command\CompleteReturnRequestHandler;
use App\Returns\Domain\Model\ReturnRequest;
use App\Returns\Domain\Repository\ReturnRequestRepositoryInterface;
use App\Returns\Domain\ValueObject\ReturnReason;
use App\Returns\Domain\ValueObject\ReturnRequestId;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CompleteReturnRequestHandler.
 */
final class CompleteReturnRequestHandlerTest extends TestCase
{
    private ReturnRequestRepositoryInterface $repository;
    private CompleteReturnRequestHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ReturnRequestRepositoryInterface::class);
        $this->handler = new CompleteReturnRequestHandler($this->repository);
    }

    public function testHandleCompletesInspectedReturn(): void
    {
        $returnRequest = $this->createInspectedReturn();

        $this->repository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($returnRequest);

        $this->repository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (ReturnRequest $rr): bool {
                return $rr->status()->isCompleted()
                    && null !== $rr->refundAmount();
            }));

        ($this->handler)(new CompleteReturnRequest(
            returnRequestId: $returnRequest->id()->toString(),
            refundAmount: 2500,
            refundCurrency: 'EUR',
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

        ($this->handler)(new CompleteReturnRequest(
            returnRequestId: ReturnRequestId::generate()->toString(),
            refundAmount: 2500,
            refundCurrency: 'EUR',
        ));
    }

    private function createInspectedReturn(): ReturnRequest
    {
        $returnRequest = ReturnRequest::create(
            id: ReturnRequestId::generate(),
            tenantId: TenantId::generate(),
            orderId: OrderId::generate(),
            reason: ReturnReason::fromString('Product defective - screen has dead pixels'),
        );

        $returnRequest->approve();
        $returnRequest->markAsReceived('WH-001');
        $returnRequest->inspect(false, 'Confirmed defective screen with dead pixels');

        return $returnRequest;
    }
}
