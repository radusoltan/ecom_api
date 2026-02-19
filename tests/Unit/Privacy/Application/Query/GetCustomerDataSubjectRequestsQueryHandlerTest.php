<?php

declare(strict_types=1);

namespace App\Tests\Unit\Privacy\Application\Query;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Privacy\Application\DTO\DataSubjectRequestDTO;
use App\Privacy\Application\Query\GetCustomerDataSubjectRequestsQuery;
use App\Privacy\Application\Query\GetCustomerDataSubjectRequestsQueryHandler;
use App\Privacy\Domain\Model\DataSubjectRequest;
use App\Privacy\Domain\Repository\DataSubjectRequestRepositoryInterface;
use App\Privacy\Domain\ValueObject\DataSubjectRequestId;
use App\Privacy\Domain\ValueObject\RequestType;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetCustomerDataSubjectRequestsQueryHandler::class)]
final class GetCustomerDataSubjectRequestsQueryHandlerTest extends TestCase
{
    private DataSubjectRequestRepositoryInterface $repository;
    private GetCustomerDataSubjectRequestsQueryHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(DataSubjectRequestRepositoryInterface::class);
        $this->handler = new GetCustomerDataSubjectRequestsQueryHandler($this->repository);
    }

    #[Test]
    public function handleReturnsRequestDTOsForCustomer(): void
    {
        $customerId = CustomerId::generate();
        $request = DataSubjectRequest::submit(
            DataSubjectRequestId::generate(),
            TenantId::generate(),
            $customerId,
            RequestType::access()
        );

        $query = new GetCustomerDataSubjectRequestsQuery($customerId);

        $this->repository->expects(self::once())
            ->method('findByCustomerId')
            ->with($customerId)
            ->willReturn([$request]);

        $result = ($this->handler)($query);

        self::assertCount(1, $result);
        self::assertInstanceOf(DataSubjectRequestDTO::class, $result[0]);
        self::assertSame('access', $result[0]->requestType);
    }

    #[Test]
    public function handleReturnsEmptyArrayWhenNoRequests(): void
    {
        $query = new GetCustomerDataSubjectRequestsQuery(CustomerId::generate());

        $this->repository->expects(self::once())
            ->method('findByCustomerId')
            ->willReturn([]);

        $result = ($this->handler)($query);

        self::assertIsArray($result);
        self::assertEmpty($result);
    }
}
