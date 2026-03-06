<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Application\Command;

use App\Catalog\Domain\Model\ProductId;
use App\Inventory\Application\Command\ReleaseStock\ReleaseStockCommand;
use App\Inventory\Application\Command\ReleaseStock\ReleaseStockHandler;
use App\Inventory\Domain\Model\Quantity;
use App\Inventory\Domain\Model\StockItem;
use App\Inventory\Domain\Model\StockItemId;
use App\Inventory\Domain\Model\WarehouseId;
use App\Inventory\Domain\Repository\StockItemRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReleaseStockHandler::class)]
#[CoversClass(ReleaseStockCommand::class)]
final class ReleaseStockHandlerTest extends TestCase
{
    private StockItemRepositoryInterface $repository;
    private ReleaseStockHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(StockItemRepositoryInterface::class);
        $this->handler = new ReleaseStockHandler($this->repository);
    }

    #[Test]
    public function itReleasesReservedStock(): void
    {
        $productId = ProductId::generate();
        $warehouseId = WarehouseId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $stockItem = StockItem::create(
            StockItemId::generate(),
            $tenantId,
            $productId,
            $warehouseId,
            Quantity::fromInt(100),
        );
        $stockItem->reserve(Quantity::fromInt(20), 'res-1');

        $this->repository->method('findByProductAndWarehouse')->willReturn($stockItem);
        $this->repository->expects(self::once())->method('save');

        $command = new ReleaseStockCommand(
            $productId,
            $warehouseId,
            Quantity::fromInt(10),
            'Cart expired',
            $tenantId,
        );

        ($this->handler)($command);

        self::assertSame(10, $stockItem->reserved()->value());
    }

    #[Test]
    public function itThrowsWhenStockItemNotFound(): void
    {
        $command = new ReleaseStockCommand(
            ProductId::generate(),
            WarehouseId::generate(),
            Quantity::fromInt(5),
            'cancelled',
            TenantId::fromString('00000000-0000-4000-8000-000000000001'),
        );

        $this->repository->method('findByProductAndWarehouse')->willReturn(null);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Stock item not found');

        ($this->handler)($command);
    }
}
