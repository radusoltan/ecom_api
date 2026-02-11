<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Domain\Model;

use App\Payment\Domain\Model\TransactionId;
use PHPUnit\Framework\TestCase;

final class TransactionIdTest extends TestCase
{
    public function testItGeneratesValidUuidV4(): void
    {
        // Act
        $id = TransactionId::generate();

        // Assert
        $this->assertInstanceOf(TransactionId::class, $id);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $id->toString()
        );
    }

    public function testItGeneratesUniqueIds(): void
    {
        // Act
        $id1 = TransactionId::generate();
        $id2 = TransactionId::generate();

        // Assert
        $this->assertNotSame($id1->toString(), $id2->toString());
        $this->assertFalse($id1->equals($id2));
    }

    public function testItCreatesFromValidUuidString(): void
    {
        // Arrange
        $uuid = 'a1b2c3d4-e5f6-4789-a012-3456789abcde';

        // Act
        $id = TransactionId::fromString($uuid);

        // Assert
        $this->assertInstanceOf(TransactionId::class, $id);
        $this->assertSame($uuid, $id->toString());
    }

    public function testItThrowsExceptionForEmptyString(): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('TransactionId cannot be empty');

        // Act
        TransactionId::fromString('');
    }

    public function testItThrowsExceptionForZeroString(): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('TransactionId cannot be empty');

        // Act
        TransactionId::fromString('0');
    }

    public function testItThrowsExceptionForInvalidUuidFormat(): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid TransactionId format: "not-a-uuid". Must be a valid UUID v4');

        // Act
        TransactionId::fromString('not-a-uuid');
    }

    public function testItThrowsExceptionForUuidV1(): void
    {
        // Arrange - UUID v1 has '1' in version position
        $uuidV1 = 'a0eebc99-9c0b-11d2-a4d9-426655440000';

        // Assert
        $this->expectException(\InvalidArgumentException::class);

        // Act
        TransactionId::fromString($uuidV1);
    }

    public function testEqualsReturnsTrueForSameId(): void
    {
        // Arrange
        $uuid = 'a1b2c3d4-e5f6-4789-a012-3456789abcde';
        $id1 = TransactionId::fromString($uuid);
        $id2 = TransactionId::fromString($uuid);

        // Act & Assert
        $this->assertTrue($id1->equals($id2));
        $this->assertTrue($id2->equals($id1));
    }

    public function testEqualsReturnsFalseForDifferentIds(): void
    {
        // Arrange
        $id1 = TransactionId::fromString('a1b2c3d4-e5f6-4789-a012-3456789abcde');
        $id2 = TransactionId::fromString('f47ac10b-58cc-4372-a567-0e02b2c3d479');

        // Act & Assert
        $this->assertFalse($id1->equals($id2));
        $this->assertFalse($id2->equals($id1));
    }

    public function testToStringReturnsUuidString(): void
    {
        // Arrange
        $uuid = 'a1b2c3d4-e5f6-4789-a012-3456789abcde';
        $id = TransactionId::fromString($uuid);

        // Act & Assert
        $this->assertSame($uuid, (string) $id);
        $this->assertSame($uuid, $id->toString());
    }
}
