<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\Model;

use App\Catalog\Domain\Model\SKU;
use PHPUnit\Framework\TestCase;

final class SKUTest extends TestCase
{
    public function testCreateSKUFromValidString(): void
    {
        $sku = SKU::fromString('ELC-TST-000001');

        $this->assertSame('ELC-TST-000001', $sku->value());
    }

    public function testSKUUppercasesAndTrimsInput(): void
    {
        $sku = SKU::fromString('  elc-tst-000001  ');

        $this->assertSame('ELC-TST-000001', $sku->value());
    }

    public function testSKURejectsInvalidCategorySegment(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SKU must match pattern: AAA-BBB-000000 (upper-case letters and 6 digits)');

        SKU::fromString('EL-TST-000001');
    }

    public function testSKURejectsInvalidVendorSegment(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SKU must match pattern: AAA-BBB-000000 (upper-case letters and 6 digits)');

        SKU::fromString('ELC-TS-000001');
    }

    public function testSKURejectsInvalidSequence(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SKU must match pattern: AAA-BBB-000000 (upper-case letters and 6 digits)');

        SKU::fromString('ELC-TST-ABCDEF');
    }

    public function testSKURejectsSequenceWithIncorrectLength(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SKU must match pattern: AAA-BBB-000000 (upper-case letters and 6 digits)');

        SKU::fromString('ELC-TST-00001');
    }

    public function testSKUEqualityUsesCanonicalValue(): void
    {
        $sku1 = SKU::fromString('elc-tst-000001');
        $sku2 = SKU::fromString('ELC-TST-000001');
        $sku3 = SKU::fromString('ELC-TST-000002');

        $this->assertTrue($sku1->equals($sku2));
        $this->assertFalse($sku1->equals($sku3));
    }

    public function testSKUToStringReturnsNormalizedValue(): void
    {
        $sku = SKU::fromString('elc-tst-000001');

        $this->assertSame('ELC-TST-000001', (string) $sku);
    }
}
