<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Application\Command;

use App\Inventory\Application\Command\ActivateWarehouse\ActivateWarehouse;
use App\Inventory\Application\Command\ActivateWarehouse\ActivateWarehouseHandler;
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

#[CoversClass(ActivateWarehouseHandler::class)]
#[CoversClass(ActivateWarehouse::class)]
final class ActivateWarehouseHandlerTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    private WarehouseRepositoryInterface $repository;
    private ActivateWarehouseHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(WarehouseRepositoryInterface::class);
        $this->handler = new ActivateWarehouseHandler($this->repository);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function buildInactiveWarehouse(WarehouseId $id): Warehouse
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $warehouse = Warehouse::create(
            $id,
            $tenantId,
            WarehouseCode::fromString('WH-INACT'),
            WarehouseName::fromString('Inactive Warehouse'),
            Address::create('5 Closed Blvd', 'Miami', 'FL', '33101', 'US'),
        );

        $warehouse->deactivate();
        $warehouse->popEvents();

        return $warehouse;
    }

    // -----------------------------------------------------------------------
    // Happy path
    // -----------------------------------------------------------------------

    #[Test]
    public function itActivatesInactiveWarehouseSuccessfully(): void
    {
        $warehouseId = WarehouseId::generate();
        $warehouse = $this->buildInactiveWarehouse($warehouseId);

        self::assertFalse($warehouse->isActive());

        $this->repository
            ->method('findById')
            ->willReturn($warehouse);

        $this->repository
            ->expects(self::once())
            ->method('save');

        $command = new ActivateWarehouse($warehouseId);

        ($this->handler)($command);

        self::assertTrue($warehouse->isActive());
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

        ($this->handler)(new ActivateWarehouse($warehouseId));
    }

    // -----------------------------------------------------------------------
    // Already active guard (domain invariant)
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsWhenWarehouseIsAlreadyActive(): void
    {
        $warehouseId = WarehouseId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);

        // Warehouse::create() starts as active
        $warehouse = Warehouse::create(
            $warehouseId,
            $tenantId,
            WarehouseCode::fromString('WH-ACT'),
            WarehouseName::fromString('Already Active'),
            Address::create('7 Open Rd', 'Phoenix', 'AZ', '85001', 'US'),
        );
        $warehouse->popEvents();

        $this->repository
            ->method('findById')
            ->willReturn($warehouse);

        $this->repository
            ->expects(self::never())
            ->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('already active');

        ($this->handler)(new ActivateWarehouse($warehouseId));
    }
}
