<?php

declare(strict_types=1);

namespace App\Tests\Unit\Returns\Application\Command;

use App\Order\Domain\Model\OrderId;
use App\Returns\Application\Command\InspectReturnRequest;
use App\Returns\Application\Command\InspectReturnRequestHandler;
use App\Returns\Domain\Model\ReturnRequest;
use App\Returns\Domain\Repository\ReturnRequestRepositoryInterface;
use App\Returns\Domain\ValueObject\ReturnReason;
use App\Returns\Domain\ValueObject\ReturnRequestId;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

/**
 * Tests for InspectReturnRequestHandler.
 */
final class InspectReturnRequestHandlerTest extends TestCase
{
    private ReturnRequestRepositoryInterface $repository;
    private InspectReturnRequestHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ReturnRequestRepositoryInterface::class);
        $this->handler = new InspectReturnRequestHandler($this->repository);
    }

    public function testHandleInspectsReceivedReturn(): void
    {
        $returnRequest = $this->createReceivedReturn();

        $this->repository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($returnRequest);

        $this->repository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (ReturnRequest $rr): bool {
                return $rr->status()->isInspected()
                    && true === $rr->isResellable()
                    && $rr->inspectionNotes() === 'Item in good condition, original packaging intact';
            }));

        ($this->handler)(new InspectReturnRequest(
            returnRequestId: $returnRequest->id()->toString(),
            isResellable: true,
            inspectionNotes: 'Item in good condition, original packaging intact',
        ));
    }

    public function testHandleInspectsWithNonResellable(): void
    {
        $returnRequest = $this->createReceivedReturn();

        $this->repository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($returnRequest);

        $this->repository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (ReturnRequest $rr): bool {
                return $rr->status()->isInspected()
                    && false === $rr->isResellable();
            }));

        ($this->handler)(new InspectReturnRequest(
            returnRequestId: $returnRequest->id()->toString(),
            isResellable: false,
            inspectionNotes: 'Item severely damaged, not resellable',
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

        ($this->handler)(new InspectReturnRequest(
            returnRequestId: ReturnRequestId::generate()->toString(),
            isResellable: true,
            inspectionNotes: 'Should not reach here',
        ));
    }

    private function createReceivedReturn(): ReturnRequest
    {
        $returnRequest = ReturnRequest::create(
            id: ReturnRequestId::generate(),
            tenantId: TenantId::generate(),
            orderId: OrderId::generate(),
            reason: ReturnReason::fromString('Wrong size was delivered to customer'),
        );

        $returnRequest->approve();
        $returnRequest->markAsReceived('WH-001');

        return $returnRequest;
    }
}
