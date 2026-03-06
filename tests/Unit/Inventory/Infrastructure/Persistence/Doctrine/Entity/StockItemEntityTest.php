<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Infrastructure\Persistence\Doctrine\Entity;

use App\Catalog\Domain\Model\ProductId;
use App\Inventory\Domain\Model\Quantity;
use App\Inventory\Domain\Model\StockItem;
use App\Inventory\Domain\Model\StockItemId;
use App\Inventory\Domain\Model\WarehouseId;
use App\Inventory\Infrastructure\Persistence\Doctrine\Entity\StockItemEntity;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class StockItemEntityTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';
    private const PRODUCT_ID = '00000000-0000-4000-8000-000000000002';
    private const WAREHOUSE_ID = '00000000-0000-4000-8000-000000000003';
    private const STOCK_ITEM_ID = '01900000-0000-7000-8000-000000000001';

    private function makeStockItem(
        int $onHand = 100,
        int $reserved = 0,
        int $allocated = 0,
        int $lowStockThreshold = 10,
    ): StockItem {
        $createdAt = new \DateTimeImmutable('2024-01-01T00:00:00+00:00');
        $updatedAt = new \DateTimeImmutable('2024-06-01T00:00:00+00:00');

        return StockItem::reconstituteFromPersistence(
            StockItemId::fromString(self::STOCK_ITEM_ID),
            TenantId::fromString(self::TENANT_ID),
            ProductId::fromString(self::PRODUCT_ID),
            WarehouseId::fromString(self::WAREHOUSE_ID),
            Quantity::fromInt($onHand),
            Quantity::fromInt($reserved),
            Quantity::fromInt($allocated),
            Quantity::fromInt($lowStockThreshold),
            $createdAt,
            $updatedAt,
        );
    }

    public function testFromDomainModelRoundtrip(): void
    {
        $stockItem = $this->makeStockItem(onHand: 100, reserved: 5, allocated: 10, lowStockThreshold: 15);

        $entity = StockItemEntity::fromDomainModel($stockItem);

        self::assertSame(self::STOCK_ITEM_ID, $entity->getId());
        self::assertSame(self::TENANT_ID, $entity->getTenantId());
        self::assertSame(self::PRODUCT_ID, $entity->getProductId());
        self::assertSame(self::WAREHOUSE_ID, $entity->getWarehouseId());
    }

    public function testToDomainModelRestoresAllFields(): void
    {
        $stockItem = $this->makeStockItem(onHand: 50, reserved: 3, allocated: 7, lowStockThreshold: 8);

        $entity = StockItemEntity::fromDomainModel($stockItem);
        $restored = $entity->toDomainModel();

        self::assertSame(self::STOCK_ITEM_ID, $restored->id()->toString());
        self::assertSame(self::TENANT_ID, $restored->tenantId()->toString());
        self::assertSame(self::PRODUCT_ID, $restored->productId()->toString());
        self::assertSame(self::WAREHOUSE_ID, $restored->warehouseId()->toString());
        self::assertSame(50, $restored->onHand()->value());
        self::assertSame(3, $restored->reserved()->value());
        self::assertSame(7, $restored->allocated()->value());
        self::assertSame(8, $restored->lowStockThreshold()->value());
        self::assertSame('2024-01-01T00:00:00+00:00', $restored->createdAt()->format(\DateTimeInterface::ATOM));
        self::assertSame('2024-06-01T00:00:00+00:00', $restored->updatedAt()->format(\DateTimeInterface::ATOM));
    }

    public function testUpdateFromDomainModelChangesMutableFields(): void
    {
        $original = $this->makeStockItem(onHand: 100, reserved: 5, allocated: 10, lowStockThreshold: 10);
        $entity = StockItemEntity::fromDomainModel($original);

        $updatedAt = new \DateTimeImmutable('2025-01-01T00:00:00+00:00');
        $updated = StockItem::reconstituteFromPersistence(
            StockItemId::fromString(self::STOCK_ITEM_ID),
            TenantId::fromString(self::TENANT_ID),
            ProductId::fromString(self::PRODUCT_ID),
            WarehouseId::fromString(self::WAREHOUSE_ID),
            Quantity::fromInt(80),
            Quantity::fromInt(2),
            Quantity::fromInt(3),
            Quantity::fromInt(20),
            new \DateTimeImmutable('2024-01-01T00:00:00+00:00'),
            $updatedAt,
        );

        $entity->updateFromDomainModel($updated);
        $restored = $entity->toDomainModel();

        self::assertSame(80, $restored->onHand()->value());
        self::assertSame(2, $restored->reserved()->value());
        self::assertSame(3, $restored->allocated()->value());
        self::assertSame(20, $restored->lowStockThreshold()->value());
        self::assertSame('2025-01-01T00:00:00+00:00', $restored->updatedAt()->format(\DateTimeInterface::ATOM));
    }

    public function testFromDomainModelWithZeroQuantities(): void
    {
        $stockItem = $this->makeStockItem(onHand: 0, reserved: 0, allocated: 0, lowStockThreshold: 0);

        $entity = StockItemEntity::fromDomainModel($stockItem);
        $restored = $entity->toDomainModel();

        self::assertSame(0, $restored->onHand()->value());
        self::assertSame(0, $restored->reserved()->value());
        self::assertSame(0, $restored->allocated()->value());
        self::assertSame(0, $restored->lowStockThreshold()->value());
    }

    public function testGettersReturnCorrectStringIds(): void
    {
        $stockItem = $this->makeStockItem();
        $entity = StockItemEntity::fromDomainModel($stockItem);

        self::assertIsString($entity->getId());
        self::assertIsString($entity->getTenantId());
        self::assertIsString($entity->getProductId());
        self::assertIsString($entity->getWarehouseId());
    }

    public function testUpdateDoesNotChangeIdentityFields(): void
    {
        $stockItem = $this->makeStockItem(onHand: 100);
        $entity = StockItemEntity::fromDomainModel($stockItem);

        $differentProductId = '00000000-0000-4000-8000-000000000099';
        $updated = StockItem::reconstituteFromPersistence(
            StockItemId::fromString(self::STOCK_ITEM_ID),
            TenantId::fromString(self::TENANT_ID),
            ProductId::fromString($differentProductId),
            WarehouseId::fromString(self::WAREHOUSE_ID),
            Quantity::fromInt(200),
            Quantity::fromInt(0),
            Quantity::fromInt(0),
            Quantity::fromInt(5),
            new \DateTimeImmutable('2024-01-01T00:00:00+00:00'),
            new \DateTimeImmutable('2025-01-01T00:00:00+00:00'),
        );

        $entity->updateFromDomainModel($updated);

        // Identity fields remain from the original fromDomainModel call
        self::assertSame(self::STOCK_ITEM_ID, $entity->getId());
        self::assertSame(self::PRODUCT_ID, $entity->getProductId());
    }
}
