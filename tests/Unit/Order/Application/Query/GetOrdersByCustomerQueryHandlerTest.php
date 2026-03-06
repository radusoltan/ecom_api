<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Application\Query;

use App\Order\Application\Query\GetOrdersByCustomerQuery;
use App\Order\Application\Query\GetOrdersByCustomerQueryHandler;
use App\Order\Domain\Repository\OrderRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class GetOrdersByCustomerQueryHandlerTest extends TestCase
{
    public function testItReturnsOrdersForCustomer(): void
    {
        $repo = $this->createStub(OrderRepositoryInterface::class);
        $repo->method('findByCustomerEmail')->willReturn([]);

        $handler = new GetOrdersByCustomerQueryHandler($repo);
        $result = ($handler)(new GetOrdersByCustomerQuery('customer@example.com', TenantId::generate()->toString()));

        self::assertSame([], $result);
    }
}
