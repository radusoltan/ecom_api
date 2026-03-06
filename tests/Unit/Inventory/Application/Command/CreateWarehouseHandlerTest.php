<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Application\Command;

use App\Inventory\Application\Command\CreateWarehouse\CreateWarehouse;
use App\Inventory\Application\Command\CreateWarehouse\CreateWarehouseHandler;
use App\Inventory\Domain\Model\WarehouseCode;
use App\Inventory\Domain\Model\WarehouseId;
use App\Inventory\Domain\Model\WarehouseName;
use App\Inventory\Domain\Repository\WarehouseRepositoryInterface;
use App\Shared\Domain\ValueObject\Address;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreateWarehouseHandler::class)]
#[CoversClass(CreateWarehouse::class)]
final class CreateWarehouseHandlerTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    private WarehouseRepositoryInterface $repository;
    private CreateWarehouseHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(WarehouseRepositoryInterface::class);
        $this->handler = new CreateWarehouseHandler($this->repository);
    }

    // -----------------------------------------------------------------------
    // Happy path
    // -----------------------------------------------------------------------

    #[Test]
    public function itCreatesWarehouseSuccessfully(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $warehouseId = WarehouseId::generate();
        $code = WarehouseCode::fromString('WH001');
        $name = WarehouseName::fromString('Main Warehouse');
        $address = Address::create('123 Main St', 'New York', 'NY', '10001', 'US');

        $this->repository
            ->method('codeExistsForTenant')
            ->willReturn(false);

        $this->repository
            ->expects(self::once())
            ->method('save');

        $command = new CreateWarehouse($warehouseId, $tenantId, $code, $name, $address);

        ($this->handler)($command);
    }

    #[Test]
    public function itCreatesWarehouseWithExplicitPriority(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $warehouseId = WarehouseId::generate();
        $code = WarehouseCode::fromString('WH002');
        $name = WarehouseName::fromString('Secondary Warehouse');
        $address = Address::create('456 Oak Ave', 'Los Angeles', 'CA', '90001', 'US');

        $this->repository
            ->method('codeExistsForTenant')
            ->willReturn(false);

        $this->repository
            ->expects(self::once())
            ->method('save');

        $command = new CreateWarehouse($warehouseId, $tenantId, $code, $name, $address, priority: 50);

        ($this->handler)($command);
    }

    #[Test]
    public function itCreatesWarehouseWithNullPriority(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $warehouseId = WarehouseId::generate();
        $code = WarehouseCode::fromString('WH003');
        $name = WarehouseName::fromString('Backup Warehouse');
        $address = Address::create('789 Pine Rd', 'Chicago', 'IL', '60601', 'US');

        $this->repository
            ->method('codeExistsForTenant')
            ->willReturn(false);

        $this->repository
            ->expects(self::once())
            ->method('save');

        $command = new CreateWarehouse($warehouseId, $tenantId, $code, $name, $address, priority: null);

        ($this->handler)($command);
    }

    // -----------------------------------------------------------------------
    // Duplicate code guard
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsWhenWarehouseCodeAlreadyExists(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $warehouseId = WarehouseId::generate();
        $code = WarehouseCode::fromString('WH001');
        $name = WarehouseName::fromString('Duplicate Warehouse');
        $address = Address::create('1 Dup St', 'Boston', 'MA', '02101', 'US');

        $this->repository
            ->method('codeExistsForTenant')
            ->willReturn(true);

        $this->repository
            ->expects(self::never())
            ->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('already exists for this tenant');

        $command = new CreateWarehouse($warehouseId, $tenantId, $code, $name, $address);

        ($this->handler)($command);
    }

    #[Test]
    public function itPassesCorrectArgumentsToCodeExistsCheck(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $warehouseId = WarehouseId::generate();
        $code = WarehouseCode::fromString('UNIQUE');
        $name = WarehouseName::fromString('Unique Warehouse');
        $address = Address::create('1 Check St', 'Seattle', 'WA', '98101', 'US');

        $this->repository
            ->expects(self::once())
            ->method('codeExistsForTenant')
            ->with(
                self::callback(fn ($c) => $c instanceof WarehouseCode && 'UNIQUE' === $c->value()),
                self::callback(fn ($t) => $t instanceof TenantId && self::TENANT_ID === $t->toString())
            )
            ->willReturn(false);

        $this->repository->method('save');

        $command = new CreateWarehouse($warehouseId, $tenantId, $code, $name, $address);

        ($this->handler)($command);
    }
}
