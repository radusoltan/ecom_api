<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Catalog\Domain\Model\ProductId;
use App\Inventory\Application\Command\CreateStockItem\CreateStockItemCommand;
use App\Inventory\Application\Command\CreateWarehouse\CreateWarehouse;
use App\Inventory\Domain\Model\Quantity;
use App\Inventory\Domain\Model\StockItemId;
use App\Inventory\Domain\Model\WarehouseCode;
use App\Inventory\Domain\Model\WarehouseId;
use App\Inventory\Domain\Model\WarehouseName;
use App\Shared\Domain\ValueObject\Address;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Ulid;

/**
 * Inventory fixtures - creates warehouses and stock items
 * Depends on: TenantFixtures, CatalogFixtures.
 */
class InventoryFixtures extends Fixture
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly Connection $connection,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        echo "📦 Creating inventory...\n";

        // Get tenant ID from database (first tenant)
        $tenantIdString = $this->connection->fetchOne(
            'SELECT id FROM tenants ORDER BY created_at LIMIT 1'
        );

        if (!$tenantIdString) {
            echo "   ⚠️  No tenants found. Skipping inventory fixtures.\n";

            return;
        }

        $tenantId = TenantId::fromString($tenantIdString);

        // Create warehouses
        $warehouses = $this->createWarehouses($tenantId);

        // Get all products for this tenant
        $products = $this->connection->fetchAllAssociative(
            'SELECT id FROM catalog_products WHERE tenant_id = :tenant_id ORDER BY created_at',
            ['tenant_id' => $tenantId->toString()]
        );

        if (empty($products)) {
            echo "   ⚠️  No products found. Skipping stock items.\n";

            return;
        }

        echo '   Creating stock items for '.count($products)." products...\n";

        // Create stock items for each product
        $stockItemsCreated = 0;
        foreach ($products as $product) {
            $productId = ProductId::fromString($product['id']);

            // Distribute products across warehouses (80% in main, 20% in secondary)
            $mainWarehouseId = $warehouses[0]['id'];
            $secondaryWarehouseId = $warehouses[1]['id'];

            // Main warehouse - stock between 50-200 units
            $mainStockQuantity = random_int(50, 200);
            $this->createStockItem(
                $tenantId,
                $productId,
                $mainWarehouseId,
                $mainStockQuantity
            );
            ++$stockItemsCreated;

            // 30% chance to also have stock in secondary warehouse
            if (random_int(1, 100) <= 30) {
                $secondaryStockQuantity = random_int(10, 50);
                $this->createStockItem(
                    $tenantId,
                    $productId,
                    $secondaryWarehouseId,
                    $secondaryStockQuantity
                );
                ++$stockItemsCreated;
            }
        }

        echo "   ✓ Created $stockItemsCreated stock items\n";
        echo "✅ Inventory created successfully\n";
    }

    private function createWarehouses(TenantId $tenantId): array
    {
        $warehouses = [];

        // Main Warehouse
        $mainWarehouseId = WarehouseId::fromString((string) new Ulid());
        $command1 = new CreateWarehouse(
            id: $mainWarehouseId,
            tenantId: $tenantId,
            code: WarehouseCode::fromString('MAIN'),
            name: WarehouseName::fromString('Main Warehouse'),
            address: Address::create(
                street: '123 Commerce Street',
                city: 'New York',
                state: 'NY',
                postalCode: '10001',
                country: 'US'
            ),
            priority: 10, // Higher priority = processed first
        );
        $this->commandBus->dispatch($command1);
        $warehouses[] = ['id' => $mainWarehouseId, 'name' => 'Main Warehouse'];
        echo "   ✓ Main Warehouse created\n";

        // Secondary Warehouse
        $secondaryWarehouseId = WarehouseId::fromString((string) new Ulid());
        $command2 = new CreateWarehouse(
            id: $secondaryWarehouseId,
            tenantId: $tenantId,
            code: WarehouseCode::fromString('SEC'),
            name: WarehouseName::fromString('Secondary Warehouse'),
            address: Address::create(
                street: '456 Industrial Blvd',
                city: 'Los Angeles',
                state: 'CA',
                postalCode: '90001',
                country: 'US'
            ),
            priority: 20, // Lower priority = backup warehouse
        );
        $this->commandBus->dispatch($command2);
        $warehouses[] = ['id' => $secondaryWarehouseId, 'name' => 'Secondary Warehouse'];
        echo "   ✓ Secondary Warehouse created\n";

        return $warehouses;
    }

    private function createStockItem(
        TenantId $tenantId,
        ProductId $productId,
        WarehouseId $warehouseId,
        int $quantity
    ): void {
        $stockItemId = StockItemId::fromString((string) new Ulid());

        $command = new CreateStockItemCommand(
            stockItemId: $stockItemId,
            productId: $productId,
            warehouseId: $warehouseId,
            initialQuantity: Quantity::fromInt($quantity),
            lowStockThreshold: Quantity::fromInt(10),
            tenantId: $tenantId,
        );

        $this->commandBus->dispatch($command);
    }

    public function getOrder(): int
    {
        // Run after TenantFixtures (1) and CatalogFixtures (2)
        return 3;
    }
}
