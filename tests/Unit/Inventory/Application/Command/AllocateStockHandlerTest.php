<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Application\Command;

use App\Catalog\Domain\Model\ProductId;
use App\Inventory\Application\Command\AllocateStock\AllocateStockCommand;
use App\Inventory\Application\Command\AllocateStock\AllocateStockHandler;
use App\Inventory\Domain\Model\Quantity;
use App\Inventory\Domain\Model\StockItem;
use App\Inventory\Domain\Model\StockItemId;
use App\Inventory\Domain\Model\WarehouseId;
use App\Inventory\Domain\Repository\StockItemRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AllocateStockHandler::class)]
#[CoversClass(AllocateStockCommand::class)]
final class AllocateStockHandlerTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    private StockItemRepositoryInterface $repository;
    private AllocateStockHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(StockItemRepositoryInterface::class);
        $this->handler = new AllocateStockHandler($this->repository);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function buildStockItem(
        ProductId $productId,
        WarehouseId $warehouseId,
        int $onHand,
    ): StockItem {
        $tenantId = TenantId::fromString(self::TENANT_ID);

        return StockItem::create(
            StockItemId::generate(),
            $tenantId,
            $productId,
            $warehouseId,
            Quantity::fromInt($onHand),
        );
    }

    // -----------------------------------------------------------------------
    // Happy path
    // -----------------------------------------------------------------------

    #[Test]
    public function itAllocatesStockSuccessfully(): void
    {
        $productId = ProductId::generate();
        $warehouseId = WarehouseId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $stockItem = $this->buildStockItem($productId, $warehouseId, 100);

        $this->repository
            ->method('findByProductAndWarehouse')
            ->willReturn($stockItem);

        $this->repository
            ->expects(self::once())
            ->method('save');

        $command = new AllocateStockCommand(
            productId: $productId->toString(),
            warehouseId: $warehouseId,
            quantity: Quantity::fromInt(10),
            orderId: 'order-001',
            tenantId: $tenantId,
        );

        ($this->handler)($command);

        self::assertSame(10, $stockItem->allocated()->value());
        self::assertSame(90, $stockItem->calculateAvailable()->value());
    }

    #[Test]
    public function itAllocatesFullAvailableQuantity(): void
    {
        $productId = ProductId::generate();
        $warehouseId = WarehouseId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $stockItem = $this->buildStockItem($productId, $warehouseId, 50);

        $this->repository
            ->method('findByProductAndWarehouse')
            ->willReturn($stockItem);

        $this->repository
            ->expects(self::once())
            ->method('save');

        $command = new AllocateStockCommand(
            productId: $productId->toString(),
            warehouseId: $warehouseId,
            quantity: Quantity::fromInt(50),
            orderId: 'order-002',
            tenantId: $tenantId,
        );

        ($this->handler)($command);

        self::assertSame(50, $stockItem->allocated()->value());
        self::assertSame(0, $stockItem->calculateAvailable()->value());
    }

    // -----------------------------------------------------------------------
    // Not found
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsWhenStockItemNotFound(): void
    {
        $productId = ProductId::generate();
        $warehouseId = WarehouseId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $this->repository
            ->method('findByProductAndWarehouse')
            ->willReturn(null);

        $this->repository
            ->expects(self::never())
            ->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Stock item not found');

        $command = new AllocateStockCommand(
            productId: $productId->toString(),
            warehouseId: $warehouseId,
            quantity: Quantity::fromInt(5),
            orderId: 'order-404',
            tenantId: $tenantId,
        );

        ($this->handler)($command);
    }

    // -----------------------------------------------------------------------
    // Insufficient stock guard
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsWhenInsufficientStockToAllocate(): void
    {
        $productId = ProductId::generate();
        $warehouseId = WarehouseId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $stockItem = $this->buildStockItem($productId, $warehouseId, 5);

        $this->repository
            ->method('findByProductAndWarehouse')
            ->willReturn($stockItem);

        $this->repository
            ->expects(self::never())
            ->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Insufficient stock');

        $command = new AllocateStockCommand(
            productId: $productId->toString(),
            warehouseId: $warehouseId,
            quantity: Quantity::fromInt(10),
            orderId: 'order-999',
            tenantId: $tenantId,
        );

        ($this->handler)($command);
    }

    // -----------------------------------------------------------------------
    // productId string conversion
    // -----------------------------------------------------------------------

    #[Test]
    public function itBuildsProductIdFromStringCommandField(): void
    {
        $productId = ProductId::generate();
        $warehouseId = WarehouseId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $stockItem = $this->buildStockItem($productId, $warehouseId, 20);

        // Handler calls ProductId::fromString($command->productId), so the string
        // value must round-trip correctly.
        $this->repository
            ->expects(self::once())
            ->method('findByProductAndWarehouse')
            ->with(
                self::callback(fn ($p) => $p instanceof ProductId && $p->toString() === $productId->toString()),
                self::anything(),
                self::anything(),
            )
            ->willReturn($stockItem);

        $this->repository->method('save');

        $command = new AllocateStockCommand(
            productId: $productId->toString(),
            warehouseId: $warehouseId,
            quantity: Quantity::fromInt(1),
            orderId: 'order-str',
            tenantId: $tenantId,
        );

        ($this->handler)($command);
    }
}
