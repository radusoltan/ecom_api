<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Domain\Service;

use App\Catalog\Domain\Model\ProductId;
use App\Inventory\Domain\Model\Quantity;
use App\Inventory\Domain\Model\StockItem;
use App\Inventory\Domain\Model\Warehouse;
use App\Inventory\Domain\Model\WarehouseCode;
use App\Inventory\Domain\Model\WarehouseId;
use App\Inventory\Domain\Model\WarehouseName;
use App\Inventory\Domain\Repository\StockItemRepositoryInterface;
use App\Inventory\Domain\Repository\WarehouseRepositoryInterface;
use App\Order\Domain\Model\Order;
use App\Order\Domain\Model\OrderId;
use App\Order\Domain\Model\OrderLine;
use App\Order\Domain\Service\WarehouseRoutingService;
use App\Order\Domain\ValueObject\OrderProductId;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[Group('order')]
final class WarehouseRoutingServiceTest extends TestCase
{
    private WarehouseRepositoryInterface&MockObject $warehouseRepo;
    private StockItemRepositoryInterface&MockObject $stockItemRepo;
    private WarehouseRoutingService $service;

    private TenantId $tenantId;
    private OrderId $orderId;

    protected function setUp(): void
    {
        $this->warehouseRepo = $this->createMock(WarehouseRepositoryInterface::class);
        $this->stockItemRepo = $this->createMock(StockItemRepositoryInterface::class);
        $this->service = new WarehouseRoutingService(
            $this->warehouseRepo,
            $this->stockItemRepo,
            new NullLogger(),
        );
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $this->orderId = OrderId::generate();
    }

    public function testFindBestWarehouseNoWarehouses(): void
    {
        $order = $this->createOrderMock([]);

        $this->warehouseRepo
            ->method('findActiveByTenant')
            ->willReturn([]);

        $result = $this->service->findBestWarehouse($order);

        self::assertNull($result);
    }

    public function testFindBestWarehouseSelectsHighestPriority(): void
    {
        $wh1Id = WarehouseId::generate();
        $wh2Id = WarehouseId::generate();

        $wh1 = $this->createWarehouseMock($wh1Id, 'WH-02', 'Secondary', 2);
        $wh2 = $this->createWarehouseMock($wh2Id, 'WH-01', 'Primary', 1);

        $productId = OrderProductId::fromString((string) ProductId::generate());
        $line = $this->createOrderLineMock($productId, 5);
        $order = $this->createOrderMock([$line]);

        $this->warehouseRepo->method('findActiveByTenant')->willReturn([$wh1, $wh2]);

        $stockItem = $this->createStockItemMock(10);

        $this->stockItemRepo
            ->method('findByProductAndWarehouse')
            ->willReturnCallback(function ($pId, $whId) use ($wh2Id, $stockItem) {
                if ($whId->equals($wh2Id)) {
                    return $stockItem;
                }

                return null;
            });

        $result = $this->service->findBestWarehouse($order);

        self::assertNotNull($result);
        self::assertTrue($result->equals($wh2Id));
    }

    public function testFindBestWarehouseSkipsInsufficientStock(): void
    {
        $wh1Id = WarehouseId::generate();
        $wh2Id = WarehouseId::generate();

        $wh1 = $this->createWarehouseMock($wh1Id, 'WH-01', 'Primary', 1);
        $wh2 = $this->createWarehouseMock($wh2Id, 'WH-02', 'Secondary', 2);

        $productId = OrderProductId::fromString((string) ProductId::generate());
        $line = $this->createOrderLineMock($productId, 10);
        $order = $this->createOrderMock([$line]);

        $this->warehouseRepo->method('findActiveByTenant')->willReturn([$wh1, $wh2]);

        $lowStock = $this->createStockItemMock(3);
        $highStock = $this->createStockItemMock(15);

        $this->stockItemRepo
            ->method('findByProductAndWarehouse')
            ->willReturnCallback(function ($pId, $whId) use ($wh1Id, $wh2Id, $lowStock, $highStock) {
                if ($whId->equals($wh1Id)) {
                    return $lowStock;
                }
                if ($whId->equals($wh2Id)) {
                    return $highStock;
                }

                return null;
            });

        $result = $this->service->findBestWarehouse($order);

        self::assertNotNull($result);
        self::assertTrue($result->equals($wh2Id));
    }

    public function testFindBestWarehouseNoStockAnywhere(): void
    {
        $wh1 = $this->createWarehouseMock(WarehouseId::generate(), 'WH-01', 'Primary', 1);

        $productId = OrderProductId::fromString((string) ProductId::generate());
        $line = $this->createOrderLineMock($productId, 10);
        $order = $this->createOrderMock([$line]);

        $this->warehouseRepo->method('findActiveByTenant')->willReturn([$wh1]);
        $this->stockItemRepo->method('findByProductAndWarehouse')->willReturn(null);

        $result = $this->service->findBestWarehouse($order);

        self::assertNull($result);
    }

    public function testCanWarehouseFulfillOrderAllItemsAvailable(): void
    {
        $productId = OrderProductId::fromString((string) ProductId::generate());
        $line = $this->createOrderLineMock($productId, 5);
        $order = $this->createOrderMock([$line]);

        $stockItem = $this->createStockItemMock(10);
        $warehouseId = WarehouseId::generate();

        $this->stockItemRepo->method('findByProductAndWarehouse')->willReturn($stockItem);

        $result = $this->service->canWarehouseFulfillOrder($warehouseId, $order, $this->tenantId);

        self::assertTrue($result);
    }

