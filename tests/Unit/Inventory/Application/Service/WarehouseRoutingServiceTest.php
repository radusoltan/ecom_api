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
use PHPUnit\Framework\TestCase;

final class WarehouseRoutingServiceTest extends TestCase
{
    private WarehouseRepositoryInterface $warehouseRepository;
    private StockItemRepositoryInterface $stockItemRepository;
    private WarehouseRoutingService $service;
    private TenantId $tenantId;
    private ProductId $productId;
    private Address $shippingAddress;

    protected function setUp(): void
    {
        $this->warehouseRepository = $this->createMock(WarehouseRepositoryInterface::class);
        $this->stockItemRepository = $this->createMock(StockItemRepositoryInterface::class);
        $this->service = new WarehouseRoutingService(
            $this->warehouseRepository,
            $this->stockItemRepository
        );
        $this->tenantId = TenantId::generate();
        $this->productId = ProductId::generate();
        $this->shippingAddress = Address::fromArray([
            'street' => '123 Main St',
            'city' => 'New York',
            'state' => 'NY',
            'postalCode' => '10001',
            'country' => 'US',
        ]);
    }

    private function createWarehouse(int $priority): Warehouse
    {
        return Warehouse::create(
            WarehouseId::generate(),
            $this->tenantId,
            WarehouseCode::fromString('WH' . str_pad((string) $priority, 3, '0', STR_PAD_LEFT)),
            WarehouseName::fromString("Warehouse Priority {$priority}"),
            $this->shippingAddress,
            $priority
        );
    }

    private function createStockItem(WarehouseId $warehouseId, int $quantity): StockItem
    {
        return StockItem::create(
            StockItemId::generate(),
            $this->tenantId,
            $this->productId,
            $warehouseId,
            Quantity::fromInt($quantity),
            Quantity::fromInt(10) // low stock threshold
        );
    }

    public function testSelectWarehouseReturnsHighestPriorityWarehouseWithStock(): void
    {
        $warehouse1 = $this->createWarehouse(5);
        $warehouse2 = $this->createWarehouse(8); // Highest priority
        $warehouse3 = $this->createWarehouse(3);

        $this->warehouseRepository
            ->method('findActiveByTenant')
            ->willReturn([$warehouse1, $warehouse2, $warehouse3]);

        // All warehouses have sufficient stock
        $stock1 = $this->createStockItem($warehouse1->id(), 100);
        $stock2 = $this->createStockItem($warehouse2->id(), 100);
        $stock3 = $this->createStockItem($warehouse3->id(), 100);

        $this->stockItemRepository
            ->method('findByProductAndWarehouse')
            ->willReturnMap([
                [$this->productId, $warehouse1->id(), $this->tenantId, $stock1],
                [$this->productId, $warehouse2->id(), $this->tenantId, $stock2],
                [$this->productId, $warehouse3->id(), $this->tenantId, $stock3],
            ]);

        $result = $this->service->selectWarehouse(
            $this->productId,
            Quantity::fromInt(10),
            $this->shippingAddress,
            $this->tenantId
        );

        $this->assertNotNull($result);
        $this->assertTrue($result->equals($warehouse2->id())); // Highest priority = 8
    }

    public function testSelectWarehouseReturnsNullWhenNoWarehousesActive(): void
    {
        $this->warehouseRepository
            ->method('findActiveByTenant')
            ->willReturn([]);

        $result = $this->service->selectWarehouse(
            $this->productId,
            Quantity::fromInt(10),
            $this->shippingAddress,
            $this->tenantId
        );

        $this->assertNull($result);
    }

    public function testSelectWarehouseReturnsNullWhenNoWarehouseHasStock(): void
    {
        $warehouse1 = $this->createWarehouse(5);
        $warehouse2 = $this->createWarehouse(8);

        $this->warehouseRepository
            ->method('findActiveByTenant')
            ->willReturn([$warehouse1, $warehouse2]);

        // No stock items exist
        $this->stockItemRepository
            ->method('findByProductAndWarehouse')
            ->willReturn(null);

        $result = $this->service->selectWarehouse(
            $this->productId,
            Quantity::fromInt(10),
            $this->shippingAddress,
            $this->tenantId
        );

        $this->assertNull($result);
    }

