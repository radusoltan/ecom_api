<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Domain\Model;

use App\Order\Domain\Model\OrderId;
use PHPUnit\Framework\TestCase;

final class OrderIdTest extends TestCase
{
    public function testItGeneratesValidUuidV4(): void
    {
        // Act
        $orderId = OrderId::generate();

        // Assert
        $this->assertInstanceOf(OrderId::class, $orderId);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $orderId->toString()
        );
    }

    public function testItGeneratesUniqueIds(): void
    {
        // Act
        $orderId1 = OrderId::generate();
        $orderId2 = OrderId::generate();

        // Assert
        $this->assertNotSame($orderId1->toString(), $orderId2->toString());
    }

    public function testItCreatesFromValidUuidString(): void
    {
        // Arrange
        $uuid = '550e8400-e29b-41d4-a716-446655440000';

        // Act
        $orderId = OrderId::fromString($uuid);

        // Assert
        $this->assertInstanceOf(OrderId::class, $orderId);
        $this->assertSame($uuid, $orderId->toString());
    }

    public function testItThrowsExceptionForEmptyString(): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('OrderId cannot be empty');

        // Act
        OrderId::fromString('');
    }

    public function testItThrowsExceptionForInvalidUuidFormat(): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid OrderId format');

        // Act
        OrderId::fromString('invalid-uuid');
    }

    public function testItThrowsExceptionForNonUuidV4(): void
    {
        // Arrange - UUID v1 (note the '1' in the version position)
        $uuidV1 = '550e8400-e29b-11d4-a716-446655440000';

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid OrderId format');

        // Act
        OrderId::fromString($uuidV1);
    }

    public function testEqualsReturnsTrueForSameId(): void
    {
        // Arrange
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $orderId1 = OrderId::fromString($uuid);
        $orderId2 = OrderId::fromString($uuid);

        // Act & Assert
        $this->assertTrue($orderId1->equals($orderId2));
    }

    public function testEqualsReturnsFalseForDifferentIds(): void
    {
        // Arrange
        $orderId1 = OrderId::generate();
        $orderId2 = OrderId::generate();

        // Act & Assert
        $this->assertFalse($orderId1->equals($orderId2));
    }

    public function testToStringReturnsUuidString(): void
    {
        // Arrange
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $orderId = OrderId::fromString($uuid);

        // Act & Assert
        $this->assertSame($uuid, (string) $orderId);
        $this->assertSame($uuid, $orderId->toString());
    }
}
