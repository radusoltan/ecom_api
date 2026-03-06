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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Warehouse::class)]
final class WarehouseEdgeCasesTest extends TestCase
{
    private function buildAddress(): Address
    {
        return Address::fromArray([
            'street' => '10 Dock Rd',
            'city' => 'Chicago',
            'state' => 'IL',
            'postalCode' => '60601',
            'country' => 'US',
        ]);
    }

    private function buildWarehouse(?int $priority = null): Warehouse
    {
        $w = Warehouse::create(
            WarehouseId::generate(),
            TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            WarehouseCode::fromString('WH001'),
            WarehouseName::fromString('Default Warehouse'),
            $this->buildAddress(),
            $priority,
        );
        $w->popEvents();

        return $w;
    }

    // ---------------------------------------------------------------
    // create — event content
    // ---------------------------------------------------------------

    #[Test]
    public function itCreateRecordsWarehouseCreatedWithCorrectData(): void
    {
        $id = WarehouseId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $code = WarehouseCode::fromString('NYC01');
        $name = WarehouseName::fromString('New York Warehouse');

        $w = Warehouse::create($id, $tenantId, $code, $name, $this->buildAddress());
        $events = $w->popEvents();

        self::assertCount(1, $events);
        $event = $events[0];
        self::assertInstanceOf(WarehouseCreated::class, $event);
        self::assertTrue($event->warehouseId->equals($id));
        self::assertTrue($event->tenantId->equals($tenantId));
        self::assertTrue($event->code->equals($code));
        self::assertTrue($event->name->equals($name));
    }

    #[Test]
    public function itCreateIsActiveByDefault(): void
    {
        $w = $this->buildWarehouse();

        self::assertTrue($w->isActive());
        self::assertTrue($w->canFulfillOrders());
    }

    #[Test]
    public function itCreateUsesDefaultPriority100(): void
    {
        $w = $this->buildWarehouse();

        self::assertSame(100, $w->priority());
    }

    #[Test]
    public function itCreateWithCustomPriority(): void
    {
        $w = $this->buildWarehouse(42);

        self::assertSame(42, $w->priority());
    }

    // ---------------------------------------------------------------
    // update
    // ---------------------------------------------------------------

    #[Test]
    public function itUpdateChangesNameAddressAndPriority(): void
    {
        $w = $this->buildWarehouse();
        $newName = WarehouseName::fromString('Updated Distribution Center');
        $newAddress = Address::fromArray([
            'street' => '99 New Ave',
            'city' => 'Detroit',
            'state' => 'MI',
            'postalCode' => '48201',
            'country' => 'US',
        ]);

        $w->update($newName, $newAddress, 75);

        self::assertTrue($w->name()->equals($newName));
        self::assertSame('99 New Ave', $w->address()->street());
        self::assertSame('Detroit', $w->address()->city());
        self::assertSame(75, $w->priority());
    }

    #[Test]
    public function itUpdateRecordsWarehouseUpdatedEvent(): void
    {
        $w = $this->buildWarehouse();
        $newName = WarehouseName::fromString('Renamed');

        $w->update($newName, $this->buildAddress(), 10);
        $events = $w->popEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(WarehouseUpdated::class, $events[0]);
        self::assertTrue($events[0]->name->equals($newName));
    }

    #[Test]
    public function itUpdateWithPriorityZeroIsAllowed(): void
    {
        $w = $this->buildWarehouse();

        $w->update(WarehouseName::fromString('Min Priority'), $this->buildAddress(), 0);

        self::assertSame(0, $w->priority());
    }

    #[Test]
    public function itUpdateWithNegativePriorityThrows(): void
    {
        $w = $this->buildWarehouse();

        self::expectException(\DomainException::class);
        self::expectExceptionMessage('priority must be non-negative');

        $w->update(WarehouseName::fromString('Bad'), $this->buildAddress(), -1);
    }

    #[Test]
    public function itUpdateDoesNotChangeActiveStatus(): void
    {
        $w = $this->buildWarehouse();
        $w->deactivate();

        $w->update(WarehouseName::fromString('Still Inactive'), $this->buildAddress(), 5);

        self::assertFalse($w->isActive());
    }

    // ---------------------------------------------------------------
    // deactivate / activate transitions
    // ---------------------------------------------------------------

    #[Test]
    public function itDeactivateRecordsDeactivatedEvent(): void
    {
        $w = $this->buildWarehouse();

        $w->deactivate();
        $events = $w->popEvents();

        self::assertCount(1, $events);
        $event = $events[0];
        self::assertInstanceOf(WarehouseDeactivated::class, $event);
        self::assertTrue($event->warehouseId->equals($w->id()));
    }

