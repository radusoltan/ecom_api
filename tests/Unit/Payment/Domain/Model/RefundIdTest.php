<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Domain\Model;

use App\Payment\Domain\Model\RefundId;
use PHPUnit\Framework\TestCase;

final class RefundIdTest extends TestCase
{
    public function testItGeneratesValidUuidV4(): void
    {
        // Act
        $id = RefundId::generate();

        // Assert
        $this->assertInstanceOf(RefundId::class, $id);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $id->toString()
        );
    }

    public function testItGeneratesUniqueIds(): void
    {
        // Act
        $id1 = RefundId::generate();
        $id2 = RefundId::generate();

        // Assert
        $this->assertNotSame($id1->toString(), $id2->toString());
        $this->assertFalse($id1->equals($id2));
    }

    public function testItCreatesFromValidUuidString(): void
    {
        // Arrange
        $uuid = '9f8e7d6c-5b4a-4321-9012-345678901234';

        // Act
        $id = RefundId::fromString($uuid);

        // Assert
        $this->assertInstanceOf(RefundId::class, $id);
        $this->assertSame($uuid, $id->toString());
    }

    public function testItThrowsExceptionForEmptyString(): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('RefundId cannot be empty');

        // Act
        RefundId::fromString('');
    }

    public function testItThrowsExceptionForZeroString(): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('RefundId cannot be empty');

        // Act
        RefundId::fromString('0');
    }

    public function testItThrowsExceptionForInvalidUuidFormat(): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid RefundId format: "invalid". Must be a valid UUID v4');

        // Act
        RefundId::fromString('invalid');
    }

    public function testItThrowsExceptionForUuidV1(): void
    {
        // Arrange - UUID v1 has '1' in version position
        $uuidV1 = 'a0eebc99-9c0b-11d2-a4d9-426655440000';

        // Assert
        $this->expectException(\InvalidArgumentException::class);

        // Act
        RefundId::fromString($uuidV1);
    }

    public function testEqualsReturnsTrueForSameId(): void
    {
        // Arrange
        $uuid = '9f8e7d6c-5b4a-4321-9012-345678901234';
        $id1 = RefundId::fromString($uuid);
        $id2 = RefundId::fromString($uuid);

        // Act & Assert
        $this->assertTrue($id1->equals($id2));
        $this->assertTrue($id2->equals($id1));
    }

    public function testEqualsReturnsFalseForDifferentIds(): void
    {
        // Arrange
        $id1 = RefundId::fromString('9f8e7d6c-5b4a-4321-9012-345678901234');
        $id2 = RefundId::fromString('a1b2c3d4-e5f6-4789-a012-3456789abcde');

        // Act & Assert
        $this->assertFalse($id1->equals($id2));
        $this->assertFalse($id2->equals($id1));
    }

    public function testToStringReturnsUuidString(): void
    {
        // Arrange
        $uuid = '9f8e7d6c-5b4a-4321-9012-345678901234';
        $id = RefundId::fromString($uuid);

        // Act & Assert
        $this->assertSame($uuid, (string) $id);
        $this->assertSame($uuid, $id->toString());
    }
}
