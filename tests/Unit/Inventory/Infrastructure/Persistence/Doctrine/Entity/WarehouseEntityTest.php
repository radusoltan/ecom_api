<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Infrastructure\Persistence\Doctrine\Entity;

use App\Inventory\Domain\Model\Warehouse;
use App\Inventory\Domain\Model\WarehouseCode;
use App\Inventory\Domain\Model\WarehouseId;
use App\Inventory\Domain\Model\WarehouseName;
use App\Inventory\Infrastructure\Persistence\Doctrine\Entity\WarehouseEntity;
use App\Shared\Domain\ValueObject\Address;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class WarehouseEntityTest extends TestCase
{
    public function testFromDomainModelRoundtrip(): void
    {
        $warehouse = Warehouse::create(
            WarehouseId::generate(),
            TenantId::generate(),
            WarehouseCode::fromString('WH-MAIN'),
            WarehouseName::fromString('Main Warehouse'),
            Address::create('123 Industrial Rd', 'Berlin', 'BE', '10115', 'DE'),
            5,
        );

        $entity = WarehouseEntity::fromDomainModel($warehouse);

        self::assertSame('WH-MAIN', $entity->getCode());
        self::assertSame('Main Warehouse', $entity->getName());
        self::assertSame(5, $entity->getPriority());
        self::assertTrue($entity->isActive());
        self::assertIsArray($entity->getAddress());

        $restored = $entity->toDomainModel();
        self::assertSame('Main Warehouse', $restored->name()->value());
        self::assertSame('WH-MAIN', $restored->code()->value());
    }

    public function testUpdateFromDomainModel(): void
    {
        $warehouse = Warehouse::create(
            WarehouseId::generate(),
            TenantId::generate(),
            WarehouseCode::fromString('WH-SEC'),
            WarehouseName::fromString('Secondary'),
            Address::create('456 Park Ave', 'Munich', 'BY', '80331', 'DE'),
        );

        $entity = WarehouseEntity::fromDomainModel($warehouse);
        $warehouse->deactivate();
        $entity->updateFromDomainModel($warehouse);

        self::assertFalse($entity->isActive());
    }

    public function testSetters(): void
    {
        $warehouse = Warehouse::create(
            WarehouseId::generate(),
            TenantId::generate(),
            WarehouseCode::fromString('WH-TEST'),
            WarehouseName::fromString('Test'),
            Address::create('1 St', 'City', 'ST', '00000', 'DE'),
        );
        $entity = WarehouseEntity::fromDomainModel($warehouse);

        $entity->setName('Updated');
        $entity->setAddress(['street' => '789 New St']);
        $entity->setPriority(10);
        $entity->setIsActive(false);
        $entity->setUpdatedAt(new \DateTimeImmutable());

        self::assertSame('Updated', $entity->getName());
        self::assertSame(10, $entity->getPriority());
        self::assertFalse($entity->isActive());
    }
}
