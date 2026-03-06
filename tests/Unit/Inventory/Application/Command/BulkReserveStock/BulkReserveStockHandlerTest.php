<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Application\Command\BulkReserveStock;

use App\Catalog\Domain\Model\ProductId;
use App\Inventory\Application\Command\BulkReserveStock\BulkReserveStockCommand;
use App\Inventory\Application\Command\BulkReserveStock\BulkReserveStockHandler;
use App\Inventory\Application\Command\BulkReserveStock\BulkReserveStockItem;
use App\Inventory\Application\Command\BulkReserveStock\BulkReserveStockResult;
use App\Inventory\Domain\Model\Quantity;
use App\Inventory\Domain\Model\StockItem;
use App\Inventory\Domain\Model\StockItemId;
use App\Inventory\Domain\Model\WarehouseId;
use App\Inventory\Domain\Repository\StockItemRepositoryInterface;
use App\Inventory\Domain\Repository\StockReservationRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class BulkReserveStockHandlerTest extends TestCase
{
    private StockItemRepositoryInterface $stockItemRepository;
    private StockReservationRepositoryInterface $reservationRepository;
    private LoggerInterface $logger;
    private BulkReserveStockHandler $handler;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->stockItemRepository = $this->createMock(StockItemRepositoryInterface::class);
        $this->reservationRepository = $this->createMock(StockReservationRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new BulkReserveStockHandler(
            $this->stockItemRepository,
            $this->reservationRepository,
            $this->logger,
        );

        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
    }

    public function testHandleRecordsFailedItemWhenStockItemNotFound(): void
    {
        $productId = ProductId::generate();
        $warehouseId = WarehouseId::generate();
        $quantity = Quantity::fromInt(5);

        $item = new BulkReserveStockItem(
            productId: $productId,
            warehouseId: $warehouseId,
            quantity: $quantity,
        );

        $command = new BulkReserveStockCommand(
            items: [$item],
            referenceId: 'order-ref-123',
            tenantId: $this->tenantId,
        );

        $this->stockItemRepository
            ->expects($this->once())
            ->method('findByProductAndWarehouse')
            ->with($productId, $warehouseId, $this->tenantId)
            ->willReturn(null);

        $this->stockItemRepository->expects($this->never())->method('save');
        $this->reservationRepository->expects($this->never())->method('save');

        $result = ($this->handler)($command);

        $this->assertInstanceOf(BulkReserveStockResult::class, $result);
        $this->assertSame('order-ref-123', $result->referenceId);
        $this->assertSame(1, $result->totalItems);
        $this->assertSame(0, $result->successCount);
        $this->assertSame(1, $result->failureCount);
        $this->assertCount(0, $result->reservedItems);
        $this->assertCount(1, $result->failedItems);
        $this->assertSame('Stock item not found', $result->failedItems[0]->error);
        $this->assertSame($productId->toString(), $result->failedItems[0]->productId);
        $this->assertFalse($result->failedItems[0]->success);
        $this->assertTrue($result->isFullyFailed());
    }

    public function testHandleRecordsFailedItemWhenDomainExceptionOnReserve(): void
    {
        $productId = ProductId::generate();
        $warehouseId = WarehouseId::generate();
        $quantity = Quantity::fromInt(100);

        $item = new BulkReserveStockItem(
            productId: $productId,
            warehouseId: $warehouseId,
            quantity: $quantity,
        );

        $command = new BulkReserveStockCommand(
            items: [$item],
            referenceId: 'order-ref-456',
            tenantId: $this->tenantId,
        );

        $stockItem = $this->createMock(StockItem::class);
        $stockItem
            ->expects($this->once())
            ->method('reserve')
            ->with($quantity, 'order-ref-456')
            ->willThrowException(new \DomainException('Insufficient stock to reserve. Available: 10, Requested: 100'));

        $this->stockItemRepository
            ->method('findByProductAndWarehouse')
            ->willReturn($stockItem);

        $this->stockItemRepository->expects($this->never())->method('save');
        $this->reservationRepository->expects($this->never())->method('save');

        $this->logger
            ->expects($this->atLeastOnce())
            ->method('warning')
            ->with(
                'Bulk reservation: Failed to reserve stock',
                $this->arrayHasKey('error')
            );

        $result = ($this->handler)($command);

        $this->assertSame(0, $result->successCount);
        $this->assertSame(1, $result->failureCount);
        $this->assertCount(1, $result->failedItems);
        $this->assertStringContainsString('Insufficient stock', $result->failedItems[0]->error);
        $this->assertFalse($result->failedItems[0]->success);
        $this->assertTrue($result->isFullyFailed());
    }

    public function testHandleReturnsFullyFailedWhenAllItemsNotFound(): void
    {
        $items = [
            new BulkReserveStockItem(ProductId::generate(), WarehouseId::generate(), Quantity::fromInt(5)),
            new BulkReserveStockItem(ProductId::generate(), WarehouseId::generate(), Quantity::fromInt(3)),
        ];

        $command = new BulkReserveStockCommand(
            items: $items,
            referenceId: 'order-ref-789',
            tenantId: $this->tenantId,
        );

        $this->stockItemRepository
            ->method('findByProductAndWarehouse')
            ->willReturn(null);

        $result = ($this->handler)($command);

        $this->assertSame(2, $result->totalItems);
        $this->assertSame(0, $result->successCount);
        $this->assertSame(2, $result->failureCount);
        $this->assertFalse($result->isFullySuccessful());
        $this->assertFalse($result->isPartiallySuccessful());
        $this->assertTrue($result->isFullyFailed());
    }

    public function testHandleReturnsPartiallySuccessfulWhenSomeItemsNotFound(): void
    {
        $productId1 = ProductId::generate();
        $warehouseId1 = WarehouseId::generate();
        $productId2 = ProductId::generate();
        $warehouseId2 = WarehouseId::generate();

        $items = [
            new BulkReserveStockItem($productId1, $warehouseId1, Quantity::fromInt(5)),
            new BulkReserveStockItem($productId2, $warehouseId2, Quantity::fromInt(3)),
        ];

        $command = new BulkReserveStockCommand(
            items: $items,
            referenceId: 'order-partial',
            tenantId: $this->tenantId,
        );

        // Both items fail: first by domain exception, second by not found
        $stockItem = $this->createMock(StockItem::class);
        $stockItem
            ->expects($this->once())
            ->method('reserve')
            ->willThrowException(new \DomainException('Insufficient stock'));
        $stockItem->method('id')->willReturn(StockItemId::generate());

        $this->stockItemRepository
            ->method('findByProductAndWarehouse')
            ->willReturnCallback(function (ProductId $pId) use ($productId1, $stockItem) {
                if ($pId->toString() === $productId1->toString()) {
                    return $stockItem;
                }

                return null; // Second item not found
            });

        $this->stockItemRepository->expects($this->never())->method('save');
        $this->reservationRepository->expects($this->never())->method('save');

        $result = ($this->handler)($command);

        $this->assertSame(2, $result->totalItems);
        $this->assertSame(0, $result->successCount);
        $this->assertSame(2, $result->failureCount);
        $this->assertFalse($result->isPartiallySuccessful());
        $this->assertFalse($result->isFullySuccessful());
        $this->assertTrue($result->isFullyFailed());
        $this->assertCount(0, $result->reservedItems);
        $this->assertCount(2, $result->failedItems);
    }

    public function testHandleWithEmptyItemsReturnsZeroCounts(): void
    {
        $command = new BulkReserveStockCommand(
            items: [],
            referenceId: 'empty-ref',
            tenantId: $this->tenantId,
        );

        $this->stockItemRepository->expects($this->never())->method('findByProductAndWarehouse');

        $result = ($this->handler)($command);

        $this->assertSame(0, $result->totalItems);
        $this->assertSame(0, $result->successCount);
        $this->assertSame(0, $result->failureCount);
        $this->assertEmpty($result->reservedItems);
        $this->assertEmpty($result->failedItems);
        $this->assertTrue($result->isFullySuccessful());
    }

    public function testHandlePropagatesUnexpectedThrowable(): void
    {
        $item = new BulkReserveStockItem(
            productId: ProductId::generate(),
            warehouseId: WarehouseId::generate(),
            quantity: Quantity::fromInt(5),
        );

        $command = new BulkReserveStockCommand(
            items: [$item],
            referenceId: 'ref-fatal',
            tenantId: $this->tenantId,
        );

        $this->stockItemRepository
            ->method('findByProductAndWarehouse')
            ->willThrowException(new \Error('Fatal error'));

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('Bulk reservation: Unexpected error', $this->arrayHasKey('error'));

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Fatal error');

        ($this->handler)($command);
    }

    public function testHandleFailedItemContainsCorrectProductAndWarehouseIds(): void
    {
        $productId = ProductId::generate();
        $warehouseId = WarehouseId::generate();
        $quantity = Quantity::fromInt(2);

        $item = new BulkReserveStockItem($productId, $warehouseId, $quantity);

        $command = new BulkReserveStockCommand(
            items: [$item],
            referenceId: 'ref-check',
            tenantId: $this->tenantId,
        );

        $this->stockItemRepository->method('findByProductAndWarehouse')->willReturn(null);

        $result = ($this->handler)($command);

        $failedItem = $result->failedItems[0];
        $this->assertSame($productId->toString(), $failedItem->productId);
        $this->assertSame($warehouseId->toString(), $failedItem->warehouseId);
        $this->assertSame(2, $failedItem->quantity);
        $this->assertFalse($failedItem->success);
        $this->assertNull($failedItem->availableQuantity);
    }
}
