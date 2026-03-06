<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\Query;

use App\Customer\Application\Query\GetCustomerTransactions\GetCustomerTransactionsQuery;
use App\Customer\Application\Query\GetCustomerTransactions\GetCustomerTransactionsQueryHandler;
use App\Customer\Domain\Repository\LoyaltyPointTransactionRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class GetCustomerTransactionsQueryHandlerTest extends TestCase
{
    public function testItReturnsPaginatedTransactions(): void
    {
        $repo = $this->createStub(LoyaltyPointTransactionRepositoryInterface::class);
        $repo->method('findByCustomerIdPaginated')->willReturn([]);
        $repo->method('countByCustomerId')->willReturn(0);

        $handler = new GetCustomerTransactionsQueryHandler($repo);
        $result = ($handler)(new GetCustomerTransactionsQuery(CustomerId::generate(), TenantId::generate()));

        self::assertSame([], $result['transactions']);
        self::assertSame(0, $result['total']);
        self::assertSame(1, $result['page']);
        self::assertSame(20, $result['limit']);
    }
}
