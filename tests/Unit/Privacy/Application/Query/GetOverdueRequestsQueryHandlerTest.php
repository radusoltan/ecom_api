<?php

declare(strict_types=1);

namespace App\Tests\Unit\Privacy\Application\Query;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Privacy\Application\DTO\DataSubjectRequestDTO;
use App\Privacy\Application\Query\GetOverdueRequestsQuery;
use App\Privacy\Application\Query\GetOverdueRequestsQueryHandler;
use App\Privacy\Domain\Model\DataSubjectRequest;
use App\Privacy\Domain\Repository\DataSubjectRequestRepositoryInterface;
use App\Privacy\Domain\ValueObject\DataSubjectRequestId;
use App\Privacy\Domain\ValueObject\RequestStatus;
use App\Privacy\Domain\ValueObject\RequestType;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetOverdueRequestsQueryHandler::class)]
final class GetOverdueRequestsQueryHandlerTest extends TestCase
{
    private DataSubjectRequestRepositoryInterface $repository;
    private GetOverdueRequestsQueryHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(DataSubjectRequestRepositoryInterface::class);
        $this->handler = new GetOverdueRequestsQueryHandler($this->repository);
    }

    #[Test]
    public function handleReturnsOverdueRequests(): void
    {
        $tenantId = TenantId::generate();
        $overdueRequest = DataSubjectRequest::reconstituteFromPersistence(
            DataSubjectRequestId::generate(),
            $tenantId,
            CustomerId::generate(),
            RequestType::access(),
            RequestStatus::pending(),
            null,
            null,
            null,
            null,
            new \DateTimeImmutable('-60 days'),
            null,
            new \DateTimeImmutable('-30 days'),
            false,
            new \DateTimeImmutable('-60 days'),
            new \DateTimeImmutable('-60 days')
        );

        $query = new GetOverdueRequestsQuery($tenantId);

        $this->repository->expects(self::once())
            ->method('findOverdueRequests')
            ->with($tenantId)
            ->willReturn([$overdueRequest]);

        $result = ($this->handler)($query);

        self::assertCount(1, $result);
        self::assertInstanceOf(DataSubjectRequestDTO::class, $result[0]);
        self::assertTrue($result[0]->isOverdue);
    }

    #[Test]
    public function handleWithoutTenantIdReturnsAllOverdue(): void
    {
        $query = new GetOverdueRequestsQuery();

        $this->repository->expects(self::once())
            ->method('findOverdueRequests')
            ->with(null)
            ->willReturn([]);

        $result = ($this->handler)($query);

        self::assertEmpty($result);
    }
}
