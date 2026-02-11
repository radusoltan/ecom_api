<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cart\Domain\Model;

use App\Cart\Domain\Model\CartStatus;
use PHPUnit\Framework\TestCase;

final class CartStatusTest extends TestCase
{
    // =============================================
    // Test Factory Methods
    // =============================================

    public function testItCreatesActiveStatus(): void
    {
        // Act
        $status = CartStatus::active();

        // Assert
        $this->assertSame(CartStatus::ACTIVE, $status);
        $this->assertEquals('active', $status->value());
    }

    public function testItCreatesExpiredStatus(): void
    {
        // Act
        $status = CartStatus::expired();

        // Assert
        $this->assertSame(CartStatus::EXPIRED, $status);
        $this->assertEquals('expired', $status->value());
    }

    public function testItCreatesConvertedStatus(): void
    {
        // Act
        $status = CartStatus::converted();

        // Assert
        $this->assertSame(CartStatus::CONVERTED, $status);
        $this->assertEquals('converted', $status->value());
    }

    // =============================================
    // Test IsActive
    // =============================================

    public function testIsActiveReturnsTrueForActiveStatus(): void
    {
        // Arrange
        $status = CartStatus::active();

        // Act & Assert
        $this->assertTrue($status->isActive());
        $this->assertFalse($status->isExpired());
        $this->assertFalse($status->isConverted());
    }

    public function testIsActiveReturnsFalseForExpiredStatus(): void
    {
        // Arrange
        $status = CartStatus::expired();

        // Act & Assert
        $this->assertFalse($status->isActive());
    }

    public function testIsActiveReturnsFalseForConvertedStatus(): void
    {
        // Arrange
        $status = CartStatus::converted();

        // Act & Assert
        $this->assertFalse($status->isActive());
    }

    // =============================================
    // Test IsExpired
    // =============================================

    public function testIsExpiredReturnsTrueForExpiredStatus(): void
    {
        // Arrange
        $status = CartStatus::expired();

        // Act & Assert
        $this->assertTrue($status->isExpired());
        $this->assertFalse($status->isActive());
        $this->assertFalse($status->isConverted());
    }

    public function testIsExpiredReturnsFalseForActiveStatus(): void
    {
        // Arrange
        $status = CartStatus::active();

        // Act & Assert
        $this->assertFalse($status->isExpired());
    }

    public function testIsExpiredReturnsFalseForConvertedStatus(): void
    {
        // Arrange
        $status = CartStatus::converted();

        // Act & Assert
        $this->assertFalse($status->isExpired());
    }

    // =============================================
    // Test IsConverted
    // =============================================

    public function testIsConvertedReturnsTrueForConvertedStatus(): void
    {
        // Arrange
        $status = CartStatus::converted();

        // Act & Assert
        $this->assertTrue($status->isConverted());
        $this->assertFalse($status->isActive());
        $this->assertFalse($status->isExpired());
    }

    public function testIsConvertedReturnsFalseForActiveStatus(): void
    {
        // Arrange
        $status = CartStatus::active();

        // Act & Assert
        $this->assertFalse($status->isConverted());
    }

    public function testIsConvertedReturnsFalseForExpiredStatus(): void
    {
        // Arrange
        $status = CartStatus::expired();

        // Act & Assert
        $this->assertFalse($status->isConverted());
    }

    // =============================================
    // Test Enum Values
    // =============================================

    public function testActiveEnumHasCorrectValue(): void
    {
        // Arrange
        $status = CartStatus::ACTIVE;

        // Assert
        $this->assertEquals('active', $status->value);
    }

    public function testExpiredEnumHasCorrectValue(): void
    {
        // Arrange
        $status = CartStatus::EXPIRED;

        // Assert
        $this->assertEquals('expired', $status->value);
    }

    public function testConvertedEnumHasCorrectValue(): void
    {
        // Arrange
        $status = CartStatus::CONVERTED;

        // Assert
        $this->assertEquals('converted', $status->value);
    }

    // =============================================
    // Test Equality
    // =============================================

    public function testTwoActiveStatusesAreEqual(): void
    {
        // Arrange
        $status1 = CartStatus::active();
        $status2 = CartStatus::active();

        // Assert
        $this->assertSame($status1, $status2);
    }

    public function testActiveAndExpiredStatusesAreNotEqual(): void
    {
        // Arrange
        $active = CartStatus::active();
        $expired = CartStatus::expired();

        // Assert
        $this->assertNotSame($active, $expired);
    }

    public function testActiveAndConvertedStatusesAreNotEqual(): void
    {
        // Arrange
        $active = CartStatus::active();
        $converted = CartStatus::converted();

        // Assert
        $this->assertNotSame($active, $converted);
    }

    public function testExpiredAndConvertedStatusesAreNotEqual(): void
    {
        // Arrange
        $expired = CartStatus::expired();
        $converted = CartStatus::converted();

        // Assert
        $this->assertNotSame($expired, $converted);
    }
}
