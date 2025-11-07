<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Domain\Model;

use App\Pricing\Domain\Model\PriceListName;
use PHPUnit\Framework\TestCase;

final class PriceListNameTest extends TestCase
{
    public function testFromStringWithValidName(): void
    {
        $name = PriceListName::fromString('Summer Sale 2025');

        $this->assertInstanceOf(PriceListName::class, $name);
        $this->assertSame('Summer Sale 2025', $name->value());
    }

    public function testFromStringTrimsWhitespace(): void
    {
        $name = PriceListName::fromString('  Black Friday Sale  ');

        $this->assertSame('Black Friday Sale', $name->value());
    }

    public function testFromStringThrowsExceptionForEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('PriceList name cannot be empty');

        PriceListName::fromString('');
    }

    public function testFromStringThrowsExceptionForWhitespaceOnly(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('PriceList name cannot be empty');

        PriceListName::fromString('   ');
    }

    public function testFromStringThrowsExceptionForTooShortName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('PriceList name must be at least 3 characters long');

        PriceListName::fromString('AB');
    }

    public function testFromStringThrowsExceptionForTooLongName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('PriceList name cannot exceed 100 characters');

        PriceListName::fromString(str_repeat('A', 101));
    }

    public function testFromStringAcceptsMinimumLength(): void
    {
        $name = PriceListName::fromString('ABC');

        $this->assertSame('ABC', $name->value());
    }

    public function testFromStringAcceptsMaximumLength(): void
    {
        $nameString = str_repeat('A', 100);
        $name = PriceListName::fromString($nameString);

        $this->assertSame($nameString, $name->value());
    }

    public function testValueReturnsCorrectString(): void
    {
        $nameString = 'Holiday Promotion';
        $name = PriceListName::fromString($nameString);

        $this->assertSame($nameString, $name->value());
    }

    public function testEqualsReturnsTrueForSameName(): void
    {
        $name1 = PriceListName::fromString('VIP Pricing');
        $name2 = PriceListName::fromString('VIP Pricing');

        $this->assertTrue($name1->equals($name2));
    }

    public function testEqualsReturnsFalseForDifferentNames(): void
    {
        $name1 = PriceListName::fromString('Standard Pricing');
        $name2 = PriceListName::fromString('Premium Pricing');

        $this->assertFalse($name1->equals($name2));
    }

    public function testEqualsIsCaseSensitive(): void
    {
        $name1 = PriceListName::fromString('summer sale');
        $name2 = PriceListName::fromString('Summer Sale');

        $this->assertFalse($name1->equals($name2));
    }

    public function testFromStringAcceptsSpecialCharacters(): void
    {
        $name = PriceListName::fromString('20% Off - Spring 2025!');

        $this->assertSame('20% Off - Spring 2025!', $name->value());
    }

    public function testFromStringAcceptsUnicodeCharacters(): void
    {
        $name = PriceListName::fromString('Liquidación de Verano');

        $this->assertSame('Liquidación de Verano', $name->value());
    }
}
