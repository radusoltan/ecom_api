<?php

declare(strict_types=1);

namespace App\Tests\Unit\Returns\Application\Query;

use App\Order\Domain\Model\OrderId;
use App\Returns\Application\DTO\ReturnRequestDTO;
use App\Returns\Application\Query\GetReturnRequestsByOrderId;
use App\Returns\Application\Query\GetReturnRequestsByOrderIdHandler;
use App\Returns\Domain\Model\ReturnRequest;
use App\Returns\Domain\Repository\ReturnRequestRepositoryInterface;
use App\Returns\Domain\ValueObject\ReturnReason;
use App\Returns\Domain\ValueObject\ReturnRequestId;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetReturnRequestsByOrderIdHandler::class)]
final class GetReturnRequestsByOrderIdHandlerTest extends TestCase
{
    private ReturnRequestRepositoryInterface&MockObject $repository;
    private GetReturnRequestsByOrderIdHandler $handler;
    private TenantId $tenantId;
    private OrderId $orderId;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ReturnRequestRepositoryInterface::class);
        $this->handler = new GetReturnRequestsByOrderIdHandler($this->repository);
        $this->tenantId = TenantId::generate();
        $this->orderId = OrderId::generate();
    }

    // -----------------------------------------------------------------------
    // Returns list of DTOs
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsArrayOfDtosWhenReturnRequestsExistForOrder(): void
    {
        $returnRequests = [
            $this->makeReturnRequest($this->orderId),
            $this->makeReturnRequest($this->orderId),
        ];

        $this->repository
            ->expects(self::once())
            ->method('findByOrderId')
            ->with(self::callback(
                fn (OrderId $id) => $id->toString() === $this->orderId->toString()
            ))
            ->willReturn($returnRequests);

        $query = new GetReturnRequestsByOrderId($this->orderId->toString());

        $result = ($this->handler)($query);

        self::assertCount(2, $result);
        self::assertContainsOnlyInstancesOf(ReturnRequestDTO::class, $result);
    }

    #[Test]
    public function itReturnsEmptyArrayWhenNoReturnRequestsForOrder(): void
    {
        $this->repository
            ->expects(self::once())
            ->method('findByOrderId')
            ->willReturn([]);

        $result = ($this->handler)(new GetReturnRequestsByOrderId($this->orderId->toString()));

        self::assertSame([], $result);
    }

    #[Test]
    public function itReturnsSingleDtoWhenOneReturnRequestExists(): void
    {
        $returnRequest = $this->makeReturnRequest($this->orderId);

        $this->repository
            ->method('findByOrderId')
            ->willReturn([$returnRequest]);

        $result = ($this->handler)(new GetReturnRequestsByOrderId($this->orderId->toString()));

        self::assertCount(1, $result);
        self::assertInstanceOf(ReturnRequestDTO::class, $result[0]);
    }

    // -----------------------------------------------------------------------
    // DTO field mapping
    // -----------------------------------------------------------------------

    #[Test]
    public function itMapsReturnRequestFieldsToDto(): void
    {
        $returnRequestId = ReturnRequestId::generate();
        $reason = ReturnReason::fromString('Item received with significant damage to packaging');

        $returnRequest = ReturnRequest::create(
            id: $returnRequestId,
            tenantId: $this->tenantId,
            orderId: $this->orderId,
            reason: $reason,
        );

        $this->repository
            ->method('findByOrderId')
            ->willReturn([$returnRequest]);

        $result = ($this->handler)(new GetReturnRequestsByOrderId($this->orderId->toString()));

        self::assertCount(1, $result);
        $dto = $result[0];
        self::assertSame($returnRequestId->toString(), $dto->id);
        self::assertSame($this->tenantId->toString(), $dto->tenantId);
        self::assertSame($this->orderId->toString(), $dto->orderId);
        self::assertSame('Item received with significant damage to packaging', $dto->reason);
        self::assertSame('requested', $dto->status);
    }

    #[Test]
    public function itPreservesOrderOfReturnedDtos(): void
    {
        $ids = [ReturnRequestId::generate(), ReturnRequestId::generate(), ReturnRequestId::generate()];

        $returnRequests = array_map(
            fn (ReturnRequestId $id) => ReturnRequest::create(
                id: $id,
                tenantId: $this->tenantId,
                orderId: $this->orderId,
                reason: ReturnReason::fromString('Product quality does not meet expectations'),
            ),
            $ids
        );

        $this->repository
            ->method('findByOrderId')
            ->willReturn($returnRequests);

        $result = ($this->handler)(new GetReturnRequestsByOrderId($this->orderId->toString()));

        self::assertCount(3, $result);
        self::assertSame($ids[0]->toString(), $result[0]->id);
        self::assertSame($ids[1]->toString(), $result[1]->id);
        self::assertSame($ids[2]->toString(), $result[2]->id);
    }

    // -----------------------------------------------------------------------
    // Repository delegation
    // -----------------------------------------------------------------------

    #[Test]
    public function itPassesCorrectOrderIdToRepository(): void
    {
        $orderId = OrderId::generate();

        $this->repository
            ->expects(self::once())
            ->method('findByOrderId')
            ->with(self::callback(
                fn (OrderId $id) => $id->toString() === $orderId->toString()
            ))
            ->willReturn([]);

        ($this->handler)(new GetReturnRequestsByOrderId($orderId->toString()));
    }

    #[Test]
    public function itCallsFindByOrderIdExactlyOnce(): void
    {
        $this->repository
            ->expects(self::once())
            ->method('findByOrderId')
            ->willReturn([]);

        ($this->handler)(new GetReturnRequestsByOrderId($this->orderId->toString()));
    }

    #[Test]
    public function itNeverCallsOtherRepositoryMethods(): void
    {
        $this->repository
            ->expects(self::never())
            ->method('findById');

        $this->repository
            ->expects(self::never())
            ->method('findByTenantId');

        $this->repository
            ->expects(self::never())
            ->method('findByStatus');

        $this->repository
            ->method('findByOrderId')
            ->willReturn([]);

        ($this->handler)(new GetReturnRequestsByOrderId($this->orderId->toString()));
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeReturnRequest(OrderId $orderId): ReturnRequest
    {
        return ReturnRequest::create(
            id: ReturnRequestId::generate(),
            tenantId: $this->tenantId,
            orderId: $orderId,
            reason: ReturnReason::fromString('Product does not match the description provided online'),
        );
    }
}
