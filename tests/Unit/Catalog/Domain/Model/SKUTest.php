<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\Model;

use App\Catalog\Domain\Model\SKU;
use PHPUnit\Framework\TestCase;

final class SKUTest extends TestCase
{
    public function testCreateSKUFromValidString(): void
    {
        $sku = SKU::fromString('LAPTOP-001');

        $this->assertSame('LAPTOP-001', $sku->value());
    }

    public function testSKUAutoConvertsToUppercase(): void
    {
        $sku = SKU::fromString('laptop-001');

        $this->assertSame('LAPTOP-001', $sku->value());
    }

    public function testSKUTrimpsWhitespace(): void
    {
        $sku = SKU::fromString('  LAPTOP-001  ');

        $this->assertSame('LAPTOP-001', $sku->value());
    }

    public function testSKUWithNumbers(): void
    {
        $sku = SKU::fromString('PROD-12345');

        $this->assertSame('PROD-12345', $sku->value());
    }

    public function testSKUWithHyphens(): void
    {
        $sku = SKU::fromString('PROD-CAT-001');

        $this->assertSame('PROD-CAT-001', $sku->value());
    }

    public function testSKUMinimumLength(): void
    {
        $sku = SKU::fromString('ABC');

        $this->assertSame('ABC', $sku->value());
    }

    public function testSKUMaximumLength(): void
    {
        $longSKU = str_repeat('A', 50);
        $sku = SKU::fromString($longSKU);

        $this->assertSame($longSKU, $sku->value());
    }

    public function testSKUThrowsExceptionForEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SKU must match pattern: uppercase letters, numbers, and hyphens, 3-50 chars');

        SKU::fromString('');
    }

    public function testSKUThrowsExceptionForWhitespaceOnly(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SKU must match pattern: uppercase letters, numbers, and hyphens, 3-50 chars');

        SKU::fromString('   ');
    }

    public function testSKUThrowsExceptionForTooShort(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SKU must match pattern: uppercase letters, numbers, and hyphens, 3-50 chars');

        SKU::fromString('AB');
    }

    public function testSKUThrowsExceptionForTooLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SKU must match pattern: uppercase letters, numbers, and hyphens, 3-50 chars');

        $longSKU = str_repeat('A', 51);
        SKU::fromString($longSKU);
    }

    public function testSKUThrowsExceptionForInvalidCharacters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SKU must match pattern: uppercase letters, numbers, and hyphens, 3-50 chars');

        SKU::fromString('PROD@001');
    }

    public function testSKUThrowsExceptionForSpaces(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SKU must match pattern: uppercase letters, numbers, and hyphens, 3-50 chars');

        SKU::fromString('PROD 001');
    }

    public function testSKUThrowsExceptionForSpecialCharacters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SKU must match pattern: uppercase letters, numbers, and hyphens, 3-50 chars');

        SKU::fromString('PROD#001');
    }

    public function testSKUEquality(): void
    {
        $sku1 = SKU::fromString('LAPTOP-001');
        $sku2 = SKU::fromString('LAPTOP-001');
        $sku3 = SKU::fromString('LAPTOP-002');

        $this->assertTrue($sku1->equals($sku2));
        $this->assertFalse($sku1->equals($sku3));
    }

    public function testSKUEqualityIsCaseInsensitive(): void
    {
        $sku1 = SKU::fromString('laptop-001');
        $sku2 = SKU::fromString('LAPTOP-001');

        $this->assertTrue($sku1->equals($sku2));
    }

    public function testSKUToString(): void
    {
        $sku = SKU::fromString('LAPTOP-001');

        $this->assertSame('LAPTOP-001', (string) $sku);
    }

    public function testSKUWithNumericOnly(): void
    {
        $sku = SKU::fromString('123456');

        $this->assertSame('123456', $sku->value());
    }

    public function testSKUWithAlphanumeric(): void
    {
        $sku = SKU::fromString('ABC123XYZ');

        $this->assertSame('ABC123XYZ', $sku->value());
    }

    public function testSKUWithMultipleHyphens(): void
    {
        $sku = SKU::fromString('PROD-CAT-SUB-001');

        $this->assertSame('PROD-CAT-SUB-001', $sku->value());
    }
}
