<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Application\Query;

use App\Catalog\Domain\Model\ProductId;
use App\Inventory\Application\Query\GetStockByProduct\GetStockByProductHandler;
use App\Inventory\Application\Query\GetStockByProduct\GetStockByProductQuery;
use App\Inventory\Application\Query\GetStockByProduct\StockItemDTO;
use App\Inventory\Domain\Model\Quantity;
use App\Inventory\Domain\Model\StockItem;
use App\Inventory\Domain\Model\StockItemId;
use App\Inventory\Domain\Model\WarehouseId;
use App\Inventory\Domain\Repository\StockItemRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetStockByProductHandler::class)]
#[CoversClass(GetStockByProductQuery::class)]
final class GetStockByProductHandlerTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    private StockItemRepositoryInterface $repository;
    private GetStockByProductHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(StockItemRepositoryInterface::class);
        $this->handler = new GetStockByProductHandler($this->repository);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function buildStockItem(
        ProductId $productId,
        int $onHand = 100,
        int $threshold = 10,
    ): StockItem {
        $tenantId = TenantId::fromString(self::TENANT_ID);

        return StockItem::create(
            StockItemId::generate(),
            $tenantId,
            $productId,
            WarehouseId::generate(),
            Quantity::fromInt($onHand),
            Quantity::fromInt($threshold),
        );
    }

    // -----------------------------------------------------------------------
    // Happy path — one warehouse
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsMappedStockItemDtos(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $stockItem = $this->buildStockItem($productId, onHand: 80);

        $this->repository
            ->method('findByProduct')
            ->with(
                self::callback(fn ($p) => $p->toString() === $productId->toString()),
                self::callback(fn ($t) => self::TENANT_ID === $t->toString()),
            )
            ->willReturn([$stockItem]);

        $query = new GetStockByProductQuery($productId, $tenantId);
        $result = ($this->handler)($query);

        self::assertCount(1, $result);
        self::assertContainsOnlyInstancesOf(StockItemDTO::class, $result);
        self::assertSame(80, $result[0]->onHand);
        self::assertSame(0, $result[0]->reserved);
        self::assertSame(0, $result[0]->allocated);
        self::assertSame(80, $result[0]->available);
        self::assertSame($productId->toString(), $result[0]->productId);
    }

    // -----------------------------------------------------------------------
    // Multiple warehouses
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsDtoForEachWarehouse(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $stockItem1 = $this->buildStockItem($productId, onHand: 50);
        $stockItem2 = $this->buildStockItem($productId, onHand: 30);

        $this->repository
            ->method('findByProduct')
            ->willReturn([$stockItem1, $stockItem2]);

        $query = new GetStockByProductQuery($productId, $tenantId);
        $result = ($this->handler)($query);

        self::assertCount(2, $result);
        self::assertSame(50, $result[0]->onHand);
        self::assertSame(30, $result[1]->onHand);
    }

    // -----------------------------------------------------------------------
    // No stock records
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsEmptyArrayWhenNoStockRecordsExist(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $this->repository
            ->method('findByProduct')
            ->willReturn([]);

        $query = new GetStockByProductQuery($productId, $tenantId);
        $result = ($this->handler)($query);

        self::assertSame([], $result);
    }

    // -----------------------------------------------------------------------
    // DTO fields — low stock flag
    // -----------------------------------------------------------------------

    #[Test]
    public function itMarksLowStockCorrectlyWhenAvailableAtThreshold(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);

        // onHand=10, threshold=10 => available=10 <= threshold=10 => isLowStock=true
        $stockItem = $this->buildStockItem($productId, onHand: 10, threshold: 10);

        $this->repository
            ->method('findByProduct')
            ->willReturn([$stockItem]);

        $result = ($this->handler)(new GetStockByProductQuery($productId, $tenantId));

        self::assertTrue($result[0]->isLowStock);
    }

    #[Test]
    public function itDoesNotMarkLowStockWhenAvailableAboveThreshold(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);

        // onHand=50, threshold=10 => available=50 > 10 => isLowStock=false
        $stockItem = $this->buildStockItem($productId, onHand: 50, threshold: 10);

        $this->repository
            ->method('findByProduct')
            ->willReturn([$stockItem]);

        $result = ($this->handler)(new GetStockByProductQuery($productId, $tenantId));

        self::assertFalse($result[0]->isLowStock);
    }
}
