<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Application\Query;

use App\Inventory\Application\Query\GetLowStockItems\GetLowStockItemsHandler;
use App\Inventory\Application\Query\GetLowStockItems\GetLowStockItemsQuery;
use App\Inventory\Domain\Repository\StockItemRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class GetLowStockItemsHandlerTest extends TestCase
{
    public function testItReturnsLowStockItems(): void
    {
        $repo = $this->createStub(StockItemRepositoryInterface::class);
        $repo->method('findLowStockItems')->willReturn([]);

        $handler = new GetLowStockItemsHandler($repo);
        $result = ($handler)(new GetLowStockItemsQuery(TenantId::generate()));

        self::assertSame([], $result);
    }
}
