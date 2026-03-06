<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Application\Command;

use App\Catalog\Domain\Model\ProductId;
use App\Inventory\Application\Command\AdjustStock\AdjustStockCommand;
use App\Inventory\Application\Command\AdjustStock\AdjustStockHandler;
use App\Inventory\Domain\Model\Quantity;
use App\Inventory\Domain\Model\StockItem;
use App\Inventory\Domain\Model\StockItemId;
use App\Inventory\Domain\Model\WarehouseId;
use App\Inventory\Domain\Repository\StockItemRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AdjustStockHandler::class)]
#[CoversClass(AdjustStockCommand::class)]
final class AdjustStockHandlerTest extends TestCase
{
    private StockItemRepositoryInterface $repository;
    private AdjustStockHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(StockItemRepositoryInterface::class);
        $this->handler = new AdjustStockHandler($this->repository);
    }

    #[Test]
    public function itAdjustsStockQuantity(): void
    {
        $productId = ProductId::generate();
        $warehouseId = WarehouseId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $stockItem = StockItem::create(
            StockItemId::generate(),
            $tenantId,
            $productId,
            $warehouseId,
            Quantity::fromInt(50),
        );

        $this->repository->method('findByProductAndWarehouse')
            ->with($productId, $warehouseId, $tenantId)
            ->willReturn($stockItem);

        $this->repository->expects(self::once())->method('save');

        $command = new AdjustStockCommand(
            $productId,
            $warehouseId,
            Quantity::fromInt(100),
            'Restocking',
            $tenantId,
        );

        ($this->handler)($command);

        self::assertSame(100, $stockItem->onHand()->value());
    }

    #[Test]
    public function itThrowsWhenStockItemNotFound(): void
    {
        $productId = ProductId::generate();
        $warehouseId = WarehouseId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $this->repository->method('findByProductAndWarehouse')->willReturn(null);

        $command = new AdjustStockCommand(
            $productId,
            $warehouseId,
            Quantity::fromInt(100),
            'Restocking',
            $tenantId,
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Stock item not found');

        ($this->handler)($command);
    }

    #[Test]
    public function itRejectsAdjustmentBelowCommittedQuantity(): void
    {
        $productId = ProductId::generate();
        $warehouseId = WarehouseId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $stockItem = StockItem::create(
            StockItemId::generate(),
            $tenantId,
            $productId,
            $warehouseId,
            Quantity::fromInt(50),
        );
        $stockItem->reserve(Quantity::fromInt(20), 'res-1');

        $this->repository->method('findByProductAndWarehouse')->willReturn($stockItem);

        $command = new AdjustStockCommand(
            $productId,
            $warehouseId,
            Quantity::fromInt(10), // less than reserved 20
            'Correction',
            $tenantId,
        );

        $this->expectException(\DomainException::class);
        ($this->handler)($command);
    }
}
