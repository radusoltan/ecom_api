<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\ValueObject;

use App\Shared\Domain\ValueObject\TenantId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class TenantIdTest extends TestCase
{
    public function testGenerateCreatesValidUuidV4(): void
    {
        $tenantId = TenantId::generate();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $tenantId->toString()
        );
    }

    public function testGenerateCreatesUniqueIds(): void
    {
        $id1 = TenantId::generate();
        $id2 = TenantId::generate();

        $this->assertNotEquals($id1->toString(), $id2->toString());
    }

    public function testGenerateMultipleIdsAreAllUnique(): void
    {
        $ids = [];
        for ($i = 0; $i < 100; $i++) {
            $ids[] = TenantId::generate()->toString();
        }

        $uniqueIds = array_unique($ids);
        $this->assertCount(100, $uniqueIds);
    }

    public function testFromStringCreatesValidTenantId(): void
    {
        $validUuid = '550e8400-e29b-41d4-a716-446655440000';

        $tenantId = TenantId::fromString($validUuid);

        $this->assertSame($validUuid, $tenantId->toString());
    }

    public function testFromStringAcceptsLowercaseUuid(): void
    {
        $lowercase = '550e8400-e29b-41d4-a716-446655440000';

        $tenantId = TenantId::fromString($lowercase);

        $this->assertSame($lowercase, $tenantId->toString());
    }

    public function testFromStringAcceptsUppercaseUuid(): void
    {
        $uppercase = '550E8400-E29B-41D4-A716-446655440000';

        $tenantId = TenantId::fromString($uppercase);

        $this->assertSame($uppercase, $tenantId->toString());
    }

    public function testFromStringAcceptsMixedCaseUuid(): void
    {
        $mixedCase = '550e8400-E29b-41D4-a716-446655440000';

        $tenantId = TenantId::fromString($mixedCase);

        $this->assertSame($mixedCase, $tenantId->toString());
    }

    public function testFromStringWithVariantBit8(): void
    {
        $uuidVariant8 = '550e8400-e29b-41d4-8716-446655440000';

        $tenantId = TenantId::fromString($uuidVariant8);

        $this->assertSame($uuidVariant8, $tenantId->toString());
    }

    public function testFromStringWithVariantBit9(): void
    {
        $uuidVariant9 = '550e8400-e29b-41d4-9716-446655440000';

        $tenantId = TenantId::fromString($uuidVariant9);

        $this->assertSame($uuidVariant9, $tenantId->toString());
    }

    public function testFromStringWithVariantBitA(): void
    {
        $uuidVariantA = '550e8400-e29b-41d4-a716-446655440000';

        $tenantId = TenantId::fromString($uuidVariantA);

        $this->assertSame($uuidVariantA, $tenantId->toString());
    }

    public function testFromStringWithVariantBitB(): void
    {
        $uuidVariantB = '550e8400-e29b-41d4-b716-446655440000';

        $tenantId = TenantId::fromString($uuidVariantB);

        $this->assertSame($uuidVariantB, $tenantId->toString());
    }

    public function testFromStringThrowsExceptionForEmptyString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('TenantId cannot be empty');

        TenantId::fromString('');
    }

    public function testFromStringThrowsExceptionForZeroString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('TenantId cannot be empty');

        TenantId::fromString('0');
    }

    public function testFromStringThrowsExceptionForInvalidFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid TenantId format');

        TenantId::fromString('not-a-uuid');
    }

    public function testFromStringThrowsExceptionForUuidV1(): void
    {
        $uuidV1 = '550e8400-e29b-11d4-a716-446655440000';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid TenantId format');

        TenantId::fromString($uuidV1);
    }

    public function testFromStringThrowsExceptionForUuidV3(): void
    {
        $uuidV3 = '550e8400-e29b-31d4-a716-446655440000';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid TenantId format');

        TenantId::fromString($uuidV3);
    }

    public function testFromStringThrowsExceptionForUuidV5(): void
    {
        $uuidV5 = '550e8400-e29b-51d4-a716-446655440000';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid TenantId format');

        TenantId::fromString($uuidV5);
    }

    public function testFromStringThrowsExceptionForInvalidVariantBit(): void
    {
        $invalidVariant = '550e8400-e29b-41d4-c716-446655440000';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid TenantId format');

        TenantId::fromString($invalidVariant);
    }

    public function testFromStringThrowsExceptionForUuidWithoutHyphens(): void
    {
        $withoutHyphens = '550e8400e29b41d4a716446655440000';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid TenantId format');

        TenantId::fromString($withoutHyphens);
    }

    public function testFromStringThrowsExceptionForTooShortUuid(): void
    {
        $tooShort = '550e8400-e29b-41d4-a716';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid TenantId format');

        TenantId::fromString($tooShort);
    }

    public function testFromStringThrowsExceptionForTooLongUuid(): void
    {
        $tooLong = '550e8400-e29b-41d4-a716-446655440000-extra';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid TenantId format');

        TenantId::fromString($tooLong);
    }

    public function testFromStringThrowsExceptionForUuidWithInvalidCharacters(): void
    {
        $invalidChars = '550e8400-e29b-41d4-a716-44665544000g';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid TenantId format');

        TenantId::fromString($invalidChars);
    }

    public function testEqualsReturnsTrueForSameUuid(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $id1 = TenantId::fromString($uuid);
        $id2 = TenantId::fromString($uuid);

        $this->assertTrue($id1->equals($id2));
    }

    public function testEqualsReturnsFalseForDifferentUuids(): void
    {
        $id1 = TenantId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $id2 = TenantId::fromString('650e8400-e29b-41d4-a716-446655440000');

        $this->assertFalse($id1->equals($id2));
    }

    public function testEqualsIsCaseSensitive(): void
    {
        $id1 = TenantId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $id2 = TenantId::fromString('550E8400-E29B-41D4-A716-446655440000');

        $this->assertFalse($id1->equals($id2));
    }

    public function testToStringReturnsOriginalValue(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $tenantId = TenantId::fromString($uuid);

        $this->assertSame($uuid, $tenantId->toString());
    }

    public function testMagicToStringReturnsValue(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $tenantId = TenantId::fromString($uuid);

        $this->assertSame($uuid, (string) $tenantId);
    }

    public function testToStringAndMagicToStringReturnSameValue(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $tenantId = TenantId::fromString($uuid);

        $this->assertSame($tenantId->toString(), (string) $tenantId);
    }

    public function testGeneratedIdIsValidUuidV4(): void
    {
        $tenantId = TenantId::generate();

        // Extract version (13th char should be '4')
        $uuid = $tenantId->toString();
        $this->assertSame('4', $uuid[14]);

        // Extract variant (17th char should be 8, 9, a, or b)
        $variantChar = strtolower($uuid[19]);
        $this->assertContains($variantChar, ['8', '9', 'a', 'b']);
    }

    public function testIsReadonly(): void
    {
        $reflection = new \ReflectionClass(TenantId::class);

        $this->assertTrue($reflection->isReadOnly());
    }

    public function testIsFinal(): void
    {
        $reflection = new \ReflectionClass(TenantId::class);

        $this->assertTrue($reflection->isFinal());
    }

    public function testImplementsStringable(): void
    {
        $tenantId = TenantId::generate();

        $this->assertInstanceOf(\Stringable::class, $tenantId);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidUuidProvider')]
    public function testFromStringRejectsInvalidUuids(string $invalidUuid, string $expectedMessage): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        TenantId::fromString($invalidUuid);
    }

    public static function invalidUuidProvider(): array
    {
        return [
            'empty string' => ['', 'TenantId cannot be empty'],
            'zero string' => ['0', 'TenantId cannot be empty'],
            'not a uuid' => ['not-a-uuid', 'Invalid TenantId format'],
            'uuid v1' => ['550e8400-e29b-11d4-a716-446655440000', 'Invalid TenantId format'],
            'uuid v3' => ['550e8400-e29b-31d4-a716-446655440000', 'Invalid TenantId format'],
            'uuid v5' => ['550e8400-e29b-51d4-a716-446655440000', 'Invalid TenantId format'],
            'no hyphens' => ['550e8400e29b41d4a716446655440000', 'Invalid TenantId format'],
            'too short' => ['550e8400-e29b-41d4-a716', 'Invalid TenantId format'],
            'too long' => ['550e8400-e29b-41d4-a716-446655440000-extra', 'Invalid TenantId format'],
            'invalid variant' => ['550e8400-e29b-41d4-c716-446655440000', 'Invalid TenantId format'],
            'invalid chars' => ['550e8400-e29b-41d4-a716-44665544000g', 'Invalid TenantId format'],
            'spaces' => ['550e8400-e29b-41d4-a716-446655440000 ', 'Invalid TenantId format'],
            'uppercase invalid' => ['NOT-A-UUID-FORMAT-HERE-000000000000', 'Invalid TenantId format'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('validUuidProvider')]
    public function testFromStringAcceptsValidUuids(string $validUuid): void
    {
        $tenantId = TenantId::fromString($validUuid);

        $this->assertSame($validUuid, $tenantId->toString());
    }

    public static function validUuidProvider(): array
    {
        return [
            'lowercase variant 8' => ['550e8400-e29b-41d4-8716-446655440000'],
            'lowercase variant 9' => ['550e8400-e29b-41d4-9716-446655440000'],
            'lowercase variant a' => ['550e8400-e29b-41d4-a716-446655440000'],
            'lowercase variant b' => ['550e8400-e29b-41d4-b716-446655440000'],
            'uppercase variant 8' => ['550E8400-E29B-41D4-8716-446655440000'],
            'uppercase variant 9' => ['550E8400-E29B-41D4-9716-446655440000'],
            'uppercase variant A' => ['550E8400-E29B-41D4-A716-446655440000'],
            'uppercase variant B' => ['550E8400-E29B-41D4-B716-446655440000'],
            'mixed case' => ['550e8400-E29b-41D4-a716-446655440000'],
            'all zeros' => ['00000000-0000-4000-8000-000000000000'],
            'all f' => ['ffffffff-ffff-4fff-bfff-ffffffffffff'],
        ];
    }
}