    public function testSelectWarehouseReturnsNullWhenNoWarehouseHasSufficientStock(): void
    {
        $warehouse1 = $this->createWarehouse(5);
        $warehouse2 = $this->createWarehouse(8);

        $this->warehouseRepository
            ->method('findActiveByTenant')
            ->willReturn([$warehouse1, $warehouse2]);

        // Stock exists but insufficient
        $stock1 = $this->createStockItem($warehouse1->id(), 5); // Need 10
        $stock2 = $this->createStockItem($warehouse2->id(), 7); // Need 10

        $this->stockItemRepository
            ->method('findByProductAndWarehouse')
            ->willReturnMap([
                [$this->productId, $warehouse1->id(), $this->tenantId, $stock1],
                [$this->productId, $warehouse2->id(), $this->tenantId, $stock2],
            ]);

        $result = $this->service->selectWarehouse(
            $this->productId,
            Quantity::fromInt(10),
            $this->shippingAddress,
            $this->tenantId
        );

        $this->assertNull($result);
    }

    public function testSelectWarehouseSkipsWarehousesWithInsufficientStock(): void
    {
        $warehouse1 = $this->createWarehouse(10); // Highest priority but no stock
        $warehouse2 = $this->createWarehouse(5);  // Lower priority but has stock

        $this->warehouseRepository
            ->method('findActiveByTenant')
            ->willReturn([$warehouse1, $warehouse2]);

        $stock1 = $this->createStockItem($warehouse1->id(), 5);  // Insufficient
        $stock2 = $this->createStockItem($warehouse2->id(), 20); // Sufficient

        $this->stockItemRepository
            ->method('findByProductAndWarehouse')
            ->willReturnMap([
                [$this->productId, $warehouse1->id(), $this->tenantId, $stock1],
                [$this->productId, $warehouse2->id(), $this->tenantId, $stock2],
            ]);

        $result = $this->service->selectWarehouse(
            $this->productId,
            Quantity::fromInt(10),
            $this->shippingAddress,
            $this->tenantId
        );

        $this->assertNotNull($result);
        $this->assertTrue($result->equals($warehouse2->id())); // Selected warehouse2 despite lower priority
    }

    public function testSelectWarehouseWithSamePrioritySelectsHigherStock(): void
    {
        $warehouse1 = $this->createWarehouse(5);
        $warehouse2 = $this->createWarehouse(5); // Same priority

        $this->warehouseRepository
            ->method('findActiveByTenant')
            ->willReturn([$warehouse1, $warehouse2]);

        $stock1 = $this->createStockItem($warehouse1->id(), 15);
        $stock2 = $this->createStockItem($warehouse2->id(), 50); // More stock

        $this->stockItemRepository
            ->method('findByProductAndWarehouse')
            ->willReturnMap([
                [$this->productId, $warehouse1->id(), $this->tenantId, $stock1],
                [$this->productId, $warehouse2->id(), $this->tenantId, $stock2],
            ]);

        $result = $this->service->selectWarehouse(
            $this->productId,
            Quantity::fromInt(10),
            $this->shippingAddress,
            $this->tenantId
        );

        $this->assertNotNull($result);
        $this->assertTrue($result->equals($warehouse2->id())); // Selected warehouse2 due to higher stock
    }

    public function testSelectWarehouseThrowsExceptionForZeroQuantity(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Quantity must be positive');

        $this->service->selectWarehouse(
            $this->productId,
            Quantity::fromInt(0),
            $this->shippingAddress,
            $this->tenantId
        );
    }

    public function testSelectWarehouseThrowsExceptionForNegativeQuantity(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Quantity cannot be negative');

        $this->service->selectWarehouse(
            $this->productId,
            Quantity::fromInt(-5),
            $this->shippingAddress,
            $this->tenantId
        );
    }

    public function testGetAvailableWarehousesReturnsAllWarehousesWithStock(): void
    {
        $warehouse1 = $this->createWarehouse(3);
        $warehouse2 = $this->createWarehouse(8);
        $warehouse3 = $this->createWarehouse(5);

        $this->warehouseRepository
            ->method('findActiveByTenant')
            ->willReturn([$warehouse1, $warehouse2, $warehouse3]);

        $stock1 = $this->createStockItem($warehouse1->id(), 20);
        $stock2 = $this->createStockItem($warehouse2->id(), 50);
        $stock3 = $this->createStockItem($warehouse3->id(), 30);

        $this->stockItemRepository
            ->method('findByProductAndWarehouse')
            ->willReturnMap([
                [$this->productId, $warehouse1->id(), $this->tenantId, $stock1],
                [$this->productId, $warehouse2->id(), $this->tenantId, $stock2],
                [$this->productId, $warehouse3->id(), $this->tenantId, $stock3],
            ]);

        $result = $this->service->getAvailableWarehouses(
            $this->productId,
            Quantity::fromInt(10),
            $this->tenantId
        );

        $this->assertCount(3, $result);
        // Should be sorted by priority descending: 8, 5, 3
        $this->assertTrue($result[0]['warehouseId']->equals($warehouse2->id()));
        $this->assertSame(8, $result[0]['priority']);
        $this->assertSame(50, $result[0]['availableStock']);

        $this->assertTrue($result[1]['warehouseId']->equals($warehouse3->id()));
        $this->assertSame(5, $result[1]['priority']);

        $this->assertTrue($result[2]['warehouseId']->equals($warehouse1->id()));
        $this->assertSame(3, $result[2]['priority']);
    }

