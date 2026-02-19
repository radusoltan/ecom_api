<?php

declare(strict_types=1);

namespace App\Tests\Unit\Privacy\Application\Query;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Privacy\Application\DTO\DataSubjectRequestDTO;
use App\Privacy\Application\Query\GetDataSubjectRequestQuery;
use App\Privacy\Application\Query\GetDataSubjectRequestQueryHandler;
use App\Privacy\Domain\Model\DataSubjectRequest;
use App\Privacy\Domain\Repository\DataSubjectRequestRepositoryInterface;
use App\Privacy\Domain\ValueObject\DataSubjectRequestId;
use App\Privacy\Domain\ValueObject\RequestType;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetDataSubjectRequestQueryHandler::class)]
final class GetDataSubjectRequestQueryHandlerTest extends TestCase
{
    private DataSubjectRequestRepositoryInterface $repository;
    private GetDataSubjectRequestQueryHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(DataSubjectRequestRepositoryInterface::class);
        $this->handler = new GetDataSubjectRequestQueryHandler($this->repository);
    }

    #[Test]
    public function handleReturnsRequestDTO(): void
    {
        $requestId = DataSubjectRequestId::generate();
        $request = DataSubjectRequest::submit(
            $requestId,
            TenantId::generate(),
            CustomerId::generate(),
            RequestType::access()
        );

        $query = new GetDataSubjectRequestQuery($requestId);

        $this->repository->expects(self::once())
            ->method('findById')
            ->with($requestId)
            ->willReturn($request);

        $result = ($this->handler)($query);

        self::assertInstanceOf(DataSubjectRequestDTO::class, $result);
        self::assertSame($requestId->toString(), $result->id);
        self::assertSame('access', $result->requestType);
        self::assertSame('pending', $result->status);
        self::assertFalse($result->isExtended);
        self::assertFalse($result->isOverdue);
    }

    #[Test]
    public function handleThrowsExceptionWhenNotFound(): void
    {
        $requestId = DataSubjectRequestId::generate();
        $query = new GetDataSubjectRequestQuery($requestId);

        $this->repository->expects(self::once())
            ->method('findById')
            ->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Data subject request not found');

        ($this->handler)($query);
    }
}
