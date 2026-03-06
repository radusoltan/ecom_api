<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Application\Service;

use App\Catalog\Domain\Model\ProductId;
use App\Inventory\Application\Service\WarehouseRoutingService;
use App\Inventory\Domain\Model\Quantity;
use App\Inventory\Domain\Model\StockItem;
use App\Inventory\Domain\Model\StockItemId;
use App\Inventory\Domain\Model\Warehouse;
use App\Inventory\Domain\Model\WarehouseCode;
use App\Inventory\Domain\Model\WarehouseId;
use App\Inventory\Domain\Model\WarehouseName;
use App\Inventory\Domain\Repository\StockItemRepositoryInterface;
use App\Inventory\Domain\Repository\WarehouseRepositoryInterface;
use App\Shared\Domain\ValueObject\Address;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(WarehouseRoutingService::class)]
final class WarehouseRoutingServiceTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    private WarehouseRepositoryInterface $warehouseRepository;
    private StockItemRepositoryInterface $stockItemRepository;
    private WarehouseRoutingService $service;
    private TenantId $tenantId;
    private Address $shippingAddress;

    protected function setUp(): void
    {
        parent::setUp();

        $this->warehouseRepository = $this->createMock(WarehouseRepositoryInterface::class);
        $this->stockItemRepository = $this->createMock(StockItemRepositoryInterface::class);

        $this->service = new WarehouseRoutingService(
            $this->warehouseRepository,
            $this->stockItemRepository,
        );

        $this->tenantId = TenantId::fromString(self::TENANT_ID);

        $this->shippingAddress = Address::create(
            street: '123 Main St',
            city: 'New York',
            state: 'NY',
            postalCode: '10001',
            country: 'US',
        );
    }

    // -----------------------------------------------------------------------
    // selectWarehouse() — happy paths
    // -----------------------------------------------------------------------

    #[Test]
    public function itSelectsTheOnlyWarehouseWhenItHasSufficientStock(): void
    {
        $productId = ProductId::generate();
        $quantity = Quantity::fromInt(5);

        $warehouse = $this->makeWarehouse(priority: 5);
        $stockItem = $this->makeStockItem($productId, $warehouse->id(), onHand: 20);

        $this->warehouseRepository
            ->expects(self::once())
            ->method('findActiveByTenant')
            ->with($this->tenantId)
            ->willReturn([$warehouse]);

        $this->stockItemRepository
            ->expects(self::once())
            ->method('findByProductAndWarehouse')
            ->willReturn($stockItem);

        $result = $this->service->selectWarehouse($productId, $quantity, $this->shippingAddress, $this->tenantId);

        self::assertNotNull($result);
        self::assertTrue($warehouse->id()->equals($result));
    }

    #[Test]
    public function itSelectsHigherPriorityWarehouseWhenBothHaveSufficientStock(): void
    {
        $productId = ProductId::generate();
        $quantity = Quantity::fromInt(3);

        $lowPriority = $this->makeWarehouse(priority: 2);
        $highPriority = $this->makeWarehouse(priority: 8);

        $stockLow = $this->makeStockItem($productId, $lowPriority->id(), onHand: 50);
        $stockHigh = $this->makeStockItem($productId, $highPriority->id(), onHand: 50);

        $this->warehouseRepository
            ->expects(self::once())
            ->method('findActiveByTenant')
            ->willReturn([$lowPriority, $highPriority]);

        $this->stockItemRepository
            ->expects(self::exactly(2))
            ->method('findByProductAndWarehouse')
            ->willReturnCallback(
                function (ProductId $pid, WarehouseId $wid, TenantId $tid) use (
                    $lowPriority,
                    $stockLow,
                    $stockHigh,
                ): StockItem {
                    if ($wid->equals($lowPriority->id())) {
                        return $stockLow;
                    }

                    return $stockHigh;
                }
            );

        $result = $this->service->selectWarehouse($productId, $quantity, $this->shippingAddress, $this->tenantId);

        self::assertNotNull($result);
        self::assertTrue($highPriority->id()->equals($result));
    }

    #[Test]
    public function itBreaksPriorityTiesByAvailableStockDescending(): void
    {
        $productId = ProductId::generate();
        $quantity = Quantity::fromInt(1);

        $warehouseA = $this->makeWarehouse(priority: 5);
        $warehouseB = $this->makeWarehouse(priority: 5);

        // warehouseB has more available stock
        $stockA = $this->makeStockItem($productId, $warehouseA->id(), onHand: 10);
        $stockB = $this->makeStockItem($productId, $warehouseB->id(), onHand: 30);

        $this->warehouseRepository
            ->expects(self::once())
            ->method('findActiveByTenant')
            ->willReturn([$warehouseA, $warehouseB]);

        $this->stockItemRepository
            ->expects(self::exactly(2))
            ->method('findByProductAndWarehouse')
            ->willReturnCallback(
                function (ProductId $pid, WarehouseId $wid, TenantId $tid) use (
                    $warehouseA,
                    $stockA,
                    $stockB,
                ): StockItem {
                    if ($wid->equals($warehouseA->id())) {
                        return $stockA;
                    }

                    return $stockB;
                }
            );

        $result = $this->service->selectWarehouse($productId, $quantity, $this->shippingAddress, $this->tenantId);

        self::assertNotNull($result);
        // warehouseB has more stock so it wins the tie-break
        self::assertTrue($warehouseB->id()->equals($result));
    }

    // -----------------------------------------------------------------------
    // selectWarehouse() — no stock / no warehouses
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsNullWhenNoActiveWarehousesExist(): void
    {
        $this->warehouseRepository
            ->expects(self::once())
            ->method('findActiveByTenant')
            ->willReturn([]);

        $result = $this->service->selectWarehouse(
            ProductId::generate(),
            Quantity::fromInt(1),
            $this->shippingAddress,
            $this->tenantId,
        );

        self::assertNull($result);
    }

    #[Test]
    public function itReturnsNullWhenNoWarehouseHasStock(): void
    {
        $productId = ProductId::generate();
        $quantity = Quantity::fromInt(10);

        $warehouse = $this->makeWarehouse(priority: 5);

        $this->warehouseRepository
            ->expects(self::once())
            ->method('findActiveByTenant')
            ->willReturn([$warehouse]);

        $this->stockItemRepository
            ->expects(self::once())
            ->method('findByProductAndWarehouse')
            ->willReturn(null);

        $result = $this->service->selectWarehouse($productId, $quantity, $this->shippingAddress, $this->tenantId);

        self::assertNull($result);
    }

    #[Test]
    public function itReturnsNullWhenStockIsInsufficientInAllWarehouses(): void
    {
        $productId = ProductId::generate();
        $quantity = Quantity::fromInt(100);

        $warehouse = $this->makeWarehouse(priority: 5);
        // Only 10 on hand, need 100
        $stockItem = $this->makeStockItem($productId, $warehouse->id(), onHand: 10);

        $this->warehouseRepository
            ->expects(self::once())
            ->method('findActiveByTenant')
            ->willReturn([$warehouse]);

        $this->stockItemRepository
            ->expects(self::once())
            ->method('findByProductAndWarehouse')
            ->willReturn($stockItem);

        $result = $this->service->selectWarehouse($productId, $quantity, $this->shippingAddress, $this->tenantId);

        self::assertNull($result);
    }

    #[Test]
    public function itSkipsWarehouseWithStockItemNullAndPicksNextOneWithStock(): void
    {
        $productId = ProductId::generate();
        $quantity = Quantity::fromInt(5);

        $noStockWarehouse = $this->makeWarehouse(priority: 10); // higher priority but no stock item
        $withStockWarehouse = $this->makeWarehouse(priority: 3);
        $stockItem = $this->makeStockItem($productId, $withStockWarehouse->id(), onHand: 20);

        $this->warehouseRepository
            ->expects(self::once())
            ->method('findActiveByTenant')
            ->willReturn([$noStockWarehouse, $withStockWarehouse]);

        $this->stockItemRepository
            ->expects(self::exactly(2))
            ->method('findByProductAndWarehouse')
            ->willReturnCallback(
                function (ProductId $pid, WarehouseId $wid, TenantId $tid) use (
                    $noStockWarehouse,
                    $stockItem,
                ): ?StockItem {
                    if ($wid->equals($noStockWarehouse->id())) {
                        return null;
                    }

                    return $stockItem;
                }
            );

        $result = $this->service->selectWarehouse($productId, $quantity, $this->shippingAddress, $this->tenantId);

        self::assertNotNull($result);
        self::assertTrue($withStockWarehouse->id()->equals($result));
    }

    // -----------------------------------------------------------------------
    // selectWarehouse() — quantity validation
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsDomainExceptionWhenQuantityIsZero(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Quantity must be positive');

        $this->service->selectWarehouse(
            ProductId::generate(),
            Quantity::fromInt(0),
            $this->shippingAddress,
            $this->tenantId,
        );
    }

    // -----------------------------------------------------------------------
    // getAvailableWarehouses()
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsAllWarehousesWithSufficientStock(): void
    {
        $productId = ProductId::generate();
        $quantity = Quantity::fromInt(5);

        $warehouseA = $this->makeWarehouse(priority: 3);
        $warehouseB = $this->makeWarehouse(priority: 7);

        $stockA = $this->makeStockItem($productId, $warehouseA->id(), onHand: 10);
        $stockB = $this->makeStockItem($productId, $warehouseB->id(), onHand: 20);

        $this->warehouseRepository
            ->expects(self::once())
            ->method('findActiveByTenant')
            ->willReturn([$warehouseA, $warehouseB]);

        $this->stockItemRepository
            ->expects(self::exactly(2))
            ->method('findByProductAndWarehouse')
            ->willReturnCallback(
                function (ProductId $pid, WarehouseId $wid, TenantId $tid) use (
                    $warehouseA,
                    $stockA,
                    $stockB,
                ): StockItem {
                    if ($wid->equals($warehouseA->id())) {
                        return $stockA;
                    }

                    return $stockB;
                }
            );

        $result = $this->service->getAvailableWarehouses($productId, $quantity, $this->tenantId);

        self::assertCount(2, $result);
        // Sorted by priority descending: warehouseB (7) first
        self::assertTrue($warehouseB->id()->equals($result[0]['warehouseId']));
        self::assertSame(7, $result[0]['priority']);
        self::assertSame(20, $result[0]['availableStock']);
        self::assertTrue($warehouseA->id()->equals($result[1]['warehouseId']));
        self::assertSame(3, $result[1]['priority']);
    }

    #[Test]
    public function itReturnsEmptyArrayWhenNoWarehousesHaveSufficientStock(): void
    {
        $productId = ProductId::generate();
        $quantity = Quantity::fromInt(999);

        $warehouse = $this->makeWarehouse(priority: 5);
        $stockItem = $this->makeStockItem($productId, $warehouse->id(), onHand: 1);

        $this->warehouseRepository
            ->expects(self::once())
            ->method('findActiveByTenant')
            ->willReturn([$warehouse]);

        $this->stockItemRepository
            ->expects(self::once())
            ->method('findByProductAndWarehouse')
            ->willReturn($stockItem);

        $result = $this->service->getAvailableWarehouses($productId, $quantity, $this->tenantId);

        self::assertSame([], $result);
    }

    #[Test]
    public function itReturnsCorrectStructureForEachAvailableWarehouse(): void
    {
        $productId = ProductId::generate();
        $quantity = Quantity::fromInt(1);

        $warehouse = $this->makeWarehouse(priority: 6);
        $stockItem = $this->makeStockItem($productId, $warehouse->id(), onHand: 15);

        $this->warehouseRepository
            ->expects(self::once())
            ->method('findActiveByTenant')
            ->willReturn([$warehouse]);

        $this->stockItemRepository
            ->expects(self::once())
            ->method('findByProductAndWarehouse')
            ->willReturn($stockItem);

        $result = $this->service->getAvailableWarehouses($productId, $quantity, $this->tenantId);

        self::assertCount(1, $result);
        self::assertArrayHasKey('warehouseId', $result[0]);
        self::assertArrayHasKey('priority', $result[0]);
        self::assertArrayHasKey('availableStock', $result[0]);
        self::assertInstanceOf(WarehouseId::class, $result[0]['warehouseId']);
        self::assertSame(6, $result[0]['priority']);
        self::assertSame(15, $result[0]['availableStock']);
    }

    // -----------------------------------------------------------------------
    // canFulfill()
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsTrueWhenAtLeastOneWarehouseHasSufficientStock(): void
    {
        $productId = ProductId::generate();
        $quantity = Quantity::fromInt(5);

        $warehouse = $this->makeWarehouse(priority: 5);
        $stockItem = $this->makeStockItem($productId, $warehouse->id(), onHand: 10);

        $this->warehouseRepository
            ->expects(self::once())
            ->method('findActiveByTenant')
            ->willReturn([$warehouse]);

        $this->stockItemRepository
            ->expects(self::once())
            ->method('findByProductAndWarehouse')
            ->willReturn($stockItem);

        $result = $this->service->canFulfill($productId, $quantity, $this->tenantId);

        self::assertTrue($result);
    }

    #[Test]
    public function itReturnsFalseWhenNoWarehouseCanFulfill(): void
    {
        $productId = ProductId::generate();
        $quantity = Quantity::fromInt(100);

        $warehouse = $this->makeWarehouse(priority: 5);
        $stockItem = $this->makeStockItem($productId, $warehouse->id(), onHand: 1);

        $this->warehouseRepository
            ->expects(self::once())
            ->method('findActiveByTenant')
            ->willReturn([$warehouse]);

        $this->stockItemRepository
            ->expects(self::once())
            ->method('findByProductAndWarehouse')
            ->willReturn($stockItem);

        $result = $this->service->canFulfill($productId, $quantity, $this->tenantId);

        self::assertFalse($result);
    }

    #[Test]
    public function itReturnsFalseWhenNoActiveWarehousesExistForCanFulfill(): void
    {
        $this->warehouseRepository
            ->expects(self::once())
            ->method('findActiveByTenant')
            ->willReturn([]);

        $result = $this->service->canFulfill(
            ProductId::generate(),
            Quantity::fromInt(1),
            $this->tenantId,
        );

        self::assertFalse($result);
    }

    #[Test]
    public function itReturnsFalseWhenStockItemIsNullForCanFulfill(): void
    {
        $productId = ProductId::generate();

        $warehouse = $this->makeWarehouse(priority: 5);

        $this->warehouseRepository
            ->expects(self::once())
            ->method('findActiveByTenant')
            ->willReturn([$warehouse]);

        $this->stockItemRepository
            ->expects(self::once())
            ->method('findByProductAndWarehouse')
            ->willReturn(null);

        $result = $this->service->canFulfill($productId, Quantity::fromInt(1), $this->tenantId);

        self::assertFalse($result);
    }

    #[Test]
    public function itReturnsTrueEarlyWhenFirstWarehouseCanFulfill(): void
    {
        $productId = ProductId::generate();
        $quantity = Quantity::fromInt(2);

        $warehouseA = $this->makeWarehouse(priority: 9);
        $warehouseB = $this->makeWarehouse(priority: 1);

        $stockA = $this->makeStockItem($productId, $warehouseA->id(), onHand: 50);

        $this->warehouseRepository
            ->expects(self::once())
            ->method('findActiveByTenant')
            ->willReturn([$warehouseA, $warehouseB]);

        // canFulfill should stop at warehouseA and NOT query warehouseB
        $this->stockItemRepository
            ->expects(self::once())
            ->method('findByProductAndWarehouse')
            ->willReturn($stockA);

        $result = $this->service->canFulfill($productId, $quantity, $this->tenantId);

        self::assertTrue($result);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeWarehouse(int $priority): Warehouse
    {
        return Warehouse::create(
            id: WarehouseId::generate(),
            tenantId: $this->tenantId,
            code: WarehouseCode::fromString('WH'.random_int(100, 999)),
            name: WarehouseName::fromString('Warehouse '.random_int(1, 9999)),
            address: $this->shippingAddress,
            priority: $priority,
        );
    }

    private function makeStockItem(
        ProductId $productId,
        WarehouseId $warehouseId,
        int $onHand,
    ): StockItem {
        return StockItem::create(
            id: StockItemId::generate(),
            tenantId: $this->tenantId,
            productId: $productId,
            warehouseId: $warehouseId,
            initialQuantity: Quantity::fromInt($onHand),
        );
    }
}
