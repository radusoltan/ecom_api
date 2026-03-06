<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Application\DTO;

use App\Inventory\Application\Command\BulkReserveStock\BulkReserveStockResult;
use App\Inventory\Application\Command\BulkReserveStock\BulkReserveStockResultItem;
use App\Inventory\Application\DTO\InventoryStatsDto;
use App\Inventory\Application\DTO\WarehouseDTO;
use App\Inventory\Application\Query\CheckStockAvailability\ItemAvailabilityResult;
use App\Inventory\Application\Query\CheckStockAvailability\StockAvailabilityResult;
use App\Inventory\Application\Query\GetStockByProduct\StockItemDTO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(BulkReserveStockResult::class)]
#[CoversClass(BulkReserveStockResultItem::class)]
#[CoversClass(InventoryStatsDto::class)]
#[CoversClass(WarehouseDTO::class)]
#[CoversClass(ItemAvailabilityResult::class)]
#[CoversClass(StockAvailabilityResult::class)]
#[CoversClass(StockItemDTO::class)]
final class InventoryDTOsTest extends TestCase
{
    // --- WarehouseDTO ---

    #[Test]
    public function warehouseDtoConstruction(): void
    {
        $dto = new WarehouseDTO(
            id: 'wh-1',
            tenantId: 't-1',
            code: 'WH-MAIN',
            name: 'Main Warehouse',
            address: ['street' => '123 Main St', 'city' => 'Springfield', 'state' => 'IL', 'postalCode' => '62701', 'country' => 'US'],
            priority: 1,
            isActive: true,
            createdAt: '2025-01-01T00:00:00+00:00',
            updatedAt: '2025-01-01T00:00:00+00:00',
        );

        self::assertSame('wh-1', $dto->id);
        self::assertSame('t-1', $dto->tenantId);
        self::assertSame('WH-MAIN', $dto->code);
        self::assertSame('Main Warehouse', $dto->name);
        self::assertSame('Springfield', $dto->address['city']);
        self::assertSame(1, $dto->priority);
        self::assertTrue($dto->isActive);
    }

    // --- InventoryStatsDto ---

    #[Test]
    public function inventoryStatsDtoConstruction(): void
    {
        $dto = new InventoryStatsDto(
            summary: ['total_products' => 100, 'total_stock' => 5000],
            warehouses: [['id' => 'wh-1', 'name' => 'Main']],
            stockLevels: ['in_stock' => 80, 'low_stock' => 15, 'out_of_stock' => 5],
            lowStockAlerts: [['product_id' => 'p-1', 'available' => 3]],
            topProducts: [['product_id' => 'p-2', 'sales' => 100]],
            stockMovement: ['received' => 500, 'shipped' => 400],
            generatedAt: '2025-06-15T10:00:00+00:00',
        );

        self::assertSame(100, $dto->summary['total_products']);
        self::assertCount(1, $dto->warehouses);
        self::assertSame(80, $dto->stockLevels['in_stock']);
        self::assertCount(1, $dto->lowStockAlerts);
        self::assertCount(1, $dto->topProducts);
        self::assertSame(500, $dto->stockMovement['received']);
        self::assertSame('2025-06-15T10:00:00+00:00', $dto->generatedAt);
    }

    // --- BulkReserveStockResult ---

    #[Test]
    public function bulkReserveFullSuccess(): void
    {
        $reserved = [
            new BulkReserveStockResultItem('p-1', 'wh-1', 5, true),
            new BulkReserveStockResultItem('p-2', 'wh-1', 3, true),
        ];

        $result = new BulkReserveStockResult(
            referenceId: 'order-1',
            totalItems: 2,
            successCount: 2,
            failureCount: 0,
            reservedItems: $reserved,
            failedItems: [],
        );

        self::assertTrue($result->isFullySuccessful());
        self::assertFalse($result->isPartiallySuccessful());
        self::assertFalse($result->isFullyFailed());
        self::assertSame('order-1', $result->referenceId);
        self::assertSame(2, $result->totalItems);
        self::assertCount(2, $result->reservedItems);
        self::assertCount(0, $result->failedItems);
    }

    #[Test]
    public function bulkReservePartialSuccess(): void
    {
        $reserved = [new BulkReserveStockResultItem('p-1', 'wh-1', 5, true)];
        $failed = [new BulkReserveStockResultItem('p-2', 'wh-1', 3, false, 'Insufficient stock', 1)];

        $result = new BulkReserveStockResult(
            referenceId: 'order-2',
            totalItems: 2,
            successCount: 1,
            failureCount: 1,
            reservedItems: $reserved,
            failedItems: $failed,
        );

        self::assertFalse($result->isFullySuccessful());
        self::assertTrue($result->isPartiallySuccessful());
        self::assertFalse($result->isFullyFailed());
    }

