<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Domain\Model;

use App\Inventory\Domain\Event\WarehouseActivated;
use App\Inventory\Domain\Event\WarehouseCreated;
use App\Inventory\Domain\Event\WarehouseDeactivated;
use App\Inventory\Domain\Event\WarehouseUpdated;
use App\Inventory\Domain\Model\Warehouse;
use App\Inventory\Domain\Model\WarehouseCode;
use App\Inventory\Domain\Model\WarehouseId;
use App\Inventory\Domain\Model\WarehouseName;
use App\Shared\Domain\ValueObject\Address;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class WarehouseTest extends TestCase
{
    private function createTestAddress(): Address
    {
        return Address::fromArray([
            'street' => '123 Main St',
            'city' => 'New York',
            'state' => 'NY',
            'postalCode' => '10001',
            'country' => 'US',
        ]);
    }

    public function testCreateWarehouse(): void
    {
        $id = WarehouseId::generate();
        $tenantId = TenantId::generate();
        $code = WarehouseCode::fromString('WH001');
        $name = WarehouseName::fromString('Main Warehouse');
        $address = $this->createTestAddress();

        $warehouse = Warehouse::create($id, $tenantId, $code, $name, $address);

        $this->assertTrue($warehouse->id()->equals($id));
        $this->assertTrue($warehouse->tenantId()->equals($tenantId));
        $this->assertTrue($warehouse->code()->equals($code));
        $this->assertTrue($warehouse->name()->equals($name));
        $this->assertSame('123 Main St', $warehouse->address()->street());
        $this->assertSame(100, $warehouse->priority()); // Default priority
        $this->assertTrue($warehouse->isActive()); // Active by default
        $this->assertInstanceOf(\DateTimeImmutable::class, $warehouse->createdAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $warehouse->updatedAt());
    }

    public function testCreateWarehouseWithCustomPriority(): void
    {
        $warehouse = Warehouse::create(
            WarehouseId::generate(),
            TenantId::generate(),
            WarehouseCode::fromString('WH001'),
            WarehouseName::fromString('Main Warehouse'),
            $this->createTestAddress(),
            50
        );

        $this->assertSame(50, $warehouse->priority());
    }

    public function testCreateWarehouseRecordsCreatedEvent(): void
    {
        $id = WarehouseId::generate();
        $tenantId = TenantId::generate();
        $code = WarehouseCode::fromString('WH001');
        $name = WarehouseName::fromString('Main Warehouse');

        $warehouse = Warehouse::create($id, $tenantId, $code, $name, $this->createTestAddress());

        $events = $warehouse->popEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(WarehouseCreated::class, $events[0]);

        $event = $events[0];
        $this->assertTrue($event->warehouseId->equals($id));
        $this->assertTrue($event->tenantId->equals($tenantId));
        $this->assertTrue($event->code->equals($code));
        $this->assertTrue($event->name->equals($name));
    }

    public function testUpdateWarehouse(): void
    {
        $warehouse = Warehouse::create(
            WarehouseId::generate(),
            TenantId::generate(),
            WarehouseCode::fromString('WH001'),
            WarehouseName::fromString('Main Warehouse'),
            $this->createTestAddress()
        );

        $warehouse->popEvents(); // Clear creation event

        $newName = WarehouseName::fromString('Updated Warehouse');
        $newAddress = Address::fromArray([
            'street' => '456 Oak Ave',
            'city' => 'Boston',
            'state' => 'MA',
            'postalCode' => '02101',
            'country' => 'US',
        ]);
        $newPriority = 50;

        $warehouse->update($newName, $newAddress, $newPriority);

        $this->assertTrue($warehouse->name()->equals($newName));
        $this->assertSame('456 Oak Ave', $warehouse->address()->street());
        $this->assertSame('Boston', $warehouse->address()->city());
        $this->assertSame(50, $warehouse->priority());
    }

    public function testUpdateWarehouseRecordsUpdatedEvent(): void
    {
        $warehouse = Warehouse::create(
            WarehouseId::generate(),
            TenantId::generate(),
            WarehouseCode::fromString('WH001'),
            WarehouseName::fromString('Main Warehouse'),
            $this->createTestAddress()
        );

        $warehouse->popEvents(); // Clear creation event

        $newName = WarehouseName::fromString('Updated Warehouse');
        $warehouse->update($newName, $this->createTestAddress(), 50);

        $events = $warehouse->popEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(WarehouseUpdated::class, $events[0]);

        $event = $events[0];
        $this->assertTrue($event->name->equals($newName));
    }

    public function testUpdateWithNegativePriorityThrowsException(): void
    {
        $warehouse = Warehouse::create(
            WarehouseId::generate(),
            TenantId::generate(),
            WarehouseCode::fromString('WH001'),
            WarehouseName::fromString('Main Warehouse'),
            $this->createTestAddress()
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('priority must be non-negative');

        $warehouse->update(
            WarehouseName::fromString('Updated'),
            $this->createTestAddress(),
            -1
        );
    }

    public function testActivateWarehouse(): void
    {
        $warehouse = Warehouse::create(
            WarehouseId::generate(),
            TenantId::generate(),
            WarehouseCode::fromString('WH001'),
            WarehouseName::fromString('Main Warehouse'),
            $this->createTestAddress()
        );

        // Deactivate first
        $warehouse->deactivate();
        $this->assertFalse($warehouse->isActive());

        $warehouse->popEvents(); // Clear events

        // Now activate
        $warehouse->activate();

        $this->assertTrue($warehouse->isActive());
    }

    public function testActivateWarehouseRecordsActivatedEvent(): void
    {
        $warehouse = Warehouse::create(
            WarehouseId::generate(),
            TenantId::generate(),
            WarehouseCode::fromString('WH001'),
            WarehouseName::fromString('Main Warehouse'),
            $this->createTestAddress()
        );

        $warehouse->deactivate();
        $warehouse->popEvents(); // Clear events

        $warehouse->activate();

        $events = $warehouse->popEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(WarehouseActivated::class, $events[0]);
    }

    public function testActivateAlreadyActiveWarehouseThrowsException(): void
    {
        $warehouse = Warehouse::create(
            WarehouseId::generate(),
            TenantId::generate(),
            WarehouseCode::fromString('WH001'),
            WarehouseName::fromString('Main Warehouse'),
            $this->createTestAddress()
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('already active');

        $warehouse->activate();
    }

    public function testDeactivateWarehouse(): void
    {
        $warehouse = Warehouse::create(
            WarehouseId::generate(),
            TenantId::generate(),
            WarehouseCode::fromString('WH001'),
            WarehouseName::fromString('Main Warehouse'),
            $this->createTestAddress()
        );

        $warehouse->popEvents(); // Clear creation event

        $warehouse->deactivate();

        $this->assertFalse($warehouse->isActive());
    }

    public function testDeactivateWarehouseRecordsDeactivatedEvent(): void
    {
        $warehouse = Warehouse::create(
            WarehouseId::generate(),
            TenantId::generate(),
            WarehouseCode::fromString('WH001'),
            WarehouseName::fromString('Main Warehouse'),
            $this->createTestAddress()
        );

        $warehouse->popEvents(); // Clear creation event

        $warehouse->deactivate();

        $events = $warehouse->popEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(WarehouseDeactivated::class, $events[0]);
    }

    public function testDeactivateAlreadyInactiveWarehouseThrowsException(): void
    {
        $warehouse = Warehouse::create(
            WarehouseId::generate(),
            TenantId::generate(),
            WarehouseCode::fromString('WH001'),
            WarehouseName::fromString('Main Warehouse'),
            $this->createTestAddress()
        );

        $warehouse->deactivate();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('already inactive');

        $warehouse->deactivate();
    }

    public function testCanFulfillOrdersReturnsTrueWhenActive(): void
    {
        $warehouse = Warehouse::create(
            WarehouseId::generate(),
            TenantId::generate(),
            WarehouseCode::fromString('WH001'),
            WarehouseName::fromString('Main Warehouse'),
            $this->createTestAddress()
        );

        $this->assertTrue($warehouse->canFulfillOrders());
    }

    public function testCanFulfillOrdersReturnsFalseWhenInactive(): void
    {
        $warehouse = Warehouse::create(
            WarehouseId::generate(),
            TenantId::generate(),
            WarehouseCode::fromString('WH001'),
            WarehouseName::fromString('Main Warehouse'),
            $this->createTestAddress()
        );

        $warehouse->deactivate();

        $this->assertFalse($warehouse->canFulfillOrders());
    }

    public function testReconstituteFromPersistence(): void
    {
        $id = WarehouseId::generate();
        $tenantId = TenantId::generate();
        $code = WarehouseCode::fromString('WH001');
        $name = WarehouseName::fromString('Main Warehouse');
        $address = $this->createTestAddress();
        $priority = 50;
        $isActive = false;
        $createdAt = new \DateTimeImmutable('2024-01-01 10:00:00');
        $updatedAt = new \DateTimeImmutable('2024-01-15 14:30:00');

        $warehouse = Warehouse::reconstituteFromPersistence(
            $id,
            $tenantId,
            $code,
            $name,
            $address,
            $priority,
            $isActive,
            $createdAt,
            $updatedAt
        );

        $this->assertTrue($warehouse->id()->equals($id));
        $this->assertTrue($warehouse->tenantId()->equals($tenantId));
        $this->assertTrue($warehouse->code()->equals($code));
        $this->assertTrue($warehouse->name()->equals($name));
        $this->assertSame($priority, $warehouse->priority());
        $this->assertSame($isActive, $warehouse->isActive());
        $this->assertSame($createdAt, $warehouse->createdAt());
        $this->assertSame($updatedAt, $warehouse->updatedAt());
    }

    public function testReconstituteFromPersistenceDoesNotRecordEvents(): void
    {
        $warehouse = Warehouse::reconstituteFromPersistence(
            WarehouseId::generate(),
            TenantId::generate(),
            WarehouseCode::fromString('WH001'),
            WarehouseName::fromString('Main Warehouse'),
            $this->createTestAddress(),
            100,
            true,
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );

        $events = $warehouse->popEvents();
        $this->assertCount(0, $events);
    }

    public function testMultipleOperationsRecordMultipleEvents(): void
    {
        $warehouse = Warehouse::create(
            WarehouseId::generate(),
            TenantId::generate(),
            WarehouseCode::fromString('WH001'),
            WarehouseName::fromString('Main Warehouse'),
            $this->createTestAddress()
        );

        $warehouse->update(
            WarehouseName::fromString('Updated'),
            $this->createTestAddress(),
            50
        );

        $warehouse->deactivate();
        $warehouse->activate();

        $events = $warehouse->popEvents();
        $this->assertCount(4, $events); // Created, Updated, Deactivated, Activated
        $this->assertInstanceOf(WarehouseCreated::class, $events[0]);
        $this->assertInstanceOf(WarehouseUpdated::class, $events[1]);
        $this->assertInstanceOf(WarehouseDeactivated::class, $events[2]);
        $this->assertInstanceOf(WarehouseActivated::class, $events[3]);
    }

    public function testPopEventsClearsEvents(): void
    {
        $warehouse = Warehouse::create(
            WarehouseId::generate(),
            TenantId::generate(),
            WarehouseCode::fromString('WH001'),
            WarehouseName::fromString('Main Warehouse'),
            $this->createTestAddress()
        );

        $events1 = $warehouse->popEvents();
        $this->assertCount(1, $events1);

        $events2 = $warehouse->popEvents();
        $this->assertCount(0, $events2);
    }

    public function testUpdateWithZeroPriorityIsAllowed(): void
    {
        $warehouse = Warehouse::create(
            WarehouseId::generate(),
            TenantId::generate(),
            WarehouseCode::fromString('WH001'),
            WarehouseName::fromString('Main Warehouse'),
            $this->createTestAddress()
        );

        $warehouse->update(
            WarehouseName::fromString('Updated'),
            $this->createTestAddress(),
            0
        );

        $this->assertSame(0, $warehouse->priority());
    }

    public function testActivateDeactivateCycle(): void
    {
        $warehouse = Warehouse::create(
            WarehouseId::generate(),
            TenantId::generate(),
            WarehouseCode::fromString('WH001'),
            WarehouseName::fromString('Main Warehouse'),
            $this->createTestAddress()
        );

        // Initially active
        $this->assertTrue($warehouse->isActive());

        // Deactivate
        $warehouse->deactivate();
        $this->assertFalse($warehouse->isActive());

        // Activate again
        $warehouse->activate();
        $this->assertTrue($warehouse->isActive());

        // Deactivate again
        $warehouse->deactivate();
        $this->assertFalse($warehouse->isActive());
    }

    public function testSetPriorityUpdatesValue(): void
    {
        $warehouse = Warehouse::create(
            WarehouseId::generate(),
            TenantId::generate(),
            WarehouseCode::fromString('WH001'),
            WarehouseName::fromString('Main Warehouse'),
            $this->createTestAddress()
        );

        $warehouse->setPriority(8);

        $this->assertSame(8, $warehouse->priority());
    }

    public function testSetPriorityWithMinValue(): void
    {
        $warehouse = Warehouse::create(
            WarehouseId::generate(),
            TenantId::generate(),
            WarehouseCode::fromString('WH001'),
            WarehouseName::fromString('Main Warehouse'),
            $this->createTestAddress()
        );

        $warehouse->setPriority(1);

        $this->assertSame(1, $warehouse->priority());
    }

    public function testSetPriorityWithMaxValue(): void
    {
        $warehouse = Warehouse::create(
            WarehouseId::generate(),
            TenantId::generate(),
            WarehouseCode::fromString('WH001'),
            WarehouseName::fromString('Main Warehouse'),
            $this->createTestAddress()
        );

        $warehouse->setPriority(10);

        $this->assertSame(10, $warehouse->priority());
    }

    public function testSetPriorityWithZeroThrowsException(): void
    {
        $warehouse = Warehouse::create(
            WarehouseId::generate(),
            TenantId::generate(),
            WarehouseCode::fromString('WH001'),
            WarehouseName::fromString('Main Warehouse'),
            $this->createTestAddress()
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('priority must be between 1 and 10');

        $warehouse->setPriority(0);
    }

    public function testSetPriorityWithNegativeValueThrowsException(): void
    {
        $warehouse = Warehouse::create(
            WarehouseId::generate(),
            TenantId::generate(),
            WarehouseCode::fromString('WH001'),
            WarehouseName::fromString('Main Warehouse'),
            $this->createTestAddress()
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('priority must be between 1 and 10');

        $warehouse->setPriority(-5);
    }

    public function testSetPriorityWithValueAboveMaxThrowsException(): void
    {
        $warehouse = Warehouse::create(
            WarehouseId::generate(),
            TenantId::generate(),
            WarehouseCode::fromString('WH001'),
            WarehouseName::fromString('Main Warehouse'),
            $this->createTestAddress()
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('priority must be between 1 and 10');

        $warehouse->setPriority(11);
    }
}
