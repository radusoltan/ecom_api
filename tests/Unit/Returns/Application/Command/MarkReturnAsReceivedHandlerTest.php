<?php

declare(strict_types=1);

namespace App\Tests\Unit\Returns\Application\Command;

use App\Order\Domain\Model\OrderId;
use App\Returns\Application\Command\MarkReturnAsReceived;
use App\Returns\Application\Command\MarkReturnAsReceivedHandler;
use App\Returns\Domain\Model\ReturnRequest;
use App\Returns\Domain\Repository\ReturnRequestRepositoryInterface;
use App\Returns\Domain\ValueObject\ReturnReason;
use App\Returns\Domain\ValueObject\ReturnRequestId;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

/**
 * Tests for MarkReturnAsReceivedHandler.
 */
final class MarkReturnAsReceivedHandlerTest extends TestCase
{
    private ReturnRequestRepositoryInterface $repository;
    private MarkReturnAsReceivedHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ReturnRequestRepositoryInterface::class);
        $this->handler = new MarkReturnAsReceivedHandler($this->repository);
    }

    public function testHandleMarksApprovedReturnAsReceived(): void
    {
        $returnRequest = $this->createApprovedReturn();

        $this->repository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($returnRequest);

        $this->repository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (ReturnRequest $rr): bool {
                return $rr->status()->isReceived()
                    && 'WH-CENTRAL-01' === $rr->warehouseId();
            }));

        ($this->handler)(new MarkReturnAsReceived(
            returnRequestId: $returnRequest->id()->toString(),
            warehouseId: 'WH-CENTRAL-01',
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

        ($this->handler)(new MarkReturnAsReceived(
            returnRequestId: ReturnRequestId::generate()->toString(),
            warehouseId: 'WH-001',
        ));
    }

    private function createApprovedReturn(): ReturnRequest
    {
        $returnRequest = ReturnRequest::create(
            id: ReturnRequestId::generate(),
            tenantId: TenantId::generate(),
            orderId: OrderId::generate(),
            reason: ReturnReason::fromString('Wrong color delivered, expected blue got red'),
        );

        $returnRequest->approve();

        return $returnRequest;
    }
}
