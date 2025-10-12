<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\Model;

use App\Catalog\Domain\Model\Stock;
use PHPUnit\Framework\TestCase;

final class StockTest extends TestCase
{
    public function testCreateStock(): void
    {
        $stock = Stock::create(100, true, false);

        $this->assertSame(100, $stock->quantity());
        $this->assertTrue($stock->trackInventory());
        $this->assertFalse($stock->allowBackorder());
    }

    public function testCreateStockWithDefaults(): void
    {
        $stock = Stock::create(50);

        $this->assertSame(50, $stock->quantity());
        $this->assertTrue($stock->trackInventory());
        $this->assertFalse($stock->allowBackorder());
    }

    public function testIncreaseStock(): void
    {
        $stock = Stock::create(10);
        $newStock = $stock->increase(5);

        $this->assertSame(10, $stock->quantity());
        $this->assertSame(15, $newStock->quantity());
    }

    public function testDecreaseStock(): void
    {
        $stock = Stock::create(10);
        $newStock = $stock->decrease(3);

        $this->assertSame(10, $stock->quantity());
        $this->assertSame(7, $newStock->quantity());
    }

    public function testDecreaseStockToZero(): void
    {
        $stock = Stock::create(5);
        $newStock = $stock->decrease(5);

        $this->assertSame(0, $newStock->quantity());
    }

    public function testDecreaseStockThrowsExceptionWhenInsufficientStock(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Insufficient stock');

        $stock = Stock::create(5, true, false);
        $stock->decrease(10);
    }

    public function testDecreaseStockWithBackorderAllowed(): void
    {
        $stock = Stock::create(5, true, true);
        $newStock = $stock->decrease(10);

        $this->assertSame(0, $newStock->quantity());
    }

    public function testIncreaseThrowsExceptionForNegativeAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount must be positive');

        $stock = Stock::create(10);
        $stock->increase(-5);
    }

    public function testDecreaseThrowsExceptionForNegativeAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount must be positive');

        $stock = Stock::create(10);
        $stock->decrease(-5);
    }

    public function testIsAvailableWithSufficientStock(): void
    {
        $stock = Stock::create(10);

        $this->assertTrue($stock->isAvailable(5));
        $this->assertTrue($stock->isAvailable(10));
    }

    public function testIsAvailableWithInsufficientStock(): void
    {
        $stock = Stock::create(10);

        $this->assertFalse($stock->isAvailable(15));
    }

    public function testIsAvailableWhenOutOfStock(): void
    {
        $stock = Stock::create(0);

        $this->assertFalse($stock->isAvailable());
    }

    public function testIsAvailableWithBackorderAllowed(): void
    {
        $stock = Stock::create(0, true, true);

        $this->assertTrue($stock->isAvailable());
        $this->assertTrue($stock->isAvailable(100));
    }

    public function testIsAvailableWhenInventoryNotTracked(): void
    {
        $stock = Stock::create(0, false, false);

        $this->assertTrue($stock->isAvailable());
        $this->assertTrue($stock->isAvailable(1000));
    }

    public function testIsLowStockReturnsTrueWhenBelowThreshold(): void
    {
        $stock = Stock::create(3);

        $this->assertTrue($stock->isLowStock(5));
        $this->assertTrue($stock->isLowStock(10));
    }

    public function testIsLowStockReturnsFalseWhenAboveThreshold(): void
    {
        $stock = Stock::create(10);

        $this->assertFalse($stock->isLowStock(5));
    }

    public function testIsLowStockReturnsFalseWhenInventoryNotTracked(): void
    {
        $stock = Stock::create(0, false, false);

        $this->assertFalse($stock->isLowStock(10));
    }

    public function testIsLowStockWithDefaultThreshold(): void
    {
        $lowStock = Stock::create(4);
        $normalStock = Stock::create(15);

        $this->assertTrue($lowStock->isLowStock());
        $this->assertFalse($normalStock->isLowStock());
    }

    public function testStockImmutability(): void
    {
        $original = Stock::create(10);
        $increased = $original->increase(5);
        $decreased = $original->decrease(3);

        $this->assertSame(10, $original->quantity());
        $this->assertSame(15, $increased->quantity());
        $this->assertSame(7, $decreased->quantity());
        $this->assertNotSame($original, $increased);
        $this->assertNotSame($original, $decreased);
    }

    public function testCreateStockWithZeroQuantity(): void
    {
        $stock = Stock::create(0);

        $this->assertSame(0, $stock->quantity());
        $this->assertFalse($stock->isAvailable());
    }

    public function testCreateStockThrowsExceptionForNegativeQuantity(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Stock quantity cannot be negative');

        Stock::create(-5);
    }
}
