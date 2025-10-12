<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Fixtures;

use App\Inventory\Application\Command\CreateWarehouse\CreateWarehouse;
use App\Inventory\Domain\Model\WarehouseCode;
use App\Inventory\Domain\Model\WarehouseId;
use App\Inventory\Domain\Model\WarehouseName;
use App\Shared\Domain\ValueObject\Address;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Warehouse fixtures for development and testing
 */
final class WarehouseFixtures extends Fixture
{
    public function __construct(
        private readonly MessageBusInterface $commandBus
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // Default tenant ID from environment
        $tenantId = TenantId::fromString('76b3bee8-20af-456c-ac19-e22903ce76a9');

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

        // Warehouse 3: Midwest Warehouse
        $this->createWarehouse(
            $tenantId,
            'MW01',
            'Midwest Regional Warehouse',
            '789 Supply Chain Drive',
            'Chicago',
            'IL',
            '60601',
            'USA',
            80
        );

        // Warehouse 4: Texas Distribution Center
        $this->createWarehouse(
            $tenantId,
            'TX01',
            'Texas Distribution Center',
            '321 Logistics Way',
            'Houston',
            'TX',
            '77001',
            'USA',
            70
        );

        // Warehouse 5: Pacific Northwest (lower priority)
        $this->createWarehouse(
            $tenantId,
            'PNW01',
            'Pacific Northwest Warehouse',
            '654 Shipping Lane',
            'Seattle',
            'WA',
            '98101',
            'USA',
            60
        );

        $manager->flush();
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
        $command = new CreateWarehouse(
            id: WarehouseId::generate(),
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
}
