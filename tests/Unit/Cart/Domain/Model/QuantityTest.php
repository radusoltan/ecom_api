<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cart\Domain\Model;

use App\Cart\Domain\Model\Quantity;
use PHPUnit\Framework\TestCase;

final class QuantityTest extends TestCase
{
    public function testItCreatesValidQuantity(): void
    {
        $quantity = Quantity::fromInt(5);
        $this->assertEquals(5, $quantity->toInt());
    }

    public function testItThrowsExceptionForZeroQuantity(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Quantity must be at least 1');
        Quantity::fromInt(0);
    }

    public function testItThrowsExceptionForNegativeQuantity(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Quantity::fromInt(-5);
    }

    public function testItThrowsExceptionForQuantityAboveMaximum(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Quantity cannot exceed 999');
        Quantity::fromInt(1000);
    }

    public function testItAddsQuantities(): void
    {
        $qty1 = Quantity::fromInt(3);
        $qty2 = Quantity::fromInt(5);
        $result = $qty1->add($qty2);
        $this->assertEquals(8, $result->toInt());
    }

    public function testItSubtractsQuantities(): void
    {
        $qty1 = Quantity::fromInt(10);
        $qty2 = Quantity::fromInt(3);
        $result = $qty1->subtract($qty2);
        $this->assertEquals(7, $result->toInt());
    }

    public function testItComparesEqualQuantities(): void
    {
        $qty1 = Quantity::fromInt(5);
        $qty2 = Quantity::fromInt(5);
        $this->assertTrue($qty1->equals($qty2));
    }

    public function testItComparesUnequalQuantities(): void
    {
        $qty1 = Quantity::fromInt(5);
        $qty2 = Quantity::fromInt(3);
        $this->assertFalse($qty1->equals($qty2));
    }

    public function testItChecksIfGreaterThan(): void
    {
        $qty1 = Quantity::fromInt(10);
        $qty2 = Quantity::fromInt(5);
        $this->assertTrue($qty1->isGreaterThan($qty2));
        $this->assertFalse($qty2->isGreaterThan($qty1));
    }

    public function testItChecksIfLessThan(): void
    {
        $qty1 = Quantity::fromInt(5);
        $qty2 = Quantity::fromInt(10);
        $this->assertTrue($qty1->isLessThan($qty2));
        $this->assertFalse($qty2->isLessThan($qty1));
    }
}
