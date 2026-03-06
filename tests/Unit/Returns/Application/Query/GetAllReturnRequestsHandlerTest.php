<?php

declare(strict_types=1);

namespace App\Tests\Unit\Returns\Application\Query;

use App\Order\Domain\Model\OrderId;
use App\Returns\Application\DTO\ReturnRequestDTO;
use App\Returns\Application\Query\GetAllReturnRequests;
use App\Returns\Application\Query\GetAllReturnRequestsHandler;
use App\Returns\Domain\Model\ReturnRequest;
use App\Returns\Domain\Repository\ReturnRequestRepositoryInterface;
use App\Returns\Domain\ValueObject\ReturnReason;
use App\Returns\Domain\ValueObject\ReturnRequestId;
use App\Returns\Domain\ValueObject\ReturnStatus;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetAllReturnRequestsHandler::class)]
final class GetAllReturnRequestsHandlerTest extends TestCase
{
    private ReturnRequestRepositoryInterface&MockObject $repository;
    private GetAllReturnRequestsHandler $handler;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ReturnRequestRepositoryInterface::class);
        $this->handler = new GetAllReturnRequestsHandler($this->repository);
        $this->tenantId = TenantId::generate();
    }

    // -----------------------------------------------------------------------
    // Without status filter
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsAllReturnRequestsForTenantWhenNoStatusFilter(): void
    {
        $returnRequests = [
            $this->makeReturnRequest(),
            $this->makeReturnRequest(),
        ];

        $this->repository
            ->expects(self::once())
            ->method('findByTenantId')
            ->with(self::callback(
                fn (TenantId $id) => $id->toString() === $this->tenantId->toString()
            ))
            ->willReturn($returnRequests);

        $this->repository
            ->expects(self::never())
            ->method('findByStatus');

        $query = new GetAllReturnRequests(tenantId: $this->tenantId->toString());

        $result = ($this->handler)($query);

        self::assertCount(2, $result);
        self::assertContainsOnlyInstancesOf(ReturnRequestDTO::class, $result);
    }

    #[Test]
    public function itReturnsEmptyArrayWhenNoReturnRequestsForTenant(): void
    {
        $this->repository
            ->method('findByTenantId')
            ->willReturn([]);

        $result = ($this->handler)(new GetAllReturnRequests(tenantId: $this->tenantId->toString()));

        self::assertSame([], $result);
    }

    #[Test]
    public function itCallsFindByTenantIdWhenStatusIsNull(): void
    {
        $this->repository
            ->expects(self::once())
            ->method('findByTenantId')
            ->willReturn([]);

        $this->repository
            ->expects(self::never())
            ->method('findByStatus');

        ($this->handler)(new GetAllReturnRequests(
            tenantId: $this->tenantId->toString(),
            status: null,
        ));
    }

    // -----------------------------------------------------------------------
    // With status filter
    // -----------------------------------------------------------------------

    #[Test]
    public function itFiltersReturnRequestsByStatusWhenProvided(): void
    {
        $requestedReturn = $this->makeReturnRequest();

        $this->repository
            ->expects(self::once())
            ->method('findByStatus')
            ->with(
                ReturnStatus::REQUESTED,
                self::callback(fn (TenantId $id) => $id->toString() === $this->tenantId->toString())
            )
            ->willReturn([$requestedReturn]);

        $this->repository
            ->expects(self::never())
            ->method('findByTenantId');

        $query = new GetAllReturnRequests(
            tenantId: $this->tenantId->toString(),
            status: ReturnStatus::REQUESTED,
        );

        $result = ($this->handler)($query);

        self::assertCount(1, $result);
        self::assertInstanceOf(ReturnRequestDTO::class, $result[0]);
        self::assertSame(ReturnStatus::REQUESTED, $result[0]->status);
    }

    #[Test]
    public function itFiltersReturnRequestsByApprovedStatus(): void
    {
        $approvedReturn = $this->makeReturnRequestWithStatus(ReturnStatus::approved());

        $this->repository
            ->method('findByStatus')
            ->with(ReturnStatus::APPROVED, self::anything())
            ->willReturn([$approvedReturn]);

        $result = ($this->handler)(new GetAllReturnRequests(
            tenantId: $this->tenantId->toString(),
            status: ReturnStatus::APPROVED,
        ));

        self::assertCount(1, $result);
        self::assertSame(ReturnStatus::APPROVED, $result[0]->status);
    }

    #[Test]
    public function itReturnsEmptyArrayWhenNoReturnRequestsMatchStatus(): void
    {
        $this->repository
            ->method('findByStatus')
            ->willReturn([]);

        $result = ($this->handler)(new GetAllReturnRequests(
            tenantId: $this->tenantId->toString(),
            status: ReturnStatus::COMPLETED,
        ));

        self::assertSame([], $result);
    }

    // -----------------------------------------------------------------------
    // DTO mapping
    // -----------------------------------------------------------------------

    #[Test]
    public function itMapsReturnRequestsToDtos(): void
    {
        $returnRequestId = ReturnRequestId::generate();
        $orderId = OrderId::generate();
        $reason = ReturnReason::fromString('Item received with missing parts and accessories');

        $returnRequest = ReturnRequest::create(
            id: $returnRequestId,
            tenantId: $this->tenantId,
            orderId: $orderId,
            reason: $reason,
        );

        $this->repository
            ->method('findByTenantId')
            ->willReturn([$returnRequest]);

        $result = ($this->handler)(new GetAllReturnRequests(tenantId: $this->tenantId->toString()));

        self::assertCount(1, $result);
        self::assertSame($returnRequestId->toString(), $result[0]->id);
        self::assertSame($this->tenantId->toString(), $result[0]->tenantId);
        self::assertSame($orderId->toString(), $result[0]->orderId);
        self::assertSame('Item received with missing parts and accessories', $result[0]->reason);
    }

    #[Test]
    public function itMapsMultipleReturnRequestsToDtos(): void
    {
        $returnRequests = array_map(fn () => $this->makeReturnRequest(), range(0, 4));

        $this->repository
            ->method('findByTenantId')
            ->willReturn($returnRequests);

        $result = ($this->handler)(new GetAllReturnRequests(tenantId: $this->tenantId->toString()));

        self::assertCount(5, $result);
        foreach ($result as $dto) {
            self::assertInstanceOf(ReturnRequestDTO::class, $dto);
        }
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeReturnRequest(): ReturnRequest
    {
        return ReturnRequest::create(
            id: ReturnRequestId::generate(),
            tenantId: $this->tenantId,
            orderId: OrderId::generate(),
            reason: ReturnReason::fromString('Product does not match the description provided'),
        );
    }

    private function makeReturnRequestWithStatus(ReturnStatus $status): ReturnRequest
    {
        return ReturnRequest::reconstituteFromPersistence(
            id: ReturnRequestId::generate(),
            tenantId: $this->tenantId,
            orderId: OrderId::generate(),
            reason: ReturnReason::fromString('Product does not match the description provided'),
            status: $status,
            warehouseId: null,
            refundAmount: null,
            isResellable: null,
            inspectionNotes: null,
            rejectionReason: null,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        );
    }
}
