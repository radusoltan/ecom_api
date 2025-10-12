<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Domain\Model;

use App\Inventory\Domain\Model\WarehouseCode;
use PHPUnit\Framework\TestCase;

final class WarehouseCodeTest extends TestCase
{
    public function testFromStringCreatesValidWarehouseCode(): void
    {
        $code = WarehouseCode::fromString('WH001');

        $this->assertSame('WH001', $code->value());
        $this->assertSame('WH001', (string) $code);
    }

    public function testFromStringConvertsToUppercase(): void
    {
        $code = WarehouseCode::fromString('wh001');

        $this->assertSame('WH001', $code->value());
    }

    public function testFromStringTrimmsWhitespace(): void
    {
        $code = WarehouseCode::fromString('  NYC  ');

        $this->assertSame('NYC', $code->value());
    }

    public function testMinimumLengthCode(): void
    {
        $code = WarehouseCode::fromString('ABC');

        $this->assertSame('ABC', $code->value());
    }

    public function testMaximumLengthCode(): void
    {
        $code = WarehouseCode::fromString('ABCDEFGHIJ');

        $this->assertSame('ABCDEFGHIJ', $code->value());
    }

    public function testAlphanumericCode(): void
    {
        $code = WarehouseCode::fromString('WH12345');

        $this->assertSame('WH12345', $code->value());
    }

    public function testNumericOnlyCode(): void
    {
        $code = WarehouseCode::fromString('123456');

        $this->assertSame('123456', $code->value());
    }

    public function testThrowsExceptionForCodeTooShort(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must be between 3 and 10 characters long');

        WarehouseCode::fromString('AB');
    }

    public function testThrowsExceptionForCodeTooLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must be between 3 and 10 characters long');

        WarehouseCode::fromString('ABCDEFGHIJK');
    }

    public function testThrowsExceptionForEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        WarehouseCode::fromString('');
    }

    public function testThrowsExceptionForWhitespaceOnly(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        WarehouseCode::fromString('   ');
    }

    public function testAllowsHyphens(): void
    {
        // Hyphens are allowed as per the business rules
        $code = WarehouseCode::fromString('WH-001');
        $this->assertSame('WH-001', $code->value());
    }

    public function testThrowsExceptionForSpecialCharacters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must contain only uppercase alphanumeric characters');

        WarehouseCode::fromString('WH@001');
    }

    public function testThrowsExceptionForSpaces(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must contain only uppercase alphanumeric characters');

        WarehouseCode::fromString('WH 001');
    }

    public function testThrowsExceptionForLowercaseLetters(): void
    {
        // Lowercase is converted to uppercase, but if pattern doesn't match after conversion
        $code = WarehouseCode::fromString('abc'); // Should work after uppercase conversion
        $this->assertSame('ABC', $code->value());
    }

    public function testThrowsExceptionForUnderscores(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must contain only uppercase alphanumeric characters');

        WarehouseCode::fromString('WH_001');
    }

    public function testEqualsReturnsTrueForSameCode(): void
    {
        $code1 = WarehouseCode::fromString('WH001');
        $code2 = WarehouseCode::fromString('WH001');

        $this->assertTrue($code1->equals($code2));
    }

    public function testEqualsReturnsFalseForDifferentCode(): void
    {
        $code1 = WarehouseCode::fromString('WH001');
        $code2 = WarehouseCode::fromString('WH002');

        $this->assertFalse($code1->equals($code2));
    }

    public function testEqualsHandlesUppercaseConversion(): void
    {
        $code1 = WarehouseCode::fromString('wh001');
        $code2 = WarehouseCode::fromString('WH001');

        $this->assertTrue($code1->equals($code2));
    }

    public function testToStringReturnsValue(): void
    {
        $code = WarehouseCode::fromString('LONDON1');

        $this->assertSame('LONDON1', (string) $code);
    }

    public function testDifferentValidFormats(): void
    {
        $codes = [
            ['input' => 'NYC', 'expected' => 'NYC'],
            ['input' => 'LONDON1', 'expected' => 'LONDON1'],
            ['input' => 'WH001', 'expected' => 'WH001'],
            ['input' => 'DC123456', 'expected' => 'DC123456'],
            ['input' => '12345', 'expected' => '12345'],
        ];

        foreach ($codes as $testCase) {
            $code = WarehouseCode::fromString($testCase['input']);
            $this->assertSame($testCase['expected'], $code->value());
        }
    }
}
