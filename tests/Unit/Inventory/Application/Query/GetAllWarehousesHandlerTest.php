<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Application\Query;

use App\Inventory\Application\DTO\WarehouseDTO;
use App\Inventory\Application\Query\GetAllWarehouses\GetAllWarehouses;
use App\Inventory\Application\Query\GetAllWarehouses\GetAllWarehousesHandler;
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

#[CoversClass(GetAllWarehousesHandler::class)]
#[CoversClass(GetAllWarehouses::class)]
final class GetAllWarehousesHandlerTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    private WarehouseRepositoryInterface $repository;
    private GetAllWarehousesHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(WarehouseRepositoryInterface::class);
        $this->handler = new GetAllWarehousesHandler($this->repository);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function buildWarehouse(string $code, bool $active = true): Warehouse
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $warehouse = Warehouse::create(
            WarehouseId::generate(),
            $tenantId,
            WarehouseCode::fromString($code),
            WarehouseName::fromString("Warehouse {$code}"),
            Address::create('1 Test St', 'Portland', 'OR', '97201', 'US'),
        );

        if (!$active) {
            $warehouse->deactivate();
        }

        $warehouse->popEvents();

        return $warehouse;
    }

    // -----------------------------------------------------------------------
    // All warehouses (activeOnly = false)
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsMappedDtosForAllWarehouses(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $warehouses = [
            $this->buildWarehouse('WH001', active: true),
            $this->buildWarehouse('WH002', active: false),
        ];

        $this->repository
            ->expects(self::once())
            ->method('findByTenant')
            ->with(self::callback(fn ($t) => self::TENANT_ID === $t->toString()))
            ->willReturn($warehouses);

        $this->repository
            ->expects(self::never())
            ->method('findActiveByTenant');

        $query = new GetAllWarehouses($tenantId, activeOnly: false);
        $result = ($this->handler)($query);

        self::assertCount(2, $result);
        self::assertContainsOnlyInstancesOf(WarehouseDTO::class, $result);
        self::assertSame('WH001', $result[0]->code);
        self::assertSame('WH002', $result[1]->code);
    }

    #[Test]
    public function itReturnsEmptyArrayWhenNoWarehousesExist(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $this->repository
            ->method('findByTenant')
            ->willReturn([]);

        $query = new GetAllWarehouses($tenantId, activeOnly: false);
        $result = ($this->handler)($query);

        self::assertSame([], $result);
    }

    // -----------------------------------------------------------------------
    // Active-only filter
    // -----------------------------------------------------------------------

    #[Test]
    public function itDelegatesToFindActiveByTenantWhenActiveOnlyIsTrue(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $activeWarehouse = $this->buildWarehouse('WH-ACT', active: true);

        $this->repository
            ->expects(self::once())
            ->method('findActiveByTenant')
            ->with(self::callback(fn ($t) => self::TENANT_ID === $t->toString()))
            ->willReturn([$activeWarehouse]);

        $this->repository
            ->expects(self::never())
            ->method('findByTenant');

        $query = new GetAllWarehouses($tenantId, activeOnly: true);
        $result = ($this->handler)($query);

        self::assertCount(1, $result);
        self::assertInstanceOf(WarehouseDTO::class, $result[0]);
        self::assertSame('WH-ACT', $result[0]->code);
        self::assertTrue($result[0]->isActive);
    }

    #[Test]
    public function itReturnsEmptyArrayWhenNoActiveWarehousesExist(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $this->repository
            ->method('findActiveByTenant')
            ->willReturn([]);

        $query = new GetAllWarehouses($tenantId, activeOnly: true);
        $result = ($this->handler)($query);

        self::assertSame([], $result);
    }

    // -----------------------------------------------------------------------
    // Default query (activeOnly defaults to false)
    // -----------------------------------------------------------------------

    #[Test]
    public function itDefaultsToFindByTenantWhenActiveOnlyNotSpecified(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $this->repository
            ->expects(self::once())
            ->method('findByTenant')
            ->willReturn([]);

        $query = new GetAllWarehouses($tenantId);   // activeOnly defaults to false
        $result = ($this->handler)($query);

        self::assertSame([], $result);
    }
}