    #[Test]
    public function bulkReserveFullFailure(): void
    {
        $failed = [
            new BulkReserveStockResultItem('p-1', 'wh-1', 5, false, 'Out of stock', 0),
        ];

        $result = new BulkReserveStockResult(
            referenceId: 'order-3',
            totalItems: 1,
            successCount: 0,
            failureCount: 1,
            reservedItems: [],
            failedItems: $failed,
        );

        self::assertFalse($result->isFullySuccessful());
        self::assertFalse($result->isPartiallySuccessful());
        self::assertTrue($result->isFullyFailed());
    }

    // --- BulkReserveStockResultItem ---

    #[Test]
    public function bulkReserveResultItemSuccess(): void
    {
        $item = new BulkReserveStockResultItem(
            productId: 'p-1',
            warehouseId: 'wh-1',
            quantity: 10,
            success: true,
        );

        self::assertSame('p-1', $item->productId);
        self::assertSame('wh-1', $item->warehouseId);
        self::assertSame(10, $item->quantity);
        self::assertTrue($item->success);
        self::assertNull($item->error);
        self::assertNull($item->availableQuantity);
    }

    #[Test]
    public function bulkReserveResultItemFailure(): void
    {
        $item = new BulkReserveStockResultItem(
            productId: 'p-2',
            warehouseId: 'wh-2',
            quantity: 50,
            success: false,
            error: 'Insufficient stock',
            availableQuantity: 10,
        );

        self::assertFalse($item->success);
        self::assertSame('Insufficient stock', $item->error);
        self::assertSame(10, $item->availableQuantity);
    }

    // --- StockAvailabilityResult ---

    #[Test]
    public function stockAvailabilityAvailable(): void
    {
        $items = [
            new ItemAvailabilityResult('p-1', null, 5, 20, true),
            new ItemAvailabilityResult('p-2', 'v-1', 3, 10, true),
        ];

        $result = new StockAvailabilityResult(available: true, items: $items);

        self::assertTrue($result->available);
        self::assertCount(2, $result->items);
    }

    #[Test]
    public function stockAvailabilityUnavailable(): void
    {
        $items = [
            new ItemAvailabilityResult('p-1', null, 5, 2, false),
        ];

        $result = new StockAvailabilityResult(available: false, items: $items);

        self::assertFalse($result->available);
    }

    // --- ItemAvailabilityResult ---

    #[Test]
    public function itemAvailabilityResultConstruction(): void
    {
        $item = new ItemAvailabilityResult(
            productId: 'p-1',
            variantId: 'v-1',
            requestedQuantity: 5,
            availableQuantity: 20,
            available: true,
        );

        self::assertSame('p-1', $item->productId);
        self::assertSame('v-1', $item->variantId);
        self::assertSame(5, $item->requestedQuantity);
        self::assertSame(20, $item->availableQuantity);
        self::assertTrue($item->available);
    }

    #[Test]
    public function itemAvailabilityResultNullVariant(): void
    {
        $item = new ItemAvailabilityResult(
            productId: 'p-2',
            variantId: null,
            requestedQuantity: 10,
            availableQuantity: 5,
            available: false,
        );

        self::assertNull($item->variantId);
        self::assertFalse($item->available);
    }

    // --- StockItemDTO ---

    #[Test]
    public function stockItemDtoConstruction(): void
    {
        $now = new \DateTimeImmutable();
        $dto = new StockItemDTO(
            stockItemId: 'si-1',
            tenantId: 't-1',
            productId: 'p-1',
            warehouseId: 'wh-1',
            onHand: 100,
            reserved: 20,
            allocated: 10,
            available: 70,
            lowStockThreshold: 15,
            isLowStock: false,
            createdAt: $now,
            updatedAt: $now,
        );

        self::assertSame('si-1', $dto->stockItemId);
        self::assertSame('t-1', $dto->tenantId);
        self::assertSame('p-1', $dto->productId);
        self::assertSame('wh-1', $dto->warehouseId);
        self::assertSame(100, $dto->onHand);
        self::assertSame(20, $dto->reserved);
        self::assertSame(10, $dto->allocated);
        self::assertSame(70, $dto->available);
        self::assertSame(15, $dto->lowStockThreshold);
        self::assertFalse($dto->isLowStock);
    }

    #[Test]
    public function stockItemDtoLowStock(): void
    {
        $now = new \DateTimeImmutable();
        $dto = new StockItemDTO(
            stockItemId: 'si-2',
            tenantId: 't-1',
            productId: 'p-2',
            warehouseId: 'wh-1',
            onHand: 10,
            reserved: 5,
            allocated: 3,
            available: 2,
            lowStockThreshold: 10,
            isLowStock: true,
            createdAt: $now,
            updatedAt: $now,
        );

        self::assertTrue($dto->isLowStock);
        self::assertSame(2, $dto->available);
    }
}
