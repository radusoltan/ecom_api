<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\ProductEntity;
use App\Inventory\Application\Command\CreateStockItem\CreateStockItemCommand;
use App\Inventory\Domain\Model\Quantity;
use App\Inventory\Domain\Model\StockItemId;
use App\Inventory\Domain\Model\WarehouseId;
use App\Inventory\Infrastructure\Persistence\Doctrine\Entity\WarehouseEntity;
use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Tenant\TenantContext;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Inventory fixtures - creates warehouses and stock items
 * Depends on: TenantFixtures, ProductFixtures.
 */
class InventoryFixtures extends AbstractTenantAwareFixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public function __construct(
        EntityManagerInterface $entityManager,
        TenantContext $tenantContext,
        private readonly MessageBusInterface $commandBus,
    ) {
        parent::__construct($entityManager, $tenantContext);
    }

    public static function getGroups(): array
    {
        return ['sprint3', 'sprint3-inventory'];
    }

    public function load(ObjectManager $manager): void
    {
        echo "📦 Creating tenant inventory...\n";

        $tenantIds = $this->tenantIdsByOwnerEmail();

        foreach (Sprint3SeedData::tenants() as $tenant) {
            $tenantId = TenantId::fromString($tenantIds[$tenant['ownerEmail']]);
            $this->activateTenantContext($tenantId);

            $warehouses = $this->entityManager->getRepository(WarehouseEntity::class)->findBy(
                ['tenantId' => $tenantId->toString()],
                ['priority' => 'ASC']
            );
            $products = $this->entityManager->getRepository(ProductEntity::class)->findBy(
                ['tenantId' => $tenantId->toString()],
                ['createdAt' => 'ASC']
            );

            if (count($warehouses) < 2) {
                throw new \RuntimeException(sprintf('Expected 2 warehouses for tenant %s before seeding inventory', $tenantId->toString()));
            }

            $stockItemsCreated = 0;
            foreach ($products as $index => $product) {
                if (!$product instanceof ProductEntity) {
                    continue;
                }

                $baseQuantity = 45 + (($index * 17) % 120);
                $this->createStockItem(
                    tenantId: $tenantId,
                    productId: ProductId::fromString($product->getId()),
                    warehouseId: WarehouseId::fromString($warehouses[0]->getId()),
                    quantity: $baseQuantity
                );
                ++$stockItemsCreated;

                if (0 === $index % 4) {
                    $this->createStockItem(
                        tenantId: $tenantId,
                        productId: ProductId::fromString($product->getId()),
                        warehouseId: WarehouseId::fromString($warehouses[1]->getId()),
                        quantity: 12 + (($index * 9) % 35)
                    );
                    ++$stockItemsCreated;
                }
            }

            echo sprintf("   ✓ %s stock items: %d\n", $tenant['name'], $stockItemsCreated);
            $this->entityManager->clear();
        }

        $this->clearTenantContext();

        echo "✅ Inventory created successfully\n";
    }

    private function createStockItem(
        TenantId $tenantId,
        ProductId $productId,
        WarehouseId $warehouseId,
        int $quantity,
    ): void {
        $stockItemId = StockItemId::fromString((string) Uuid::v7());

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

    public function getDependencies(): array
    {
        return [
            ProductFixtures::class,
            WarehouseFixtures::class,
        ];
    }
}
