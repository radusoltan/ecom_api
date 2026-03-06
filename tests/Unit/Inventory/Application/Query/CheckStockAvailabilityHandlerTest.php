<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Application\Query;

use App\Catalog\Domain\Model\ProductId;
use App\Inventory\Application\Query\CheckStockAvailability\CheckStockAvailabilityHandler;
use App\Inventory\Application\Query\CheckStockAvailability\CheckStockAvailabilityQuery;
use App\Inventory\Application\Query\CheckStockAvailability\StockAvailabilityItem;
use App\Inventory\Application\Query\CheckStockAvailability\StockAvailabilityResult;
use App\Inventory\Domain\Model\Quantity;
use App\Inventory\Domain\Model\StockItem;
use App\Inventory\Domain\Model\StockItemId;
use App\Inventory\Domain\Model\WarehouseId;
use App\Inventory\Domain\Repository\StockItemRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CheckStockAvailabilityHandler::class)]
#[CoversClass(CheckStockAvailabilityQuery::class)]
final class CheckStockAvailabilityHandlerTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    private StockItemRepositoryInterface $repository;
    private CheckStockAvailabilityHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(StockItemRepositoryInterface::class);
        $this->handler = new CheckStockAvailabilityHandler($this->repository);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function buildStockItem(
        ProductId $productId,
        int $onHand,
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
    // Empty items list
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsAvailableForEmptyItemsList(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $query = new CheckStockAvailabilityQuery(items: [], tenantId: $tenantId);
        $result = ($this->handler)($query);

        self::assertInstanceOf(StockAvailabilityResult::class, $result);
        self::assertTrue($result->available);
        self::assertSame([], $result->items);
    }

    // -----------------------------------------------------------------------
    // Single item — sufficient stock
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsAvailableTrueWhenSufficientStock(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $stockItem = $this->buildStockItem($productId, onHand: 100);

        $this->repository
            ->method('findByProduct')
            ->willReturn([$stockItem]);

        $item = new StockAvailabilityItem($productId, variantId: null, quantity: 10);
        $query = new CheckStockAvailabilityQuery(items: [$item], tenantId: $tenantId);

        $result = ($this->handler)($query);

        self::assertTrue($result->available);
        self::assertCount(1, $result->items);
        self::assertTrue($result->items[0]->available);
        self::assertSame(10, $result->items[0]->requestedQuantity);
        self::assertSame(100, $result->items[0]->availableQuantity);
        self::assertSame($productId->toString(), $result->items[0]->productId);
    }

    // -----------------------------------------------------------------------
    // Single item — insufficient stock
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsAvailableFalseWhenInsufficientStock(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $stockItem = $this->buildStockItem($productId, onHand: 5);

        $this->repository
            ->method('findByProduct')
            ->willReturn([$stockItem]);

        $item = new StockAvailabilityItem($productId, variantId: null, quantity: 10);
        $query = new CheckStockAvailabilityQuery(items: [$item], tenantId: $tenantId);

        $result = ($this->handler)($query);

        self::assertFalse($result->available);
        self::assertCount(1, $result->items);
        self::assertFalse($result->items[0]->available);
        self::assertSame(5, $result->items[0]->availableQuantity);
    }

    // -----------------------------------------------------------------------
    // Single item — exactly at requested quantity
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsAvailableTrueWhenStockExactlyMatchesRequest(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $stockItem = $this->buildStockItem($productId, onHand: 10);

        $this->repository
            ->method('findByProduct')
            ->willReturn([$stockItem]);

        $item = new StockAvailabilityItem($productId, variantId: null, quantity: 10);
        $query = new CheckStockAvailabilityQuery(items: [$item], tenantId: $tenantId);

        $result = ($this->handler)($query);

        self::assertTrue($result->available);
        self::assertTrue($result->items[0]->available);
    }

    // -----------------------------------------------------------------------
    // Single item — no stock records (returns 0)
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsZeroAvailableQuantityWhenNoStockRecords(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $this->repository
            ->method('findByProduct')
            ->willReturn([]);

        $item = new StockAvailabilityItem($productId, variantId: null, quantity: 1);
        $query = new CheckStockAvailabilityQuery(items: [$item], tenantId: $tenantId);

        $result = ($this->handler)($query);

        self::assertFalse($result->available);
        self::assertSame(0, $result->items[0]->availableQuantity);
        self::assertFalse($result->items[0]->available);
    }

    // -----------------------------------------------------------------------
    // Multiple items — all available
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsAvailableTrueWhenAllItemsAvailable(): void
    {
        $productId1 = ProductId::generate();
        $productId2 = ProductId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $stockItem1 = $this->buildStockItem($productId1, onHand: 100);
        $stockItem2 = $this->buildStockItem($productId2, onHand: 200);

        $callCount = 0;
        $this->repository
            ->method('findByProduct')
            ->willReturnCallback(function (ProductId $productId) use ($productId1, $stockItem1, $stockItem2, &$callCount) {
                ++$callCount;
                if ($productId->toString() === $productId1->toString()) {
                    return [$stockItem1];
                }

                return [$stockItem2];
            });

        $items = [
            new StockAvailabilityItem($productId1, variantId: null, quantity: 10),
            new StockAvailabilityItem($productId2, variantId: null, quantity: 20),
        ];
        $query = new CheckStockAvailabilityQuery(items: $items, tenantId: $tenantId);
        $result = ($this->handler)($query);

        self::assertTrue($result->available);
        self::assertCount(2, $result->items);
        self::assertTrue($result->items[0]->available);
        self::assertTrue($result->items[1]->available);
        self::assertSame(2, $callCount);
    }

    // -----------------------------------------------------------------------
    // Multiple items — one unavailable flips overall to false
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsAvailableFalseWhenOneItemIsUnavailable(): void
    {
        $productId1 = ProductId::generate();
        $productId2 = ProductId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $stockItem1 = $this->buildStockItem($productId1, onHand: 100);
        $stockItem2 = $this->buildStockItem($productId2, onHand: 2);  // not enough

        $this->repository
            ->method('findByProduct')
            ->willReturnCallback(fn (ProductId $pid) => $pid->toString() === $productId1->toString()
                ? [$stockItem1]
                : [$stockItem2]);

        $items = [
            new StockAvailabilityItem($productId1, variantId: null, quantity: 10),
            new StockAvailabilityItem($productId2, variantId: null, quantity: 5),
        ];
        $query = new CheckStockAvailabilityQuery(items: $items, tenantId: $tenantId);
        $result = ($this->handler)($query);

        self::assertFalse($result->available);
        self::assertTrue($result->items[0]->available);   // first item OK
        self::assertFalse($result->items[1]->available);  // second item not OK
    }

    // -----------------------------------------------------------------------
    // Stock aggregated across multiple warehouses
    // -----------------------------------------------------------------------

    #[Test]
    public function itAggregatesStockAcrossMultipleWarehouses(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);

        // Two warehouses: 30 + 40 = 70 total available
        $stockItem1 = $this->buildStockItem($productId, onHand: 30);
        $stockItem2 = $this->buildStockItem($productId, onHand: 40);

        $this->repository
            ->method('findByProduct')
            ->willReturn([$stockItem1, $stockItem2]);

        // Request 60 — should be satisfied by combined 70
        $item = new StockAvailabilityItem($productId, variantId: null, quantity: 60);
        $query = new CheckStockAvailabilityQuery(items: [$item], tenantId: $tenantId);

        $result = ($this->handler)($query);

        self::assertTrue($result->available);
        self::assertTrue($result->items[0]->available);
        self::assertSame(70, $result->items[0]->availableQuantity);
    }

    // -----------------------------------------------------------------------
    // variantId propagated to result
    // -----------------------------------------------------------------------

    #[Test]
    public function itPropagatesVariantIdToItemResult(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $stockItem = $this->buildStockItem($productId, onHand: 50);

        $this->repository
            ->method('findByProduct')
            ->willReturn([$stockItem]);

        $item = new StockAvailabilityItem($productId, variantId: 'variant-abc', quantity: 5);
        $query = new CheckStockAvailabilityQuery(items: [$item], tenantId: $tenantId);

        $result = ($this->handler)($query);

        self::assertSame('variant-abc', $result->items[0]->variantId);
    }
}
