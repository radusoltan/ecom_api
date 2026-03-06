<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Application\Command;

use App\Catalog\Domain\Model\ProductId;
use App\Inventory\Application\Command\TransferStock\TransferStockCommand;
use App\Inventory\Application\Command\TransferStock\TransferStockCommandHandler;
use App\Inventory\Domain\Event\StockTransferred;
use App\Inventory\Domain\Model\Quantity;
use App\Inventory\Domain\Model\StockItem;
use App\Inventory\Domain\Model\StockItemId;
use App\Inventory\Domain\Model\WarehouseId;
use App\Inventory\Domain\Repository\StockItemRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TransferStockCommandHandler::class)]
#[CoversClass(TransferStockCommand::class)]
final class TransferStockCommandHandlerTest extends TestCase
{
    private StockItemRepositoryInterface $repository;
    private TransferStockCommandHandler $handler;
    private TenantId $tenantId;
    private ProductId $productId;
    private WarehouseId $sourceWarehouseId;
    private WarehouseId $destinationWarehouseId;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(StockItemRepositoryInterface::class);
        $this->handler = new TransferStockCommandHandler($this->repository);
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $this->productId = ProductId::generate();
        $this->sourceWarehouseId = WarehouseId::generate();
        $this->destinationWarehouseId = WarehouseId::generate();
    }

    #[Test]
    public function itTransfersStockFromSourceToExistingDestination(): void
    {
        $sourceStockItem = StockItem::create(
            StockItemId::generate(),
            $this->tenantId,
            $this->productId,
            $this->sourceWarehouseId,
            Quantity::fromInt(100),
        );

        $destinationStockItem = StockItem::create(
            StockItemId::generate(),
            $this->tenantId,
            $this->productId,
            $this->destinationWarehouseId,
            Quantity::fromInt(20),
        );

        $this->repository
            ->method('findByProductAndWarehouse')
            ->willReturnCallback(function (ProductId $productId, WarehouseId $warehouseId, TenantId $tenantId) use ($sourceStockItem, $destinationStockItem): ?StockItem {
                if ($warehouseId->toString() === $this->sourceWarehouseId->toString()) {
                    return $sourceStockItem;
                }
                if ($warehouseId->toString() === $this->destinationWarehouseId->toString()) {
                    return $destinationStockItem;
                }

                return null;
            });

        $this->repository->expects(self::exactly(2))->method('save');

        $command = new TransferStockCommand(
            $this->productId,
            $this->sourceWarehouseId,
            $this->destinationWarehouseId,
            Quantity::fromInt(30),
            $this->tenantId,
        );

        ($this->handler)($command);

        self::assertSame(70, $sourceStockItem->onHand()->value());
        self::assertSame(50, $destinationStockItem->onHand()->value());

        $sourceEvents = $sourceStockItem->popEvents();
        self::assertCount(1, $sourceEvents);
        self::assertInstanceOf(StockTransferred::class, $sourceEvents[0]);
        self::assertSame(30, $sourceEvents[0]->quantity->value());
    }

    #[Test]
    public function itCreatesDestinationStockItemWhenItDoesNotExist(): void
    {
        $sourceStockItem = StockItem::create(
            StockItemId::generate(),
            $this->tenantId,
            $this->productId,
            $this->sourceWarehouseId,
            Quantity::fromInt(100),
        );

        $this->repository
            ->method('findByProductAndWarehouse')
            ->willReturnCallback(function (ProductId $productId, WarehouseId $warehouseId, TenantId $tenantId) use ($sourceStockItem): ?StockItem {
                if ($warehouseId->toString() === $this->sourceWarehouseId->toString()) {
                    return $sourceStockItem;
                }

                return null;
            });

        $savedItems = [];
        $this->repository
            ->expects(self::exactly(2))
            ->method('save')
            ->willReturnCallback(function (StockItem $item) use (&$savedItems): void {
                $savedItems[] = $item;
            });

        $command = new TransferStockCommand(
            $this->productId,
            $this->sourceWarehouseId,
            $this->destinationWarehouseId,
            Quantity::fromInt(25),
            $this->tenantId,
        );

        ($this->handler)($command);

        self::assertSame(75, $sourceStockItem->onHand()->value());

        // The newly created destination stock item should have been saved
        self::assertCount(2, $savedItems);
        $newDestination = $savedItems[1];
        self::assertSame(25, $newDestination->onHand()->value());
        self::assertTrue($newDestination->productId()->equals($this->productId));
        self::assertTrue($newDestination->warehouseId()->equals($this->destinationWarehouseId));
        self::assertTrue($newDestination->tenantId()->equals($this->tenantId));
    }

    #[Test]
    public function itThrowsWhenSourceStockItemNotFound(): void
    {
        $this->repository
            ->method('findByProductAndWarehouse')
            ->willReturn(null);

        $command = new TransferStockCommand(
            $this->productId,
            $this->sourceWarehouseId,
            $this->destinationWarehouseId,
            Quantity::fromInt(10),
            $this->tenantId,
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Stock item not found for product');

        ($this->handler)($command);
    }

    #[Test]
    public function itThrowsWhenSourceHasInsufficientStock(): void
    {
        $sourceStockItem = StockItem::create(
            StockItemId::generate(),
            $this->tenantId,
            $this->productId,
            $this->sourceWarehouseId,
            Quantity::fromInt(10),
        );

        $this->repository
            ->method('findByProductAndWarehouse')
            ->willReturn($sourceStockItem);

        $command = new TransferStockCommand(
            $this->productId,
            $this->sourceWarehouseId,
            $this->destinationWarehouseId,
            Quantity::fromInt(50),
            $this->tenantId,
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Insufficient available stock to transfer');

        ($this->handler)($command);
    }

    #[Test]
    public function itThrowsWhenSourceAndDestinationWarehouseAreSame(): void
    {
        $command = new TransferStockCommand(
            $this->productId,
            $this->sourceWarehouseId,
            $this->sourceWarehouseId,
            Quantity::fromInt(10),
            $this->tenantId,
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Source and destination warehouses must be different');

        ($this->handler)($command);
    }

    #[Test]
    public function itDoesNotSaveWhenSameWarehouseCheckFails(): void
    {
        $this->repository->expects(self::never())->method('save');
        $this->repository->expects(self::never())->method('findByProductAndWarehouse');

        $command = new TransferStockCommand(
            $this->productId,
            $this->sourceWarehouseId,
            $this->sourceWarehouseId,
            Quantity::fromInt(10),
            $this->tenantId,
        );

        try {
            ($this->handler)($command);
        } catch (\DomainException) {
            // Expected
        }
    }
}
