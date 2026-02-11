<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Domain\Model;

use App\Payment\Domain\Model\PaymentId;
use PHPUnit\Framework\TestCase;

final class PaymentIdTest extends TestCase
{
    public function testItGeneratesValidUuidV4(): void
    {
        // Act
        $id = PaymentId::generate();

        // Assert
        $this->assertInstanceOf(PaymentId::class, $id);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $id->toString()
        );
    }

    public function testItGeneratesUniqueIds(): void
    {
        // Act
        $id1 = PaymentId::generate();
        $id2 = PaymentId::generate();

        // Assert
        $this->assertNotSame($id1->toString(), $id2->toString());
        $this->assertFalse($id1->equals($id2));
    }

    public function testItCreatesFromValidUuidString(): void
    {
        // Arrange
        $uuid = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';

        // Act
        $id = PaymentId::fromString($uuid);

        // Assert
        $this->assertInstanceOf(PaymentId::class, $id);
        $this->assertSame($uuid, $id->toString());
    }

    public function testItThrowsExceptionForEmptyString(): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('PaymentId cannot be empty');

        // Act
        PaymentId::fromString('');
    }

    public function testItThrowsExceptionForZeroString(): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('PaymentId cannot be empty');

        // Act
        PaymentId::fromString('0');
    }

    public function testItThrowsExceptionForInvalidUuidFormat(): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid PaymentId format: "invalid-uuid". Must be a valid UUID v4');

        // Act
        PaymentId::fromString('invalid-uuid');
    }

    public function testItThrowsExceptionForUuidV1(): void
    {
        // Arrange - UUID v1 has '1' in version position
        $uuidV1 = 'a0eebc99-9c0b-11d2-a4d9-426655440000';

        // Assert
        $this->expectException(\InvalidArgumentException::class);

        // Act
        PaymentId::fromString($uuidV1);
    }

    public function testEqualsReturnsTrueForSameId(): void
    {
        // Arrange
        $uuid = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';
        $id1 = PaymentId::fromString($uuid);
        $id2 = PaymentId::fromString($uuid);

        // Act & Assert
        $this->assertTrue($id1->equals($id2));
        $this->assertTrue($id2->equals($id1));
    }

    public function testEqualsReturnsFalseForDifferentIds(): void
    {
        // Arrange
        $id1 = PaymentId::fromString('f47ac10b-58cc-4372-a567-0e02b2c3d479');
        $id2 = PaymentId::fromString('550e8400-e29b-41d4-a716-446655440000');

        // Act & Assert
        $this->assertFalse($id1->equals($id2));
        $this->assertFalse($id2->equals($id1));
    }

    public function testToStringReturnsUuidString(): void
    {
        // Arrange
        $uuid = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';
        $id = PaymentId::fromString($uuid);

        // Act & Assert
        $this->assertSame($uuid, (string) $id);
        $this->assertSame($uuid, $id->toString());
    }
}
