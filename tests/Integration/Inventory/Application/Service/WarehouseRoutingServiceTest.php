<?php

declare(strict_types=1);

namespace App\Tests\Integration\Inventory\Application\Service;

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
use App\Tests\Support\TenantTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for WarehouseRoutingService
 * Tests real database interactions with proper RLS context
 */
final class WarehouseRoutingServiceTest extends KernelTestCase
{
    use TenantTestTrait;

    private WarehouseRoutingService $service;
    private WarehouseRepositoryInterface $warehouseRepository;
    private StockItemRepositoryInterface $stockItemRepository;
    private TenantId $tenantId;
    private ProductId $productId;
    private Address $shippingAddress;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        // Use default test tenant for RLS
        $this->tenantId = $this->getDefaultTenantId();
        $this->setTenantContext($this->tenantId->toString());

        // Get repositories from container
        $container = self::getContainer();
        $this->warehouseRepository = $container->get(WarehouseRepositoryInterface::class);
        $this->stockItemRepository = $container->get(StockItemRepositoryInterface::class);

        // Create service manually with repositories
        $this->service = new WarehouseRoutingService(
            $this->warehouseRepository,
            $this->stockItemRepository
        );

        $this->productId = ProductId::generate();
        $this->shippingAddress = Address::fromArray([
            'street' => '123 Test St',
            'city' => 'Test City',
            'state' => 'TC',
            'postalCode' => '12345',
            'country' => 'US',
        ]);
    }

    private function createWarehouse(int $priority, string $codePrefix = 'WH'): Warehouse
    {
        // Generate truly unique code using random suffix to avoid collisions across tests
        $uniqueCode = $codePrefix . substr(md5(uniqid((string)mt_rand(), true)), 0, 7 - strlen($codePrefix));

        $warehouse = Warehouse::create(
            WarehouseId::generate(),
            $this->tenantId,
            WarehouseCode::fromString(strtoupper($uniqueCode)),
            WarehouseName::fromString("Warehouse Priority {$priority}"),
            $this->shippingAddress,
            $priority
        );

        $this->warehouseRepository->save($warehouse);

        return $warehouse;
    }

    private function createStockItem(WarehouseId $warehouseId, int $quantity): StockItem
    {
        $stockItem = StockItem::create(
            StockItemId::generate(),
            $this->tenantId,
            $this->productId,
            $warehouseId,
            Quantity::fromInt($quantity),
            Quantity::fromInt(10)
        );

        $this->stockItemRepository->save($stockItem);

        return $stockItem;
    }

    public function testSelectWarehouseWithRealDatabaseReturnsHighestPriority(): void
    {
        // Create warehouses with different priorities
        $warehouse1 = $this->createWarehouse(3, 'LOW');
        $warehouse2 = $this->createWarehouse(8, 'HIGH');
        $warehouse3 = $this->createWarehouse(5, 'MED');

        // Add stock to all warehouses
        $this->createStockItem($warehouse1->id(), 100);
        $this->createStockItem($warehouse2->id(), 100);
        $this->createStockItem($warehouse3->id(), 100);

        // Select warehouse
        $selectedWarehouseId = $this->service->selectWarehouse(
            $this->productId,
            Quantity::fromInt(10),
            $this->shippingAddress,
            $this->tenantId
        );

        // Should select warehouse with priority 8 (highest)
        $this->assertNotNull($selectedWarehouseId);
        $this->assertTrue($selectedWarehouseId->equals($warehouse2->id()));
    }

    public function testSelectWarehouseSkipsWarehousesWithInsufficientStock(): void
    {
        // Create warehouses
        $warehouse1 = $this->createWarehouse(10, 'HP1'); // Highest priority but low stock
        $warehouse2 = $this->createWarehouse(5, 'MP1');  // Lower priority but sufficient stock

        // Add insufficient stock to high priority warehouse
        $this->createStockItem($warehouse1->id(), 5);
        // Add sufficient stock to lower priority warehouse
        $this->createStockItem($warehouse2->id(), 50);

        $selectedWarehouseId = $this->service->selectWarehouse(
            $this->productId,
            Quantity::fromInt(10),
            $this->shippingAddress,
            $this->tenantId
        );

        // Should select warehouse2 despite lower priority
        $this->assertNotNull($selectedWarehouseId);
        $this->assertTrue($selectedWarehouseId->equals($warehouse2->id()));
    }

    public function testSelectWarehouseReturnsNullWhenNoWarehouseHasStock(): void
    {
        // Create warehouses but don't add any stock
        $this->createWarehouse(8, 'HP2');
        $this->createWarehouse(5, 'MP2');

        $selectedWarehouseId = $this->service->selectWarehouse(
            $this->productId,
            Quantity::fromInt(10),
            $this->shippingAddress,
            $this->tenantId
        );

        $this->assertNull($selectedWarehouseId);
    }

    public function testSelectWarehouseOnlyConsidersActiveWarehouses(): void
    {
        // Create active and inactive warehouses
        $activeWarehouse = $this->createWarehouse(5, 'ACT');
        $inactiveWarehouse = $this->createWarehouse(10, 'INA'); // Higher priority

        // Add stock to both
        $this->createStockItem($activeWarehouse->id(), 100);
        $this->createStockItem($inactiveWarehouse->id(), 100);

        // Deactivate the high priority warehouse
        $inactiveWarehouse->deactivate();
        $this->warehouseRepository->save($inactiveWarehouse);

        $selectedWarehouseId = $this->service->selectWarehouse(
            $this->productId,
            Quantity::fromInt(10),
            $this->shippingAddress,
            $this->tenantId
        );

        // Should select active warehouse despite lower priority
        $this->assertNotNull($selectedWarehouseId);
        $this->assertTrue($selectedWarehouseId->equals($activeWarehouse->id()));
    }

    public function testGetAvailableWarehousesReturnsAllWithStockSortedByPriority(): void
    {
        // Create multiple warehouses with different priorities
        $warehouse1 = $this->createWarehouse(3, 'W1');
        $warehouse2 = $this->createWarehouse(8, 'W2');
        $warehouse3 = $this->createWarehouse(5, 'W3');
        $warehouse4 = $this->createWarehouse(10, 'W4');

        // Add stock to all
        $this->createStockItem($warehouse1->id(), 20);
        $this->createStockItem($warehouse2->id(), 50);
        $this->createStockItem($warehouse3->id(), 30);
        $this->createStockItem($warehouse4->id(), 40);

        $availableWarehouses = $this->service->getAvailableWarehouses(
            $this->productId,
            Quantity::fromInt(10),
            $this->tenantId
        );

        // Should return all 4 warehouses sorted by priority
        $this->assertCount(4, $availableWarehouses);

        // Verify sort order: 10, 8, 5, 3
        $this->assertTrue($availableWarehouses[0]['warehouseId']->equals($warehouse4->id()));
        $this->assertSame(10, $availableWarehouses[0]['priority']);

        $this->assertTrue($availableWarehouses[1]['warehouseId']->equals($warehouse2->id()));
        $this->assertSame(8, $availableWarehouses[1]['priority']);

        $this->assertTrue($availableWarehouses[2]['warehouseId']->equals($warehouse3->id()));
        $this->assertSame(5, $availableWarehouses[2]['priority']);

        $this->assertTrue($availableWarehouses[3]['warehouseId']->equals($warehouse1->id()));
        $this->assertSame(3, $availableWarehouses[3]['priority']);
    }

    public function testGetAvailableWarehousesExcludesWarehousesWithInsufficientStock(): void
    {
        // Create warehouses
        $warehouse1 = $this->createWarehouse(8, 'SUF');
        $warehouse2 = $this->createWarehouse(5, 'INS');

        // Add sufficient stock to warehouse1
        $this->createStockItem($warehouse1->id(), 100);
        // Add insufficient stock to warehouse2
        $this->createStockItem($warehouse2->id(), 5);

        $availableWarehouses = $this->service->getAvailableWarehouses(
            $this->productId,
            Quantity::fromInt(10),
            $this->tenantId
        );

        // Should only return warehouse1
        $this->assertCount(1, $availableWarehouses);
        $this->assertTrue($availableWarehouses[0]['warehouseId']->equals($warehouse1->id()));
    }

    public function testCanFulfillReturnsTrueWhenAtLeastOneWarehouseCanFulfill(): void
    {
        // Create warehouses with varying stock
        $warehouse1 = $this->createWarehouse(5, 'CF1');
        $warehouse2 = $this->createWarehouse(8, 'CF2');

        // Add insufficient stock to warehouse1
        $this->createStockItem($warehouse1->id(), 5);
        // Add sufficient stock to warehouse2
        $this->createStockItem($warehouse2->id(), 100);

        $canFulfill = $this->service->canFulfill(
            $this->productId,
            Quantity::fromInt(10),
            $this->tenantId
        );

        $this->assertTrue($canFulfill);
    }

    public function testCanFulfillReturnsFalseWhenNoWarehouseCanFulfill(): void
    {
        // Create warehouses with insufficient stock
        $warehouse1 = $this->createWarehouse(5, 'CF3');
        $warehouse2 = $this->createWarehouse(8, 'CF4');

        // Add insufficient stock to both
        $this->createStockItem($warehouse1->id(), 3);
        $this->createStockItem($warehouse2->id(), 7);

        $canFulfill = $this->service->canFulfill(
            $this->productId,
            Quantity::fromInt(10),
            $this->tenantId
        );

        $this->assertFalse($canFulfill);
    }

    public function testWarehouseSelectionConsidersReservedStock(): void
    {
        // Create warehouse with stock
        $warehouse = $this->createWarehouse(5, 'RES');
        $stockItem = $this->createStockItem($warehouse->id(), 50);

        // Reserve some stock
        $stockItem->reserve(Quantity::fromInt(35), 'cart-123');
        $this->stockItemRepository->save($stockItem);

        // Try to reserve more than available (50 total - 35 reserved = 15 available)
        $selectedWarehouseId = $this->service->selectWarehouse(
            $this->productId,
            Quantity::fromInt(20), // Need 20 but only 15 available
            $this->shippingAddress,
            $this->tenantId
        );

        // Should return null because available quantity is insufficient
        $this->assertNull($selectedWarehouseId);
    }

    public function testWarehouseSelectionWithSamePrioritySelectsHigherStock(): void
    {
        // Create warehouses with same priority but different stock
        $warehouse1 = $this->createWarehouse(5, 'SP1');
        $warehouse2 = $this->createWarehouse(5, 'SP2');

        // Add different stock quantities
        $this->createStockItem($warehouse1->id(), 30);
        $this->createStockItem($warehouse2->id(), 100); // More stock

        $selectedWarehouseId = $this->service->selectWarehouse(
            $this->productId,
            Quantity::fromInt(10),
            $this->shippingAddress,
            $this->tenantId
        );

        // Should select warehouse2 due to higher available stock
        $this->assertNotNull($selectedWarehouseId);
        $this->assertTrue($selectedWarehouseId->equals($warehouse2->id()));
    }

    public function testMultipleProductsCanBeRoutedToDifferentWarehouses(): void
    {
        $product1 = ProductId::generate();
        $product2 = ProductId::generate();

        // Create warehouses
        $warehouse1 = $this->createWarehouse(8, 'MP1');
        $warehouse2 = $this->createWarehouse(5, 'MP2');

        // Product1 has stock only in warehouse1
        $stockItem1 = StockItem::create(
            StockItemId::generate(),
            $this->tenantId,
            $product1,
            $warehouse1->id(),
            Quantity::fromInt(100),
            Quantity::fromInt(10)
        );
        $this->stockItemRepository->save($stockItem1);

        // Product2 has stock only in warehouse2
        $stockItem2 = StockItem::create(
            StockItemId::generate(),
            $this->tenantId,
            $product2,
            $warehouse2->id(),
            Quantity::fromInt(100),
            Quantity::fromInt(10)
        );
        $this->stockItemRepository->save($stockItem2);

        // Route product1
        $warehouse1Id = $this->service->selectWarehouse(
            $product1,
            Quantity::fromInt(10),
            $this->shippingAddress,
            $this->tenantId
        );

        // Route product2
        $warehouse2Id = $this->service->selectWarehouse(
            $product2,
            Quantity::fromInt(10),
            $this->shippingAddress,
            $this->tenantId
        );

        // Should route to different warehouses
        $this->assertNotNull($warehouse1Id);
        $this->assertNotNull($warehouse2Id);
        $this->assertTrue($warehouse1Id->equals($warehouse1->id()));
        $this->assertTrue($warehouse2Id->equals($warehouse2->id()));
    }

    public function testWarehouseSelectionIsTenantIsolated(): void
    {
        // Create warehouse for test tenant
        $warehouse = $this->createWarehouse(5, 'TEN');
        $this->createStockItem($warehouse->id(), 100);

        // Try to select warehouse for different tenant
        $otherTenantId = TenantId::generate();

        $selectedWarehouseId = $this->service->selectWarehouse(
            $this->productId,
            Quantity::fromInt(10),
            $this->shippingAddress,
            $otherTenantId
        );

        // Should return null because warehouse belongs to different tenant
        $this->assertNull($selectedWarehouseId);
    }
}
