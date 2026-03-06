<?php

declare(strict_types=1);

namespace App\Tests\Unit\AuditLog\Domain\ValueObject;

use App\AuditLog\Domain\ValueObject\AuditLogId;
use PHPUnit\Framework\TestCase;

final class AuditLogIdTest extends TestCase
{
    public function testItGeneratesUniqueIds(): void
    {
        // Act
        $id1 = AuditLogId::generate();
        $id2 = AuditLogId::generate();

        // Assert
        self::assertFalse($id1->equals($id2));
    }

    public function testItGeneratesValidUuidV4(): void
    {
        // Act
        $id = AuditLogId::generate();

        // Assert — UUID v4 pattern: xxxxxxxx-xxxx-4xxx-[89ab]xxx-xxxxxxxxxxxx
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $id->toString()
        );
    }

    public function testItCreatesFromValidString(): void
    {
        // Arrange
        $uuid = '550e8400-e29b-41d4-a716-446655440000';

        // Act
        $id = AuditLogId::fromString($uuid);

        // Assert
        self::assertSame($uuid, $id->toString());
    }

    public function testItThrowsForInvalidUuid(): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid UUID');

        // Act
        AuditLogId::fromString('not-a-uuid');
    }

    public function testItThrowsForEmptyString(): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);

        // Act
        AuditLogId::fromString('');
    }

    public function testItEqualsSameValue(): void
    {
        // Arrange
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $id1 = AuditLogId::fromString($uuid);
        $id2 = AuditLogId::fromString($uuid);

        // Assert
        self::assertTrue($id1->equals($id2));
    }

    public function testItDoesNotEqualDifferentValue(): void
    {
        // Arrange
        $id1 = AuditLogId::generate();
        $id2 = AuditLogId::generate();

        // Assert
        self::assertFalse($id1->equals($id2));
    }

    public function testItConvertsToStringViaMagicMethod(): void
    {
        // Arrange
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $id = AuditLogId::fromString($uuid);

        // Assert
        self::assertSame($uuid, (string) $id);
    }

    public function testToStringAndMagicStringMatch(): void
    {
        // Arrange
        $id = AuditLogId::generate();

        // Assert
        self::assertSame($id->toString(), (string) $id);
    }

    public function testItAcceptsDefaultTestTenantFormatUuid(): void
    {
        // Arrange — using the project's default test UUID format
        $uuid = '00000000-0000-4000-8000-000000000001';

        // Act
        $id = AuditLogId::fromString($uuid);

        // Assert
        self::assertSame($uuid, $id->toString());
    }
}
