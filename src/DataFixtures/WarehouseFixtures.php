<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Inventory\Application\Command\CreateWarehouse\CreateWarehouse;
use App\Inventory\Domain\Model\WarehouseCode;
use App\Inventory\Domain\Model\WarehouseId;
use App\Inventory\Domain\Model\WarehouseName;
use App\Shared\Domain\ValueObject\Address;
use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Tenant\TenantContext;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Warehouse fixtures - creates multiple warehouses for inventory management.
 */
class WarehouseFixtures extends AbstractTenantAwareFixture implements DependentFixtureInterface, FixtureGroupInterface
{
    // Reference constants for StockItemFixtures
    public const WAREHOUSE_MAIN = 'warehouse_main';
    public const WAREHOUSE_EAST = 'warehouse_east';
    public const WAREHOUSE_WEST = 'warehouse_west';
    public const WAREHOUSE_MIDWEST = 'warehouse_midwest';
    public const WAREHOUSE_SOUTH = 'warehouse_south';

    public function __construct(
        EntityManagerInterface $entityManager,
        TenantContext $tenantContext,
        private readonly MessageBusInterface $commandBus,
    ) {
        parent::__construct($entityManager, $tenantContext);
    }

    public static function getGroups(): array
    {
        return ['sprint3', 'sprint3-inventory', 'sprint3-warehouses'];
    }

    public function load(ObjectManager $manager): void
    {
        echo "🏭 Creating tenant warehouses...\n";

        $tenantIds = $this->tenantIdsByOwnerEmail();

        foreach (Sprint3SeedData::tenants() as $tenant) {
            $tenantId = TenantId::fromString($tenantIds[$tenant['ownerEmail']]);
            $this->activateTenantContext($tenantId);

            foreach (Sprint3SeedData::warehouses($tenant['alias']) as $warehouse) {
                $this->createWarehouse(
                    tenantId: $tenantId,
                    code: $warehouse['code'],
                    name: $warehouse['name'],
                    street: $warehouse['street'],
                    city: $warehouse['city'],
                    state: $warehouse['state'],
                    postalCode: $warehouse['postalCode'],
                    country: $warehouse['country'],
                    priority: $warehouse['priority'],
                );
            }

            echo sprintf("   ✓ %s warehouses: %d\n", $tenant['name'], count(Sprint3SeedData::warehouses($tenant['alias'])));
        }

        $this->clearTenantContext();

        echo "✅ Warehouse network created\n";
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
        int $priority,
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

    public function getDependencies(): array
    {
        return [
            TenantFixtures::class,
        ];
    }
}