    public function testGetAvailableWarehousesExcludesWarehousesWithoutStock(): void
    {
        $warehouse1 = $this->createWarehouse(8);
        $warehouse2 = $this->createWarehouse(5);

        $this->warehouseRepository
            ->method('findActiveByTenant')
            ->willReturn([$warehouse1, $warehouse2]);

        // Only warehouse2 has stock
        $stock2 = $this->createStockItem($warehouse2->id(), 30);

        $this->stockItemRepository
            ->method('findByProductAndWarehouse')
            ->willReturnMap([
                [$this->productId, $warehouse1->id(), $this->tenantId, null], // No stock
                [$this->productId, $warehouse2->id(), $this->tenantId, $stock2],
            ]);

        $result = $this->service->getAvailableWarehouses(
            $this->productId,
            Quantity::fromInt(10),
            $this->tenantId
        );

        $this->assertCount(1, $result);
        $this->assertTrue($result[0]['warehouseId']->equals($warehouse2->id()));
    }

    public function testCanFulfillReturnsTrueWhenWarehouseHasStock(): void
    {
        $warehouse = $this->createWarehouse(5);

        $this->warehouseRepository
            ->method('findActiveByTenant')
            ->willReturn([$warehouse]);

        $stock = $this->createStockItem($warehouse->id(), 50);

        $this->stockItemRepository
            ->method('findByProductAndWarehouse')
            ->with($this->productId, $warehouse->id(), $this->tenantId)
            ->willReturn($stock);

        $result = $this->service->canFulfill(
            $this->productId,
            Quantity::fromInt(10),
            $this->tenantId
        );

        $this->assertTrue($result);
    }

    public function testCanFulfillReturnsFalseWhenNoWarehouseHasStock(): void
    {
        $warehouse = $this->createWarehouse(5);

        $this->warehouseRepository
            ->method('findActiveByTenant')
            ->willReturn([$warehouse]);

        $this->stockItemRepository
            ->method('findByProductAndWarehouse')
            ->willReturn(null);

        $result = $this->service->canFulfill(
            $this->productId,
            Quantity::fromInt(10),
            $this->tenantId
        );

        $this->assertFalse($result);
    }

    public function testCanFulfillReturnsFalseWhenStockIsInsufficient(): void
    {
        $warehouse = $this->createWarehouse(5);

        $this->warehouseRepository
            ->method('findActiveByTenant')
            ->willReturn([$warehouse]);

        $stock = $this->createStockItem($warehouse->id(), 5); // Insufficient

        $this->stockItemRepository
            ->method('findByProductAndWarehouse')
            ->willReturn($stock);

        $result = $this->service->canFulfill(
            $this->productId,
            Quantity::fromInt(10),
            $this->tenantId
        );

        $this->assertFalse($result);
    }

    public function testCanFulfillReturnsTrueWhenAtLeastOneWarehouseCanFulfill(): void
    {
        $warehouse1 = $this->createWarehouse(5);
        $warehouse2 = $this->createWarehouse(8);

        $this->warehouseRepository
            ->method('findActiveByTenant')
            ->willReturn([$warehouse1, $warehouse2]);

        $stock1 = $this->createStockItem($warehouse1->id(), 5);  // Insufficient
        $stock2 = $this->createStockItem($warehouse2->id(), 50); // Sufficient

        $this->stockItemRepository
            ->method('findByProductAndWarehouse')
            ->willReturnMap([
                [$this->productId, $warehouse1->id(), $this->tenantId, $stock1],
                [$this->productId, $warehouse2->id(), $this->tenantId, $stock2],
            ]);

        $result = $this->service->canFulfill(
            $this->productId,
            Quantity::fromInt(10),
            $this->tenantId
        );

        $this->assertTrue($result);
    }
}
