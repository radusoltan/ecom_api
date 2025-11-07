<?php

declare(strict_types=1);

namespace App\Tests\Integration\Inventory\Infrastructure\Repository;

use App\Catalog\Domain\Model\ProductId;
use App\Inventory\Domain\Model\Quantity;
use App\Inventory\Domain\Model\StockItem;
use App\Inventory\Domain\Model\StockItemId;
use App\Inventory\Domain\Model\WarehouseId;
use App\Inventory\Domain\Repository\StockItemRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tests\Support\TenantTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class StockItemRepositoryTest extends KernelTestCase
{
    use TenantTestTrait;

    private StockItemRepositoryInterface $repository;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = static::getContainer();
        $this->repository = $container->get(StockItemRepositoryInterface::class);

        // Use default test tenant ID
        $this->tenantId = $this->getDefaultTenantId();

        // Set tenant context for RLS (Row-Level Security)
        $this->setTenantContext($this->tenantId->toString());

        // Clean up existing test data to avoid pollution between tests
        $this->cleanupTestData();
    }

    protected function tearDown(): void
    {
        // Clean up after each test
        $this->cleanupTestData();

        parent::tearDown();
    }

    private function cleanupTestData(): void
    {
        $em = $this->getEntityManager();
        $connection = $em->getConnection();

        // Delete all stock items for the test tenant
        $connection->executeStatement(
            sprintf(
                "DELETE FROM stock_items WHERE tenant_id = '%s'",
                $this->tenantId->toString()
            )
        );
    }

    public function testSaveAndFindById(): void
    {
        $stockItem = $this->createStockItem();

        $this->repository->save($stockItem);

        $foundStockItem = $this->repository->findById($stockItem->id());

        $this->assertNotNull($foundStockItem);
        $this->assertTrue($foundStockItem->id()->equals($stockItem->id()));
        $this->assertTrue($foundStockItem->tenantId()->equals($stockItem->tenantId()));
        $this->assertTrue($foundStockItem->productId()->equals($stockItem->productId()));
        $this->assertTrue($foundStockItem->warehouseId()->equals($stockItem->warehouseId()));
        $this->assertEquals($stockItem->onHand()->value(), $foundStockItem->onHand()->value());
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $nonExistentId = StockItemId::generate();

        $result = $this->repository->findById($nonExistentId);

        $this->assertNull($result);
    }

    public function testSaveUpdatesExistingStockItem(): void
    {
        $stockItem = $this->createStockItem(100);
        $this->repository->save($stockItem);

        // Modify the stock item
        $stockItem->reserve(Quantity::fromInt(20), 'reservation-1');
        $this->repository->save($stockItem);

        // Retrieve and verify
        $foundStockItem = $this->repository->findById($stockItem->id());

        $this->assertNotNull($foundStockItem);
        $this->assertEquals(20, $foundStockItem->reserved()->value());
        $this->assertEquals(80, $foundStockItem->calculateAvailable()->value());
    }

    public function testFindByProductAndWarehouse(): void
    {
        $productId = ProductId::generate();
        $warehouseId = WarehouseId::generate();

        $stockItem = StockItem::create(
            StockItemId::generate(),
            $this->tenantId,
            $productId,
            $warehouseId,
            Quantity::fromInt(100)
        );

        $this->repository->save($stockItem);

        $foundStockItem = $this->repository->findByProductAndWarehouse(
            $productId,
            $warehouseId,
            $this->tenantId
        );

        $this->assertNotNull($foundStockItem);
        $this->assertTrue($foundStockItem->productId()->equals($productId));
        $this->assertTrue($foundStockItem->warehouseId()->equals($warehouseId));
    }

    public function testFindByProductAndWarehouseReturnsNullWhenNotFound(): void
    {
        $result = $this->repository->findByProductAndWarehouse(
            ProductId::generate(),
            WarehouseId::generate(),
            $this->tenantId
        );

        $this->assertNull($result);
    }

    public function testFindByProduct(): void
    {
        $productId = ProductId::generate();
        $warehouse1 = WarehouseId::generate();
        $warehouse2 = WarehouseId::generate();

        // Create stock items for same product in different warehouses
        $stockItem1 = StockItem::create(
            StockItemId::generate(),
            $this->tenantId,
            $productId,
            $warehouse1,
            Quantity::fromInt(100)
        );

        $stockItem2 = StockItem::create(
            StockItemId::generate(),
            $this->tenantId,
            $productId,
            $warehouse2,
            Quantity::fromInt(50)
        );

        $this->repository->save($stockItem1);
        $this->repository->save($stockItem2);

        $foundStockItems = $this->repository->findByProduct($productId, $this->tenantId);

        $this->assertCount(2, $foundStockItems);
        $this->assertTrue($foundStockItems[0]->productId()->equals($productId));
        $this->assertTrue($foundStockItems[1]->productId()->equals($productId));
    }

    public function testFindByWarehouse(): void
    {
        $warehouseId = WarehouseId::generate();
        $product1 = ProductId::generate();
        $product2 = ProductId::generate();

        // Create stock items for different products in same warehouse
        $stockItem1 = StockItem::create(
            StockItemId::generate(),
            $this->tenantId,
            $product1,
            $warehouseId,
            Quantity::fromInt(100)
        );

        $stockItem2 = StockItem::create(
            StockItemId::generate(),
            $this->tenantId,
            $product2,
            $warehouseId,
            Quantity::fromInt(75)
        );

        $this->repository->save($stockItem1);
        $this->repository->save($stockItem2);

        $foundStockItems = $this->repository->findByWarehouse($warehouseId, $this->tenantId);

        $this->assertCount(2, $foundStockItems);
        $this->assertTrue($foundStockItems[0]->warehouseId()->equals($warehouseId));
        $this->assertTrue($foundStockItems[1]->warehouseId()->equals($warehouseId));
    }

    public function testFindLowStockItems(): void
    {
        // Create stock item with low stock
        $lowStockItem = StockItem::create(
            StockItemId::generate(),
            $this->tenantId,
            ProductId::generate(),
            WarehouseId::generate(),
            Quantity::fromInt(15),
            Quantity::fromInt(20) // Low stock threshold
        );

        // Create stock item with sufficient stock
        $normalStockItem = StockItem::create(
            StockItemId::generate(),
            $this->tenantId,
            ProductId::generate(),
            WarehouseId::generate(),
            Quantity::fromInt(100),
            Quantity::fromInt(20)
        );

        $this->repository->save($lowStockItem);
        $this->repository->save($normalStockItem);

        $lowStockItems = $this->repository->findLowStockItems($this->tenantId);

        $this->assertCount(1, $lowStockItems);
        $this->assertTrue($lowStockItems[0]->id()->equals($lowStockItem->id()));
    }

    public function testFindLowStockItemsWithReservedAndAllocated(): void
    {
        // Create stock item that becomes low after reservations
        $stockItem = StockItem::create(
            StockItemId::generate(),
            $this->tenantId,
            ProductId::generate(),
            WarehouseId::generate(),
            Quantity::fromInt(100),
            Quantity::fromInt(20)
        );

        // Reserve and allocate to bring available below threshold
        $stockItem->reserve(Quantity::fromInt(40), 'reservation-1');
        $stockItem->allocate(Quantity::fromInt(45), 'order-1');
        // Available: 100 - 40 - 45 = 15 (below threshold of 20)

        $this->repository->save($stockItem);

        $lowStockItems = $this->repository->findLowStockItems($this->tenantId);

        $this->assertCount(1, $lowStockItems);
        $available = $lowStockItems[0]->calculateAvailable();
        $this->assertEquals(15, $available->value());
    }

    public function testFindByTenant(): void
    {
        // Use default tenant from RLS context
        $tenant1 = $this->tenantId;

        // Create stock items for tenant 1
        $stockItem1 = StockItem::create(
            StockItemId::generate(),
            $tenant1,
            ProductId::generate(),
            WarehouseId::generate(),
            Quantity::fromInt(100)
        );

        $stockItem2 = StockItem::create(
            StockItemId::generate(),
            $tenant1,
            ProductId::generate(),
            WarehouseId::generate(),
            Quantity::fromInt(50)
        );

        $this->repository->save($stockItem1);
        $this->repository->save($stockItem2);

        $tenant1Items = $this->repository->findByTenant($tenant1);

        $this->assertCount(2, $tenant1Items);
        $this->assertTrue($tenant1Items[0]->tenantId()->equals($tenant1));
        $this->assertTrue($tenant1Items[1]->tenantId()->equals($tenant1));
    }

    public function testDelete(): void
    {
        $stockItem = $this->createStockItem();
        $this->repository->save($stockItem);

        // Verify it exists
        $foundStockItem = $this->repository->findById($stockItem->id());
        $this->assertNotNull($foundStockItem);

        // Delete it
        $this->repository->delete($stockItem);

        // Verify it's gone
        $deletedStockItem = $this->repository->findById($stockItem->id());
        $this->assertNull($deletedStockItem);
    }

    public function testUniqueConstraintOnProductAndWarehouse(): void
    {
        $productId = ProductId::generate();
        $warehouseId = WarehouseId::generate();

        $stockItem1 = StockItem::create(
            StockItemId::generate(),
            $this->tenantId,
            $productId,
            $warehouseId,
            Quantity::fromInt(100)
        );

        $this->repository->save($stockItem1);

        // Try to create another stock item for same product/warehouse combination
        $stockItem2 = StockItem::create(
            StockItemId::generate(),
            $this->tenantId,
            $productId,
            $warehouseId,
            Quantity::fromInt(50)
        );

        $this->expectException(\Exception::class);
        $this->repository->save($stockItem2);
    }

    public function testDomainEventsAreDispatchedOnSave(): void
    {
        $stockItem = $this->createStockItem();
        $stockItem->reserve(Quantity::fromInt(20), 'reservation-1');

        // Events should be present before save
        $this->assertTrue($stockItem->hasEvents());

        $this->repository->save($stockItem);

        // Events should be cleared after save (popped and dispatched)
        $this->assertFalse($stockItem->hasEvents());
    }

    private function createStockItem(int $initialQuantity = 100): StockItem
    {
        return StockItem::create(
            StockItemId::generate(),
            $this->tenantId,
            ProductId::generate(),
            WarehouseId::generate(),
            Quantity::fromInt($initialQuantity)
        );
    }
}
