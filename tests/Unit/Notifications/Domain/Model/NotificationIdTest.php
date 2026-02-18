<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications\Domain\Model;

use App\Notifications\Domain\Model\NotificationId;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for NotificationId value object.
 *
 * Tests ULID-based identifier with validation.
 */
final class NotificationIdTest extends TestCase
{
    public function testItGeneratesValidNotificationId(): void
    {
        // Act
        $id = NotificationId::generate();

        // Assert
        $this->assertInstanceOf(NotificationId::class, $id);
        $this->assertNotEmpty($id->toString());
        $this->assertMatchesRegularExpression('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/i', $id->toString());
    }

    public function testItCreatesFromValidString(): void
    {
        // Arrange
        $ulid = '01HQ4VY5JZQZ5JZQZ5JZQZ5JZQ';

        // Act
        $id = NotificationId::fromString($ulid);

        // Assert
        $this->assertSame($ulid, $id->toString());
    }

    public function testItThrowsWhenStringIsEmpty(): void
    {
        // Arrange
        $emptyString = '';

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('NotificationId cannot be empty');

        // Act
        NotificationId::fromString($emptyString);
    }

    public function testItThrowsWhenStringIsZero(): void
    {
        // Arrange
        $zeroString = '0';

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('NotificationId cannot be empty');

        // Act
        NotificationId::fromString($zeroString);
    }

    public function testItThrowsWhenUlidFormatIsInvalid(): void
    {
        // Arrange
        $invalidUlid = 'invalid-ulid-format';

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid NotificationId format');

        // Act
        NotificationId::fromString($invalidUlid);
    }

    public function testItEqualsSameValue(): void
    {
        // Arrange
        $ulid = '01HQ4VY5JZQZ5JZQZ5JZQZ5JZQ';
        $id1 = NotificationId::fromString($ulid);
        $id2 = NotificationId::fromString($ulid);

        // Assert
        $this->assertTrue($id1->equals($id2));
    }

    public function testItNotEqualsDifferentValue(): void
    {
        // Arrange
        $id1 = NotificationId::generate();
        $id2 = NotificationId::generate();

        // Assert
        $this->assertFalse($id1->equals($id2));
    }

    public function testItConvertsToString(): void
    {
        // Arrange
        $ulid = '01HQ4VY5JZQZ5JZQZ5JZQZ5JZQ';
        $id = NotificationId::fromString($ulid);

        // Act
        $stringValue = (string) $id;

        // Assert
        $this->assertSame($ulid, $stringValue);
    }
}
