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

    // =============================================
    // Additional Edge Case Tests
    // =============================================

    public function testItCreatesQuantityWithMinValue(): void
    {
        // Act
        $quantity = Quantity::fromInt(1);

        // Assert
        $this->assertEquals(1, $quantity->toInt());
    }

    public function testItCreatesQuantityWithMaxValue(): void
    {
        // Act
        $quantity = Quantity::fromInt(999);

        // Assert
        $this->assertEquals(999, $quantity->toInt());
    }

    public function testItThrowsWhenAddingExceedsMaxQuantity(): void
    {
        // Arrange
        $qty1 = Quantity::fromInt(500);
        $qty2 = Quantity::fromInt(500);

        // Expect
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Quantity cannot exceed 999, 1000 given');

        // Act
        $qty1->add($qty2);
    }

    public function testItThrowsWhenSubtractingResultsInZero(): void
    {
        // Arrange
        $qty1 = Quantity::fromInt(5);
        $qty2 = Quantity::fromInt(5);

        // Expect
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Quantity must be at least 1, 0 given');

        // Act
        $qty1->subtract($qty2);
    }

    public function testItThrowsWhenSubtractingResultsInNegative(): void
    {
        // Arrange
        $qty1 = Quantity::fromInt(3);
        $qty2 = Quantity::fromInt(5);

        // Expect
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Quantity must be at least 1, -2 given');

        // Act
        $qty1->subtract($qty2);
    }

    public function testItDoesNotMutateOriginalWhenAdding(): void
    {
        // Arrange
        $original = Quantity::fromInt(5);
        $toAdd = Quantity::fromInt(3);

        // Act
        $result = $original->add($toAdd);

        // Assert
        $this->assertEquals(5, $original->toInt()); // Original unchanged
        $this->assertEquals(3, $toAdd->toInt()); // ToAdd unchanged
        $this->assertEquals(8, $result->toInt()); // New result
    }

    public function testItDoesNotMutateOriginalWhenSubtracting(): void
    {
        // Arrange
        $original = Quantity::fromInt(10);
        $toSubtract = Quantity::fromInt(3);

        // Act
        $result = $original->subtract($toSubtract);

        // Assert
        $this->assertEquals(10, $original->toInt()); // Original unchanged
        $this->assertEquals(3, $toSubtract->toInt()); // ToSubtract unchanged
        $this->assertEquals(7, $result->toInt()); // New result
    }

    public function testIsGreaterThanReturnsFalseForEqualQuantities(): void
    {
        // Arrange
        $qty1 = Quantity::fromInt(5);
        $qty2 = Quantity::fromInt(5);

        // Act & Assert
        $this->assertFalse($qty1->isGreaterThan($qty2));
    }

    public function testIsLessThanReturnsFalseForEqualQuantities(): void
    {
        // Arrange
        $qty1 = Quantity::fromInt(5);
        $qty2 = Quantity::fromInt(5);

        // Act & Assert
        $this->assertFalse($qty1->isLessThan($qty2));
    }

    public function testItAddsToMaxQuantity(): void
    {
        // Arrange
        $qty1 = Quantity::fromInt(500);
        $qty2 = Quantity::fromInt(499);

        // Act
        $result = $qty1->add($qty2);

        // Assert
        $this->assertEquals(999, $result->toInt());
    }

    public function testItSubtractsToMinQuantity(): void
    {
        // Arrange
        $qty1 = Quantity::fromInt(5);
        $qty2 = Quantity::fromInt(4);

        // Act
        $result = $qty1->subtract($qty2);

        // Assert
        $this->assertEquals(1, $result->toInt());
    }

    public function testMinQuantityIsGreaterThanNothing(): void
    {
        // Arrange
        $min = Quantity::fromInt(1);
        $higher = Quantity::fromInt(2);

        // Act & Assert
        $this->assertFalse($min->isGreaterThan($higher));
        $this->assertTrue($higher->isGreaterThan($min));
    }

    public function testMaxQuantityIsLessThanNothing(): void
    {
        // Arrange
        $max = Quantity::fromInt(999);
        $lower = Quantity::fromInt(998);

        // Act & Assert
        $this->assertFalse($max->isLessThan($lower));
        $this->assertTrue($lower->isLessThan($max));
    }
}
