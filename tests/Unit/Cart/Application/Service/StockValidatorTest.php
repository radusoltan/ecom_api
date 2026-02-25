<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cart\Application\Service;

use App\Cart\Application\Service\StockValidator;
use App\Cart\Domain\Exception\InsufficientStockException;
use App\Catalog\Domain\Model\ProductId;
use App\Inventory\Domain\Model\Quantity;
use App\Inventory\Domain\Model\StockItem;
use App\Inventory\Domain\Repository\StockItemRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class StockValidatorTest extends TestCase
{
    private StockItemRepositoryInterface $stockItemRepository;
    private StockValidator $validator;

    protected function setUp(): void
    {
        $this->stockItemRepository = $this->createMock(StockItemRepositoryInterface::class);
        $this->validator = new StockValidator($this->stockItemRepository);
    }

    public function testValidateStockAvailabilityPasses(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::generate();

        $this->stockItemRepository
            ->method('findByProduct')
            ->willReturn([$this->createStockItemWithAvailable(50)]);

        $this->validator->validateStockAvailability($productId, null, $tenantId, 5);
        $this->addToAssertionCount(1);
    }

    public function testValidateStockAvailabilityThrowsInsufficientStock(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::generate();

        $this->stockItemRepository
            ->method('findByProduct')
            ->willReturn([$this->createStockItemWithAvailable(2)]);

        $this->expectException(InsufficientStockException::class);

        $this->validator->validateStockAvailability($productId, null, $tenantId, 5);
    }

    public function testValidateStockAvailabilityThrowsWhenNoStock(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::generate();

        $this->stockItemRepository->method('findByProduct')->willReturn([]);

        $this->expectException(InsufficientStockException::class);

        $this->validator->validateStockAvailability($productId, null, $tenantId, 1);
    }

    public function testGetAvailableQuantityAcrossWarehouses(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::generate();

        $this->stockItemRepository
            ->method('findByProduct')
            ->willReturn([
                $this->createStockItemWithAvailable(20),
                $this->createStockItemWithAvailable(30),
                $this->createStockItemWithAvailable(10),
            ]);

        $result = $this->validator->getAvailableQuantity($productId, null, $tenantId);

        $this->assertSame(60, $result);
    }

    public function testGetAvailableQuantityReturnsZeroWhenNoStockItems(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::generate();

        $this->stockItemRepository->method('findByProduct')->willReturn([]);

        $result = $this->validator->getAvailableQuantity($productId, null, $tenantId);

        $this->assertSame(0, $result);
    }

    public function testIsInStockReturnsTrueWhenAvailable(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::generate();

        $this->stockItemRepository
            ->method('findByProduct')
            ->willReturn([$this->createStockItemWithAvailable(15)]);

        $this->assertTrue($this->validator->isInStock($productId, null, $tenantId));
    }

    public function testIsInStockReturnsFalseWhenOutOfStock(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::generate();

        $this->stockItemRepository
            ->method('findByProduct')
            ->willReturn([$this->createStockItemWithAvailable(0)]);

        $this->assertFalse($this->validator->isInStock($productId, null, $tenantId));
    }

    public function testGetStockDetailsWithSufficientStock(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::generate();

        $this->stockItemRepository
            ->method('findByProduct')
            ->willReturn([$this->createStockItemWithAvailable(50)]);

        $details = $this->validator->getStockDetails($productId, null, $tenantId);

        $this->assertSame(50, $details['available']);
        $this->assertTrue($details['isInStock']);
        $this->assertFalse($details['isLowStock']);
    }

    public function testGetStockDetailsWithLowStock(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::generate();

        $this->stockItemRepository
            ->method('findByProduct')
            ->willReturn([$this->createStockItemWithAvailable(5)]);

        $details = $this->validator->getStockDetails($productId, null, $tenantId);

        $this->assertSame(5, $details['available']);
        $this->assertTrue($details['isInStock']);
        $this->assertTrue($details['isLowStock']);
    }

    public function testGetStockDetailsWithNoStock(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::generate();

        $this->stockItemRepository->method('findByProduct')->willReturn([]);

        $details = $this->validator->getStockDetails($productId, null, $tenantId);

        $this->assertSame(0, $details['available']);
        $this->assertFalse($details['isInStock']);
        $this->assertFalse($details['isLowStock']);
    }

    private function createStockItemWithAvailable(int $available): StockItem
    {
        $quantity = $this->createMock(Quantity::class);
        $quantity->method('value')->willReturn($available);

        $stockItem = $this->createMock(StockItem::class);
        $stockItem->method('calculateAvailable')->willReturn($quantity);

        return $stockItem;
    }
}