    public function testCanWarehouseFulfillOrderProductNotStocked(): void
    {
        $productId = OrderProductId::fromString((string) ProductId::generate());
        $line = $this->createOrderLineMock($productId, 5);
        $order = $this->createOrderMock([$line]);

        $this->stockItemRepo->method('findByProductAndWarehouse')->willReturn(null);

        $result = $this->service->canWarehouseFulfillOrder(WarehouseId::generate(), $order, $this->tenantId);

        self::assertFalse($result);
    }

    public function testCanWarehouseFulfillOrderInsufficientQuantity(): void
    {
        $productId = OrderProductId::fromString((string) ProductId::generate());
        $line = $this->createOrderLineMock($productId, 10);
        $order = $this->createOrderMock([$line]);

        $stockItem = $this->createStockItemMock(5);
        $this->stockItemRepo->method('findByProductAndWarehouse')->willReturn($stockItem);

        $result = $this->service->canWarehouseFulfillOrder(WarehouseId::generate(), $order, $this->tenantId);

        self::assertFalse($result);
    }

    public function testGetAvailabilityReportMixed(): void
    {
        $wh1Id = WarehouseId::generate();
        $wh2Id = WarehouseId::generate();

        $wh1 = $this->createWarehouseMock($wh1Id, 'WH-01', 'Primary', 1);
        $wh2 = $this->createWarehouseMock($wh2Id, 'WH-02', 'Secondary', 2);

        $productId = OrderProductId::fromString((string) ProductId::generate());
        $line = $this->createOrderLineMock($productId, 5);
        $order = $this->createOrderMock([$line]);

        $this->warehouseRepo->method('findActiveByTenant')->willReturn([$wh1, $wh2]);

        $stockItem = $this->createStockItemMock(10);

        $this->stockItemRepo
            ->method('findByProductAndWarehouse')
            ->willReturnCallback(function ($pId, $whId) use ($wh1Id, $stockItem) {
                if ($whId->equals($wh1Id)) {
                    return $stockItem;
                }

                return null;
            });

        $report = $this->service->getAvailabilityReport($order);

        self::assertCount(2, $report);

        // WH1 can fulfill
        $wh1Report = $report[$wh1Id->toString()];
        self::assertTrue($wh1Report['canFulfill']);
        self::assertEmpty($wh1Report['missingProducts']);
        self::assertSame('WH-01', $wh1Report['warehouseCode']);

        // WH2 cannot fulfill (product not stocked)
        $wh2Report = $report[$wh2Id->toString()];
        self::assertFalse($wh2Report['canFulfill']);
        self::assertCount(1, $wh2Report['missingProducts']);
        self::assertSame('not_stocked', $wh2Report['missingProducts'][0]['reason']);
    }

    public function testGetAvailabilityReportInsufficientStock(): void
    {
        $whId = WarehouseId::generate();
        $wh = $this->createWarehouseMock($whId, 'WH-01', 'Primary', 1);

        $productId = OrderProductId::fromString((string) ProductId::generate());
        $line = $this->createOrderLineMock($productId, 20);
        $order = $this->createOrderMock([$line]);

        $this->warehouseRepo->method('findActiveByTenant')->willReturn([$wh]);

        $stockItem = $this->createStockItemMock(5);
        $this->stockItemRepo->method('findByProductAndWarehouse')->willReturn($stockItem);

        $report = $this->service->getAvailabilityReport($order);

        $whReport = $report[$whId->toString()];
        self::assertFalse($whReport['canFulfill']);
        self::assertSame('insufficient_stock', $whReport['missingProducts'][0]['reason']);
        self::assertSame(5, $whReport['missingProducts'][0]['available']);
        self::assertSame(20, $whReport['missingProducts'][0]['required']);
    }

    /** @return Order&MockObject */
    private function createOrderMock(array $lines): Order
    {
        $order = $this->createMock(Order::class);
        $order->method('id')->willReturn($this->orderId);
        $order->method('tenantId')->willReturn($this->tenantId);
        $order->method('lines')->willReturn($lines);

        return $order;
    }

    /** @return OrderLine&MockObject */
    private function createOrderLineMock(OrderProductId $productId, int $quantity): OrderLine
    {
        $line = $this->createMock(OrderLine::class);
        $line->method('productId')->willReturn($productId);
        $line->method('quantity')->willReturn($quantity);

        return $line;
    }

    /** @return Warehouse&MockObject */
    private function createWarehouseMock(WarehouseId $id, string $code, string $name, int $priority): Warehouse
    {
        $wh = $this->createMock(Warehouse::class);
        $wh->method('id')->willReturn($id);
        $wh->method('code')->willReturn(WarehouseCode::fromString($code));
        // WarehouseRoutingService::getAvailabilityReport calls ->name()->toString()
        // but WarehouseName only has value(). BypassFinals strips final so we can extend.
        $whNameMock = new class($name) extends WarehouseName {
            public function __construct(private readonly string $n)
            {
                // Skip parent constructor validation
            }

            public function toString(): string
            {
                return $this->n;
            }

            public function value(): string
            {
                return $this->n;
            }
        };
        $wh->method('name')->willReturn($whNameMock);
        $wh->method('priority')->willReturn($priority);

        return $wh;
    }

    /** @return StockItem&MockObject */
    private function createStockItemMock(int $availableQty): StockItem
    {
        $stockItem = $this->createMock(StockItem::class);
        $stockItem->method('calculateAvailable')->willReturn(Quantity::fromInt($availableQty));

        return $stockItem;
    }
}
