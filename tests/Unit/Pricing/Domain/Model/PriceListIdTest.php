<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Domain\Model;

use App\Pricing\Domain\Model\PriceListId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PriceListIdTest extends TestCase
{
    public function testGenerateCreatesValidUuid(): void
    {
        $id = PriceListId::generate();

        $this->assertInstanceOf(PriceListId::class, $id);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $id->toString()
        );
    }

    public function testFromStringWithValidUuid(): void
    {
        $uuidString = '550e8400-e29b-41d4-a716-446655440000';
        $id = PriceListId::fromString($uuidString);

        $this->assertInstanceOf(PriceListId::class, $id);
        $this->assertSame($uuidString, $id->toString());
    }

    public function testFromStringThrowsExceptionForInvalidUuid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid PriceListId');

        PriceListId::fromString('invalid-uuid');
    }

    public function testFromStringThrowsExceptionForEmptyString(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PriceListId::fromString('');
    }

    public function testToStringReturnsCorrectValue(): void
    {
        $uuidString = '550e8400-e29b-41d4-a716-446655440000';
        $id = PriceListId::fromString($uuidString);

        $this->assertSame($uuidString, $id->toString());
    }

    public function testEqualsReturnsTrueForSameId(): void
    {
        $uuidString = '550e8400-e29b-41d4-a716-446655440000';
        $id1 = PriceListId::fromString($uuidString);
        $id2 = PriceListId::fromString($uuidString);

        $this->assertTrue($id1->equals($id2));
    }

    public function testEqualsReturnsFalseForDifferentIds(): void
    {
        $id1 = PriceListId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $id2 = PriceListId::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8');

        $this->assertFalse($id1->equals($id2));
    }

    public function testGenerateCreatesUniqueIds(): void
    {
        $id1 = PriceListId::generate();
        $id2 = PriceListId::generate();

        $this->assertFalse($id1->equals($id2));
    }

    public function testFromStringPreservesCase(): void
    {
        $uuidString = '550E8400-E29B-41D4-A716-446655440000';
        $id = PriceListId::fromString($uuidString);

        $this->assertSame($uuidString, $id->toString());
    }
}
