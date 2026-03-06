<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\Query;

use App\Customer\Application\DTO\CustomerSummaryDTO;
use App\Customer\Application\Query\SearchCustomers\SearchCustomersQuery;
use App\Customer\Application\Query\SearchCustomers\SearchCustomersQueryHandler;
use App\Customer\Application\Query\SearchCustomers\SearchCustomersResult;
use App\Customer\Domain\Model\Customer;
use App\Customer\Domain\Repository\CustomerSearchRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(SearchCustomersQueryHandler::class)]
final class SearchCustomersQueryHandlerTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    private CustomerSearchRepositoryInterface&MockObject $searchRepository;
    private SearchCustomersQueryHandler $handler;

    protected function setUp(): void
    {
        $this->searchRepository = $this->createMock(CustomerSearchRepositoryInterface::class);
        $this->handler = new SearchCustomersQueryHandler($this->searchRepository);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeCustomer(string $email = 'alice@example.com'): Customer
    {
        $customer = Customer::register(
            CustomerId::generate(),
            TenantId::fromString(self::TENANT_ID),
            Email::fromString($email),
            'Alice',
            'Smith',
        );
        $customer->popEvents();

        return $customer;
    }

    // -----------------------------------------------------------------------
    // Returns paginated results with items
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsPaginatedResultWithMatchingCustomers(): void
    {
        $customer = $this->makeCustomer();

        $this->searchRepository
            ->expects(self::once())
            ->method('search')
            ->willReturn(['customers' => [$customer], 'total' => 1]);

        $query = new SearchCustomersQuery(
            tenantId: self::TENANT_ID,
            searchTerm: 'alice',
            page: 1,
            limit: 20,
        );

        $result = ($this->handler)($query);

        self::assertInstanceOf(SearchCustomersResult::class, $result);
        self::assertCount(1, $result->items);
        self::assertSame(1, $result->total);
        self::assertSame(1, $result->page);
        self::assertSame(20, $result->limit);
        self::assertSame(1, $result->totalPages);
        self::assertContainsOnlyInstancesOf(CustomerSummaryDTO::class, $result->items);
    }

    #[Test]
    public function itReturnsEmptyResultWhenNoCustomersMatch(): void
    {
        $this->searchRepository
            ->method('search')
            ->willReturn(['customers' => [], 'total' => 0]);

        $query = new SearchCustomersQuery(tenantId: self::TENANT_ID, searchTerm: 'nonexistent');
        $result = ($this->handler)($query);

        self::assertSame(0, $result->total);
        self::assertSame([], $result->items);
        self::assertSame(0, $result->totalPages);
    }

    // -----------------------------------------------------------------------
    // Pagination metadata
    // -----------------------------------------------------------------------

    #[Test]
    public function itCalculatesTotalPagesCorrectly(): void
    {
        $customers = array_map(
            fn (int $i) => $this->makeCustomer("user{$i}@example.com"),
            range(1, 3),
        );

        $this->searchRepository
            ->method('search')
            ->willReturn(['customers' => $customers, 'total' => 25]);

        $query = new SearchCustomersQuery(tenantId: self::TENANT_ID, page: 2, limit: 10);
        $result = ($this->handler)($query);

        self::assertSame(25, $result->total);
        self::assertSame(2, $result->page);
        self::assertSame(10, $result->limit);
        self::assertSame(3, $result->totalPages);
    }

    // -----------------------------------------------------------------------
    // Query properties are forwarded to criteria
    // -----------------------------------------------------------------------

    #[Test]
    public function itForwardsPaginationParamsFromQuery(): void
    {
        $this->searchRepository
            ->method('search')
            ->willReturn(['customers' => [], 'total' => 0]);

        $query = new SearchCustomersQuery(tenantId: self::TENANT_ID, page: 3, limit: 5);
        $result = ($this->handler)($query);

        self::assertSame(3, $result->page);
        self::assertSame(5, $result->limit);
    }

    #[Test]
    public function itHandlesMultipleCustomersInResult(): void
    {
        $customers = [
            $this->makeCustomer('bob@example.com'),
            $this->makeCustomer('carol@example.com'),
            $this->makeCustomer('dave@example.com'),
        ];

        $this->searchRepository
            ->method('search')
            ->willReturn(['customers' => $customers, 'total' => 3]);

        $query = new SearchCustomersQuery(tenantId: self::TENANT_ID);
        $result = ($this->handler)($query);

        self::assertCount(3, $result->items);
    }

    // -----------------------------------------------------------------------
    // toArray representation
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsCorrectToArrayStructure(): void
    {
        $this->searchRepository
            ->method('search')
            ->willReturn(['customers' => [], 'total' => 0]);

        $query = new SearchCustomersQuery(tenantId: self::TENANT_ID, page: 1, limit: 20);
        $result = ($this->handler)($query);

        $array = $result->toArray();

        self::assertArrayHasKey('items', $array);
        self::assertArrayHasKey('total', $array);
        self::assertArrayHasKey('page', $array);
        self::assertArrayHasKey('limit', $array);
        self::assertArrayHasKey('totalPages', $array);
    }
}
