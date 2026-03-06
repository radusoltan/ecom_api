<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Application\Query;

use App\Inventory\Application\DTO\WarehouseDTO;
use App\Inventory\Application\Query\GetWarehouseById\GetWarehouseById;
use App\Inventory\Application\Query\GetWarehouseById\GetWarehouseByIdHandler;
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

#[CoversClass(GetWarehouseByIdHandler::class)]
#[CoversClass(GetWarehouseById::class)]
final class GetWarehouseByIdHandlerTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    private WarehouseRepositoryInterface $repository;
    private GetWarehouseByIdHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(WarehouseRepositoryInterface::class);
        $this->handler = new GetWarehouseByIdHandler($this->repository);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function buildWarehouse(WarehouseId $id): Warehouse
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $warehouse = Warehouse::create(
            $id,
            $tenantId,
            WarehouseCode::fromString('WH001'),
            WarehouseName::fromString('Main Warehouse'),
            Address::create('10 Main St', 'Seattle', 'WA', '98101', 'US'),
        );
        $warehouse->popEvents();

        return $warehouse;
    }

    // -----------------------------------------------------------------------
    // Found
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsWarehouseDtoWhenFound(): void
    {
        $warehouseId = WarehouseId::generate();
        $warehouse = $this->buildWarehouse($warehouseId);

        $this->repository
            ->method('findById')
            ->with(self::callback(fn ($id) => $id->toString() === $warehouseId->toString()))
            ->willReturn($warehouse);

        $query = new GetWarehouseById($warehouseId);
        $result = ($this->handler)($query);

        self::assertInstanceOf(WarehouseDTO::class, $result);
        self::assertSame($warehouseId->toString(), $result->id);
        self::assertSame(self::TENANT_ID, $result->tenantId);
        self::assertSame('WH001', $result->code);
        self::assertSame('Main Warehouse', $result->name);
        self::assertTrue($result->isActive);
    }

    #[Test]
    public function itMapsAddressToArray(): void
    {
        $warehouseId = WarehouseId::generate();
        $warehouse = $this->buildWarehouse($warehouseId);

        $this->repository
            ->method('findById')
            ->willReturn($warehouse);

        $result = ($this->handler)(new GetWarehouseById($warehouseId));

        self::assertIsArray($result->address);
        self::assertArrayHasKey('street', $result->address);
        self::assertArrayHasKey('city', $result->address);
        self::assertArrayHasKey('state', $result->address);
        self::assertArrayHasKey('postalCode', $result->address);
        self::assertArrayHasKey('country', $result->address);
        self::assertSame('10 Main St', $result->address['street']);
        self::assertSame('US', $result->address['country']);
    }

    // -----------------------------------------------------------------------
    // Not found
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsNullWhenWarehouseNotFound(): void
    {
        $warehouseId = WarehouseId::generate();

        $this->repository
            ->method('findById')
            ->willReturn(null);

        $result = ($this->handler)(new GetWarehouseById($warehouseId));

        self::assertNull($result);
    }
}
