<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Fixtures;

use App\Catalog\Domain\Model\ProductId;
use App\Inventory\Application\Command\CreateStockItem\CreateStockItemCommand;
use App\Inventory\Domain\Model\Quantity;
use App\Inventory\Domain\Model\StockItemId;
use App\Inventory\Domain\Model\WarehouseId;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Stock Item fixtures for development and testing
 *
 * Creates stock items for existing products across multiple warehouses
 */
final class StockItemFixtures extends Fixture
{
    public function __construct(
        private readonly MessageBusInterface $commandBus
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // Default tenant ID
        $tenantId = TenantId::fromString('76b3bee8-20af-456c-ac19-e22903ce76a9');

        // Existing product IDs from database
        $productIds = [
            'ff4e49b3-0b54-462e-adee-fa654b209c2d', // New Product Test EDITED
            'ef2872c7-39d9-4edd-af0a-a27613587301', // Smartphone Pro Max
            '9a5eeeb6-7238-4032-8000-bc939e7faae7', // Cotton T-Shirt Premium
        ];

        // Existing warehouse IDs from database
        $warehouseIds = [
            '01K7938D02VMST6SMYDEJAHTVP', // MAIN01 - Main Distribution Center
            '01K7938D31701SDMY94XQJ9R72', // EAST01 - East Coast Distribution Center
            '01K7938D382EQW6R9P65E3FRBG', // MW01 - Midwest Regional Warehouse
        ];

        // Create stock items for each product in multiple warehouses
        // Product 1: New Product Test EDITED
        $this->createStockItem(
            $tenantId,
            $productIds[0],
            $warehouseIds[0], // Main DC
            500,  // High stock
            50    // Low stock threshold
        );

        $this->createStockItem(
            $tenantId,
            $productIds[0],
            $warehouseIds[1], // East Coast
            300,  // Medium stock
            50
        );

        $this->createStockItem(
            $tenantId,
            $productIds[0],
            $warehouseIds[2], // Midwest
            45,   // Low stock (will trigger alert)
            50
        );

        // Product 2: Smartphone Pro Max
        $this->createStockItem(
            $tenantId,
            $productIds[1],
            $warehouseIds[0], // Main DC
            250,  // Good stock
            30
        );

        $this->createStockItem(
            $tenantId,
            $productIds[1],
            $warehouseIds[1], // East Coast
            180,  // Good stock
            30
        );

        $this->createStockItem(
            $tenantId,
            $productIds[1],
            $warehouseIds[2], // Midwest
            25,   // Low stock (will trigger alert)
            30
        );

        // Product 3: Cotton T-Shirt Premium
        $this->createStockItem(
            $tenantId,
            $productIds[2],
            $warehouseIds[0], // Main DC
            1000, // Very high stock (popular item)
            100
        );

        $this->createStockItem(
            $tenantId,
            $productIds[2],
            $warehouseIds[1], // East Coast
            750,  // High stock
            100
        );

        $this->createStockItem(
            $tenantId,
            $productIds[2],
            $warehouseIds[2], // Midwest
            95,   // Low stock (will trigger alert)
            100
        );

        $manager->flush();
    }

    private function createStockItem(
        TenantId $tenantId,
        string $productId,
        string $warehouseId,
        int $initialQuantity,
        int $lowStockThreshold
    ): void {
        $command = new CreateStockItemCommand(
            stockItemId: StockItemId::generate(),
            productId: ProductId::fromString($productId),
            warehouseId: WarehouseId::fromString($warehouseId),
            initialQuantity: Quantity::fromInt($initialQuantity),
            lowStockThreshold: Quantity::fromInt($lowStockThreshold),
            tenantId: $tenantId
        );

        $this->commandBus->dispatch($command);
    }
}
