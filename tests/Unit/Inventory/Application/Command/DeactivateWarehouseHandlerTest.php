<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Application\Command;

use App\Inventory\Application\Command\DeactivateWarehouse\DeactivateWarehouse;
use App\Inventory\Application\Command\DeactivateWarehouse\DeactivateWarehouseHandler;
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

#[CoversClass(DeactivateWarehouseHandler::class)]
#[CoversClass(DeactivateWarehouse::class)]
final class DeactivateWarehouseHandlerTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    private WarehouseRepositoryInterface $repository;
    private DeactivateWarehouseHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(WarehouseRepositoryInterface::class);
        $this->handler = new DeactivateWarehouseHandler($this->repository);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function buildActiveWarehouse(WarehouseId $id): Warehouse
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $warehouse = Warehouse::create(
            $id,
            $tenantId,
            WarehouseCode::fromString('WH-LIVE'),
            WarehouseName::fromString('Active Warehouse'),
            Address::create('3 Running Ave', 'Dallas', 'TX', '75201', 'US'),
        );
        $warehouse->popEvents();

        return $warehouse;
    }

    // -----------------------------------------------------------------------
    // Happy path
    // -----------------------------------------------------------------------

    #[Test]
    public function itDeactivatesActiveWarehouseSuccessfully(): void
    {
        $warehouseId = WarehouseId::generate();
        $warehouse = $this->buildActiveWarehouse($warehouseId);

        self::assertTrue($warehouse->isActive());

        $this->repository
            ->method('findById')
            ->willReturn($warehouse);

        $this->repository
            ->expects(self::once())
            ->method('save');

        $command = new DeactivateWarehouse($warehouseId);

        ($this->handler)($command);

        self::assertFalse($warehouse->isActive());
    }

    // -----------------------------------------------------------------------
    // Not found
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

        ($this->handler)(new DeactivateWarehouse($warehouseId));
    }

    // -----------------------------------------------------------------------
    // Already inactive guard (domain invariant)
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsWhenWarehouseIsAlreadyInactive(): void
    {
        $warehouseId = WarehouseId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $warehouse = Warehouse::create(
            $warehouseId,
            $tenantId,
            WarehouseCode::fromString('WH-STOP'),
            WarehouseName::fromString('Soon Inactive'),
            Address::create('9 Stop St', 'Houston', 'TX', '77001', 'US'),
        );
        $warehouse->deactivate();
        $warehouse->popEvents();

        $this->repository
            ->method('findById')
            ->willReturn($warehouse);

        $this->repository
            ->expects(self::never())
            ->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('already inactive');

        ($this->handler)(new DeactivateWarehouse($warehouseId));
    }
}
