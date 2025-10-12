<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Domain\Model;

use App\Inventory\Domain\Model\Quantity;
use PHPUnit\Framework\TestCase;

final class QuantityTest extends TestCase
{
    public function testCreateFromInt(): void
    {
        $quantity = Quantity::fromInt(42);

        $this->assertEquals(42, $quantity->value());
    }

    public function testCreateZero(): void
    {
        $quantity = Quantity::zero();

        $this->assertEquals(0, $quantity->value());
        $this->assertTrue($quantity->isZero());
    }

    public function testCannotCreateNegativeQuantity(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Quantity cannot be negative');

        Quantity::fromInt(-10);
    }

    public function testCannotExceedMaxQuantity(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Quantity cannot exceed 999999');

        Quantity::fromInt(1000000);
    }

    public function testAdd(): void
    {
        $qty1 = Quantity::fromInt(10);
        $qty2 = Quantity::fromInt(5);

        $result = $qty1->add($qty2);

        $this->assertEquals(15, $result->value());
    }

    public function testSubtract(): void
    {
        $qty1 = Quantity::fromInt(10);
        $qty2 = Quantity::fromInt(3);

        $result = $qty1->subtract($qty2);

        $this->assertEquals(7, $result->value());
    }

    public function testSubtractThrowsWhenResultWouldBeNegative(): void
    {
        $qty1 = Quantity::fromInt(5);
        $qty2 = Quantity::fromInt(10);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Quantity cannot be negative');

        $qty1->subtract($qty2);
    }

    public function testIsPositive(): void
    {
        $positive = Quantity::fromInt(1);
        $zero = Quantity::zero();

        $this->assertTrue($positive->isPositive());
        $this->assertFalse($zero->isPositive());
    }

    public function testComparisons(): void
    {
        $qty5 = Quantity::fromInt(5);
        $qty10 = Quantity::fromInt(10);
        $qty10b = Quantity::fromInt(10);

        $this->assertTrue($qty10->isGreaterThan($qty5));
        $this->assertFalse($qty5->isGreaterThan($qty10));

        $this->assertTrue($qty10->isGreaterThanOrEqual($qty5));
        $this->assertTrue($qty10->isGreaterThanOrEqual($qty10b));

        $this->assertTrue($qty5->isLessThan($qty10));
        $this->assertFalse($qty10->isLessThan($qty5));

        $this->assertTrue($qty10->equals($qty10b));
        $this->assertFalse($qty10->equals($qty5));
    }

    public function testToString(): void
    {
        $quantity = Quantity::fromInt(42);

        $this->assertEquals('42', $quantity->toString());
        $this->assertEquals('42', (string) $quantity);
    }
}
