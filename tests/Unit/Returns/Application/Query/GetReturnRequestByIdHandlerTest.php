<?php

declare(strict_types=1);

namespace App\Tests\Unit\Returns\Application\Query;

use App\Order\Domain\Model\OrderId;
use App\Returns\Application\DTO\ReturnRequestDTO;
use App\Returns\Application\Query\GetReturnRequestById;
use App\Returns\Application\Query\GetReturnRequestByIdHandler;
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

#[CoversClass(GetReturnRequestByIdHandler::class)]
final class GetReturnRequestByIdHandlerTest extends TestCase
{
    private ReturnRequestRepositoryInterface&MockObject $repository;
    private GetReturnRequestByIdHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ReturnRequestRepositoryInterface::class);
        $this->handler = new GetReturnRequestByIdHandler($this->repository);
    }

    // -----------------------------------------------------------------------
    // Found
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsDtoWhenReturnRequestExists(): void
    {
        $returnRequestId = ReturnRequestId::generate();
        $tenantId = TenantId::generate();
        $orderId = OrderId::generate();
        $reason = ReturnReason::fromString('Item arrived completely broken and unusable');

        $returnRequest = ReturnRequest::create(
            id: $returnRequestId,
            tenantId: $tenantId,
            orderId: $orderId,
            reason: $reason,
        );

        $this->repository
            ->expects(self::once())
            ->method('findById')
            ->with(self::callback(
                fn (ReturnRequestId $id) => $id->toString() === $returnRequestId->toString()
            ))
            ->willReturn($returnRequest);

        $query = new GetReturnRequestById($returnRequestId->toString());

        $result = ($this->handler)($query);

        self::assertInstanceOf(ReturnRequestDTO::class, $result);
        self::assertSame($returnRequestId->toString(), $result->id);
        self::assertSame($tenantId->toString(), $result->tenantId);
        self::assertSame($orderId->toString(), $result->orderId);
        self::assertSame('Item arrived completely broken and unusable', $result->reason);
        self::assertSame(ReturnStatus::REQUESTED, $result->status);
    }

    #[Test]
    public function itMapsAllDtoFieldsCorrectlyWhenReturnRequestFound(): void
    {
        $returnRequestId = ReturnRequestId::generate();
        $tenantId = TenantId::generate();
        $orderId = OrderId::generate();

        $returnRequest = ReturnRequest::reconstituteFromPersistence(
            id: $returnRequestId,
            tenantId: $tenantId,
            orderId: $orderId,
            reason: ReturnReason::fromString('Customer ordered wrong colour variant'),
            status: ReturnStatus::approved(),
            warehouseId: 'WH-NORTH-001',
            refundAmount: null,
            isResellable: null,
            inspectionNotes: null,
            rejectionReason: null,
            createdAt: new \DateTimeImmutable('2026-01-10 08:00:00'),
            updatedAt: new \DateTimeImmutable('2026-01-11 09:00:00'),
        );

        $this->repository->method('findById')->willReturn($returnRequest);

        $result = ($this->handler)(new GetReturnRequestById($returnRequestId->toString()));

        self::assertSame(ReturnStatus::APPROVED, $result->status);
        self::assertSame('WH-NORTH-001', $result->warehouseId);
        self::assertNull($result->refundAmount);
        self::assertNull($result->refundCurrency);
        self::assertNull($result->isResellable);
        self::assertNull($result->inspectionNotes);
        self::assertNull($result->rejectionReason);
        self::assertSame('2026-01-10 08:00:00', $result->createdAt);
        self::assertSame('2026-01-11 09:00:00', $result->updatedAt);
    }

    // -----------------------------------------------------------------------
    // Not found
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsNullWhenReturnRequestDoesNotExist(): void
    {
        $this->repository
            ->expects(self::once())
            ->method('findById')
            ->willReturn(null);

        $nonExistentId = ReturnRequestId::generate();
        $query = new GetReturnRequestById($nonExistentId->toString());

        $result = ($this->handler)($query);

        self::assertNull($result);
    }

    #[Test]
    public function itPassesCorrectIdToRepositoryWhenNotFound(): void
    {
        $returnRequestId = ReturnRequestId::generate();

        $this->repository
            ->expects(self::once())
            ->method('findById')
            ->with(self::callback(
                fn (ReturnRequestId $id) => $id->toString() === $returnRequestId->toString()
            ))
            ->willReturn(null);

        ($this->handler)(new GetReturnRequestById($returnRequestId->toString()));
    }

    // -----------------------------------------------------------------------
    // Repository delegation
    // -----------------------------------------------------------------------

    #[Test]
    public function itCallsRepositoryFindByIdExactlyOnce(): void
    {
        $this->repository
            ->expects(self::once())
            ->method('findById');

        ($this->handler)(new GetReturnRequestById(ReturnRequestId::generate()->toString()));
    }
}
