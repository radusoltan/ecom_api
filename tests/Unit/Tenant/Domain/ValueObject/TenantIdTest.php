<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tenant\Domain\ValueObject;

use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class TenantIdTest extends TestCase
{
    public function testItGeneratesValidUuidV4(): void
    {
        // Act
        $tenantId = TenantId::generate();

        // Assert
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $tenantId->toString()
        );
    }

    public function testItGeneratesUniqueIds(): void
    {
        // Act
        $id1 = TenantId::generate();
        $id2 = TenantId::generate();

        // Assert
        $this->assertNotEquals($id1->toString(), $id2->toString());
    }

    public function testItCreatesFromValidUuidV4(): void
    {
        // Arrange
        $validUuid = '550e8400-e29b-41d4-a716-446655440000';

        // Act
        $tenantId = TenantId::fromString($validUuid);

        // Assert
        $this->assertSame($validUuid, $tenantId->toString());
    }

    public function testItAcceptsValidUuidV4WithDifferentVariantBits(): void
    {
        // Arrange - Test with variant bit 8
        $uuidVariant8 = '550e8400-e29b-41d4-8716-446655440000';

        // Act
        $tenantId = TenantId::fromString($uuidVariant8);

        // Assert
        $this->assertSame($uuidVariant8, $tenantId->toString());
    }

    public function testItRejectsEmptyString(): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('TenantId cannot be empty');

        // Act
        TenantId::fromString('');
    }

    public function testItRejectsInvalidUuidFormat(): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid TenantId format');

        // Act
        TenantId::fromString('not-a-uuid');
    }

    public function testItRejectsUuidWithWrongVersion(): void
    {
        // Arrange - UUID v1 (version bit is 1, not 4)
        $uuidV1 = '550e8400-e29b-11d4-a716-446655440000';

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid TenantId format');

        // Act
        TenantId::fromString($uuidV1);
    }

    public function testItRejectsUuidWithInvalidVariantBits(): void
    {
        // Arrange - Invalid variant (should be 8, 9, a, or b in the 9th group)
        $invalidVariant = '550e8400-e29b-41d4-c716-446655440000';

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid TenantId format');

        // Act
        TenantId::fromString($invalidVariant);
    }

    public function testItComparesEqualityCorrectly(): void
    {
        // Arrange
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $id1 = TenantId::fromString($uuid);
        $id2 = TenantId::fromString($uuid);
        $id3 = TenantId::fromString('650e8400-e29b-41d4-a716-446655440000');

        // Assert
        $this->assertTrue($id1->equals($id2));
        $this->assertFalse($id1->equals($id3));
    }

    public function testItConvertsToStringViaMagicMethod(): void
    {
        // Arrange
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $tenantId = TenantId::fromString($uuid);

        // Act
        $stringValue = (string) $tenantId;

        // Assert
        $this->assertSame($uuid, $stringValue);
    }

    public function testToStringReturnsSameAsToStringMethod(): void
    {
        // Arrange
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $tenantId = TenantId::fromString($uuid);

        // Assert
        $this->assertSame($tenantId->toString(), (string) $tenantId);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidUuidProvider')]
    public function testItRejectsInvalidUuids(string $invalidUuid): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);

        // Act
        TenantId::fromString($invalidUuid);
    }

    public static function invalidUuidProvider(): array
    {
        return [
            [''],
            ['not-a-uuid'],
            ['550e8400-e29b-11d4-a716-446655440000'],
            ['550e8400-e29b-31d4-a716-446655440000'],
            ['550e8400-e29b-51d4-a716-446655440000'],
            ['550e8400e29b41d4a716446655440000'],
            ['550e8400-e29b-41d4-a716'],
            ['550e8400-e29b-41d4-c716-446655440000'],
        ];
    }
}
