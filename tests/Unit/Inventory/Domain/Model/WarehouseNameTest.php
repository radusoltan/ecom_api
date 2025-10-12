<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Domain\Model;

use App\Inventory\Domain\Model\WarehouseName;
use PHPUnit\Framework\TestCase;

final class WarehouseNameTest extends TestCase
{
    public function testFromStringCreatesValidWarehouseName(): void
    {
        $name = WarehouseName::fromString('Main Warehouse');

        $this->assertSame('Main Warehouse', $name->value());
        $this->assertSame('Main Warehouse', (string) $name);
    }

    public function testFromStringTrimsWhitespace(): void
    {
        $name = WarehouseName::fromString('  New York Distribution Center  ');

        $this->assertSame('New York Distribution Center', $name->value());
    }

    public function testMinimumLengthName(): void
    {
        $name = WarehouseName::fromString('NY');

        $this->assertSame('NY', $name->value());
    }

    public function testMaximumLengthName(): void
    {
        $longName = str_repeat('A', 100);
        $name = WarehouseName::fromString($longName);

        $this->assertSame($longName, $name->value());
    }

    public function testThrowsExceptionForNameTooShort(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must be at least 2 characters long');

        WarehouseName::fromString('A');
    }

    public function testThrowsExceptionForNameTooLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot exceed 100 characters');

        $longName = str_repeat('A', 101);
        WarehouseName::fromString($longName);
    }

    public function testThrowsExceptionForEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        WarehouseName::fromString('');
    }

    public function testThrowsExceptionForWhitespaceOnly(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        WarehouseName::fromString('   ');
    }

    public function testThrowsExceptionForTabsOnly(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        WarehouseName::fromString("\t\t");
    }

    public function testAcceptsSpecialCharacters(): void
    {
        $name = WarehouseName::fromString('Warehouse #1 - North');

        $this->assertSame('Warehouse #1 - North', $name->value());
    }

    public function testAcceptsMultibyteCharacters(): void
    {
        $name = WarehouseName::fromString('Магазин Москва');

        $this->assertSame('Магазин Москва', $name->value());
    }

    public function testAcceptsAccentedCharacters(): void
    {
        $name = WarehouseName::fromString('Entrepôt de Paris');

        $this->assertSame('Entrepôt de Paris', $name->value());
    }

    public function testEqualsReturnsTrueForSameName(): void
    {
        $name1 = WarehouseName::fromString('Main Warehouse');
        $name2 = WarehouseName::fromString('Main Warehouse');

        $this->assertTrue($name1->equals($name2));
    }

    public function testEqualsReturnsFalseForDifferentName(): void
    {
        $name1 = WarehouseName::fromString('Main Warehouse');
        $name2 = WarehouseName::fromString('Secondary Warehouse');

        $this->assertFalse($name1->equals($name2));
    }

    public function testEqualsIsCaseSensitive(): void
    {
        $name1 = WarehouseName::fromString('Warehouse');
        $name2 = WarehouseName::fromString('warehouse');

        $this->assertFalse($name1->equals($name2));
    }

    public function testToStringReturnsValue(): void
    {
        $name = WarehouseName::fromString('Distribution Center');

        $this->assertSame('Distribution Center', (string) $name);
    }

    public function testVariousValidNames(): void
    {
        $names = [
            ['input' => 'NYC Warehouse', 'expected' => 'NYC Warehouse'],
            ['input' => 'Distribution Center #1', 'expected' => 'Distribution Center #1'],
            ['input' => 'Main Storage Facility', 'expected' => 'Main Storage Facility'],
            ['input' => 'DC-EAST-001', 'expected' => 'DC-EAST-001'],
            ['input' => 'Fulfillment Center (Boston)', 'expected' => 'Fulfillment Center (Boston)'],
        ];

        foreach ($names as $testCase) {
            $name = WarehouseName::fromString($testCase['input']);
            $this->assertSame($testCase['expected'], $name->value());
        }
    }

    public function testTrimsButPreservesInternalWhitespace(): void
    {
        $name = WarehouseName::fromString('  New  York  Warehouse  ');

        $this->assertSame('New  York  Warehouse', $name->value());
    }
}
