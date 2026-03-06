<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Application\Command;

use App\Inventory\Application\Command\UpdateWarehouse\UpdateWarehouse;
use App\Inventory\Application\Command\UpdateWarehouse\UpdateWarehouseHandler;
use App\Inventory\Domain\Model\Warehouse;
use App\Inventory\Domain\Model\WarehouseCode;
use App\Inventory\Domain\Model\WarehouseId;
use App\Inventory\Domain\Model\WarehouseName;
use App\Inventory\Domain\Repository\WarehouseRepositoryInterface;
use App\Shared\Domain\ValueObject\Address;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(UpdateWarehouseHandler::class)]
#[CoversClass(UpdateWarehouse::class)]
final class UpdateWarehouseHandlerTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    private WarehouseRepositoryInterface $repository;
    private UpdateWarehouseHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(WarehouseRepositoryInterface::class);
        $this->handler = new UpdateWarehouseHandler($this->repository);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function buildWarehouse(
        ?WarehouseId $id = null,
        bool $isActive = true,
    ): Warehouse {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $warehouseId = $id ?? WarehouseId::generate();

        $warehouse = Warehouse::create(
            $warehouseId,
            $tenantId,
            WarehouseCode::fromString('WH001'),
            WarehouseName::fromString('Original Name'),
            Address::create('1 Old St', 'Boston', 'MA', '02101', 'US'),
        );

        if (!$isActive) {
            $warehouse->deactivate();
        }

        // Drain events produced by create/deactivate so assertions are clean
        $warehouse->popEvents();

        return $warehouse;
    }

    // -----------------------------------------------------------------------
    // Happy path
    // -----------------------------------------------------------------------

    #[Test]
    public function itUpdatesWarehouseSuccessfully(): void
    {
        $warehouseId = WarehouseId::generate();
        $warehouse = $this->buildWarehouse($warehouseId);
        $newAddress = Address::create('99 New Ave', 'Chicago', 'IL', '60601', 'US');
        $newName = WarehouseName::fromString('Updated Name');

        $this->repository
            ->method('findById')
            ->willReturn($warehouse);

        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(fn ($w) => 'Updated Name' === $w->name()->value()));

        $command = new UpdateWarehouse($warehouseId, $newName, $newAddress, priority: 10);

        ($this->handler)($command);

        self::assertSame('Updated Name', $warehouse->name()->value());
        self::assertSame(10, $warehouse->priority());
    }

    #[Test]
    public function itSavesWithCorrectPriority(): void
    {
        $warehouseId = WarehouseId::generate();
        $warehouse = $this->buildWarehouse($warehouseId);
        $address = Address::create('1 Prio St', 'Denver', 'CO', '80201', 'US');
        $name = WarehouseName::fromString('Priority Test');

        $this->repository
            ->method('findById')
            ->willReturn($warehouse);

        $this->repository
            ->expects(self::once())
            ->method('save');

        $command = new UpdateWarehouse($warehouseId, $name, $address, priority: 5);

        ($this->handler)($command);

        self::assertSame(5, $warehouse->priority());
    }

    // -----------------------------------------------------------------------
    // Not found guard
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsWhenWarehouseNotFound(): void
    {
        $warehouseId = WarehouseId::generate();

        $this->repository
            ->method('findById')
            ->willReturn(null);

        $this->repository
            ->expects(self::never())
            ->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Warehouse not found');

        $command = new UpdateWarehouse(
            $warehouseId,
            WarehouseName::fromString('No Such Warehouse'),
            Address::create('X St', 'Nowhere', 'XX', '00000', 'US'),
            priority: 1,
        );

        ($this->handler)($command);
    }

    // -----------------------------------------------------------------------
    // Domain invariant — negative priority
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsWhenPriorityIsNegative(): void
    {
        $warehouseId = WarehouseId::generate();
        $warehouse = $this->buildWarehouse($warehouseId);

        $this->repository
            ->method('findById')
            ->willReturn($warehouse);

        $this->repository
            ->expects(self::never())
            ->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('priority must be non-negative');

        $command = new UpdateWarehouse(
            $warehouseId,
            WarehouseName::fromString('Bad Priority'),
            Address::create('2 Bad St', 'Austin', 'TX', '78701', 'US'),
            priority: -1,
        );

        ($this->handler)($command);
    }
}
