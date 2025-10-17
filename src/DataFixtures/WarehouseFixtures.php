<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Inventory\Application\Command\CreateWarehouse\CreateWarehouse;
use App\Inventory\Domain\Model\WarehouseCode;
use App\Inventory\Domain\Model\WarehouseId;
use App\Inventory\Domain\Model\WarehouseName;
use App\Shared\Domain\ValueObject\Address;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Warehouse fixtures - creates multiple warehouses for inventory management
 */
class WarehouseFixtures extends Fixture implements DependentFixtureInterface
{
    // Reference constants for StockItemFixtures
    public const WAREHOUSE_MAIN = 'warehouse_main';
    public const WAREHOUSE_EAST = 'warehouse_east';
    public const WAREHOUSE_WEST = 'warehouse_west';
    public const WAREHOUSE_MIDWEST = 'warehouse_midwest';
    public const WAREHOUSE_SOUTH = 'warehouse_south';

    public function __construct(
        private readonly MessageBusInterface $commandBus
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        echo "🏭 Creating warehouses...\n";

        // Get first tenant ID from database
        $tenantIdString = $this->getFirstTenantId($manager);
        $tenantId = TenantId::fromString($tenantIdString);

        // Warehouse 1: Main Distribution Center (highest priority)
        $this->createWarehouse(
            $tenantId,
            'MAIN01',
            'Main Distribution Center',
            '123 Industrial Parkway',
            'Los Angeles',
            'CA',
            '90001',
            'USA',
            100
        );
        echo "   ✓ Main Distribution Center created\n";

        // Warehouse 2: East Coast Warehouse
        $this->createWarehouse(
            $tenantId,
            'EAST01',
            'East Coast Distribution Center',
            '456 Commerce Boulevard',
            'New York',
            'NY',
            '10001',
            'USA',
            90
        );
        echo "   ✓ East Coast Distribution Center created\n";

        // Warehouse 3: West Coast Warehouse
        $this->createWarehouse(
            $tenantId,
            'WEST01',
            'West Coast Distribution Center',
            '789 Pacific Highway',
            'Seattle',
            'WA',
            '98101',
            'USA',
            85
        );
        echo "   ✓ West Coast Distribution Center created\n";

        // Warehouse 4: Midwest Warehouse
        $this->createWarehouse(
            $tenantId,
            'MW01',
            'Midwest Regional Warehouse',
            '321 Supply Chain Drive',
            'Chicago',
            'IL',
            '60601',
            'USA',
            80
        );
        echo "   ✓ Midwest Regional Warehouse created\n";

        // Warehouse 5: South Warehouse
        $this->createWarehouse(
            $tenantId,
            'SOUTH01',
            'Southern Distribution Center',
            '654 Logistics Way',
            'Houston',
            'TX',
            '77001',
            'USA',
            75
        );
        echo "   ✓ Southern Distribution Center created\n";

        $manager->flush();
        echo "✅ All warehouses created (5 total)\n";
    }

    private function createWarehouse(
        TenantId $tenantId,
        string $code,
        string $name,
        string $street,
        string $city,
        string $state,
        string $postalCode,
        string $country,
        int $priority
    ): void {
        $warehouseId = WarehouseId::generate();

        $command = new CreateWarehouse(
            id: $warehouseId,
            tenantId: $tenantId,
            code: WarehouseCode::fromString($code),
            name: WarehouseName::fromString($name),
            address: Address::create(
                street: $street,
                city: $city,
                state: $state,
                postalCode: $postalCode,
                country: $country
            ),
            priority: $priority
        );

        $this->commandBus->dispatch($command);
    }

    private function getFirstTenantId(ObjectManager $manager): string
    {
        $connection = $manager->getConnection();
        $result = $connection->executeQuery('SELECT id FROM tenants ORDER BY created_at ASC LIMIT 1')->fetchOne();

        return $result;
    }

    public function getDependencies(): array
    {
        return [
            TenantFixtures::class,
        ];
    }

    public function getOrder(): int
    {
        return 6;
    }
}