    #[Test]
    public function itActivateAfterDeactivateRecordsActivatedEvent(): void
    {
        $w = $this->buildWarehouse();
        $w->deactivate();
        $w->popEvents();

        $w->activate();
        $events = $w->popEvents();

        self::assertCount(1, $events);
        $event = $events[0];
        self::assertInstanceOf(WarehouseActivated::class, $event);
        self::assertTrue($event->warehouseId->equals($w->id()));
    }

    #[Test]
    public function itCanFulfillOrdersReturnsFalseAfterDeactivation(): void
    {
        $w = $this->buildWarehouse();

        $w->deactivate();

        self::assertFalse($w->canFulfillOrders());
    }

    #[Test]
    public function itCanFulfillOrdersReturnsTrueAfterReactivation(): void
    {
        $w = $this->buildWarehouse();
        $w->deactivate();

        $w->activate();

        self::assertTrue($w->canFulfillOrders());
    }

    #[Test]
    public function itThrowsActivatingAlreadyActiveWarehouse(): void
    {
        $w = $this->buildWarehouse(); // active by default

        self::expectException(\DomainException::class);
        self::expectExceptionMessage('already active');

        $w->activate();
    }

    #[Test]
    public function itThrowsDeactivatingAlreadyInactiveWarehouse(): void
    {
        $w = $this->buildWarehouse();
        $w->deactivate();

        self::expectException(\DomainException::class);
        self::expectExceptionMessage('already inactive');

        $w->deactivate();
    }

    // ---------------------------------------------------------------
    // setPriority
    // ---------------------------------------------------------------

    #[Test]
    public function itSetPriorityTo5(): void
    {
        $w = $this->buildWarehouse();

        $w->setPriority(5);

        self::assertSame(5, $w->priority());
    }

    #[Test]
    public function itSetPriorityThrowsForZero(): void
    {
        $w = $this->buildWarehouse();

        self::expectException(\DomainException::class);
        self::expectExceptionMessage('priority must be between 1 and 10');

        $w->setPriority(0);
    }

    #[Test]
    public function itSetPriorityThrowsForAboveMax(): void
    {
        $w = $this->buildWarehouse();

        self::expectException(\DomainException::class);
        self::expectExceptionMessage('priority must be between 1 and 10');

        $w->setPriority(11);
    }

    #[Test]
    public function itSetPriorityDoesNotEmitAnEvent(): void
    {
        $w = $this->buildWarehouse();

        $w->setPriority(3);

        // setPriority should not emit any events per the implementation
        self::assertEmpty($w->popEvents());
    }

    // ---------------------------------------------------------------
    // reconstituteFromPersistence
    // ---------------------------------------------------------------

    #[Test]
    public function itReconstituteSetsAllFields(): void
    {
        $id = WarehouseId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $code = WarehouseCode::fromString('LAX01');
        $name = WarehouseName::fromString('Los Angeles Depot');
        $address = $this->buildAddress();
        $created = new \DateTimeImmutable('2024-01-01');
        $updated = new \DateTimeImmutable('2024-06-01');

        $w = Warehouse::reconstituteFromPersistence(
            $id, $tenantId, $code, $name, $address, 8, false, $created, $updated,
        );

        self::assertTrue($w->id()->equals($id));
        self::assertTrue($w->tenantId()->equals($tenantId));
        self::assertTrue($w->code()->equals($code));
        self::assertTrue($w->name()->equals($name));
        self::assertSame(8, $w->priority());
        self::assertFalse($w->isActive());
        self::assertSame($created, $w->createdAt());
        self::assertSame($updated, $w->updatedAt());
    }

    #[Test]
    public function itReconstituteEmitsNoEvents(): void
    {
        $w = Warehouse::reconstituteFromPersistence(
            WarehouseId::generate(),
            TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            WarehouseCode::fromString('TST01'),
            WarehouseName::fromString('Test Wh'),
            $this->buildAddress(),
            5,
            true,
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
        );

        self::assertEmpty($w->popEvents());
    }

    // ---------------------------------------------------------------
    // popEvents drains events
    // ---------------------------------------------------------------

    #[Test]
    public function itPopEventsClearsEventsAfterCalling(): void
    {
        $w = Warehouse::create(
            WarehouseId::generate(),
            TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            WarehouseCode::fromString('DRAIN1'),
            WarehouseName::fromString('Drain Test'),
            $this->buildAddress(),
        );

        $first = $w->popEvents();
        self::assertCount(1, $first);

        $second = $w->popEvents();
        self::assertEmpty($second);
    }
}
