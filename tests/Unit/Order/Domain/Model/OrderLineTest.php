<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Domain\Model;

use App\Order\Domain\Model\OrderLine;
use App\Order\Domain\ValueObject\OrderProductId;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class OrderLineTest extends TestCase
{
    public function testItCreatesOrderLineWithValidData(): void
    {
        // Arrange
        $productId = OrderProductId::generate();
        $productName = 'Test Product';
        $quantity = 2;
        $unitPrice = Money::fromScalars(1000, 'USD');

        // Act
        $line = OrderLine::create($productId, $productName, $quantity, $unitPrice);

        // Assert
        $this->assertTrue($line->productId()->equals($productId));
        $this->assertSame($productName, $line->productName());
        $this->assertSame($quantity, $line->quantity());
        $this->assertTrue($line->unitPrice()->equals($unitPrice));
    }

    public function testItCalculatesCorrectTotal(): void
    {
        // Arrange
        $productId = OrderProductId::generate();
        $productName = 'Test Product';
        $quantity = 3;
        $unitPrice = Money::fromScalars(1000, 'USD'); // $10.00

        // Act
        $line = OrderLine::create($productId, $productName, $quantity, $unitPrice);
        $total = $line->total();

        // Assert
        $this->assertEquals(3000, $total->getAmount()); // 3 * $10.00 = $30.00
        $this->assertSame('USD', $total->getCurrency()->getCurrencyCode());
    }

    public function testItThrowsExceptionForZeroQuantity(): void
    {
        // Arrange
        $productId = OrderProductId::generate();
        $productName = 'Test Product';
        $quantity = 0;
        $unitPrice = Money::fromScalars(1000, 'USD');

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Order line quantity must be greater than 0');

        // Act
        OrderLine::create($productId, $productName, $quantity, $unitPrice);
    }

    public function testItThrowsExceptionForNegativeQuantity(): void
    {
        // Arrange
        $productId = OrderProductId::generate();
        $productName = 'Test Product';
        $quantity = -1;
        $unitPrice = Money::fromScalars(1000, 'USD');

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Order line quantity must be greater than 0');

        // Act
        OrderLine::create($productId, $productName, $quantity, $unitPrice);
    }

    public function testItThrowsExceptionForZeroUnitPrice(): void
    {
        // Arrange
        $productId = OrderProductId::generate();
        $productName = 'Test Product';
        $quantity = 2;
        $unitPrice = Money::fromScalars(0, 'USD');

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Order line unit price must be greater than 0');

        // Act
        OrderLine::create($productId, $productName, $quantity, $unitPrice);
    }

    public function testItThrowsExceptionForNegativeUnitPrice(): void
    {
        // Arrange
        $productId = OrderProductId::generate();
        $productName = 'Test Product';
        $quantity = 2;
        $unitPrice = Money::fromScalars(-100, 'USD');

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Order line unit price must be greater than 0');

        // Act
        OrderLine::create($productId, $productName, $quantity, $unitPrice);
    }
}
