<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\Model;

use App\Catalog\Domain\Model\SKU;
use PHPUnit\Framework\TestCase;

final class SKUTest extends TestCase
{
    public function testCreateSKUFromValidString(): void
    {
        $sku = SKU::fromString('PRD-123456');

        $this->assertSame('PRD-123456', $sku->value());
    }

    public function testSKUUppercasesAndTrimsInput(): void
    {
        $sku = SKU::fromString('  prd-123456  ');

        $this->assertSame('PRD-123456', $sku->value());
    }

    public function testSKURejectsInvalidPrefixTooShort(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SKU must match pattern: PRD-123456 (3 uppercase letters, hyphen, 6 digits)');

        SKU::fromString('PR-123456');
    }

    public function testSKURejectsInvalidPrefixTooLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SKU must match pattern: PRD-123456 (3 uppercase letters, hyphen, 6 digits)');

        SKU::fromString('PROD-123456');
    }

    public function testSKURejectsInvalidSequence(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SKU must match pattern: PRD-123456 (3 uppercase letters, hyphen, 6 digits)');

        SKU::fromString('PRD-ABCDEF');
    }

    public function testSKURejectsSequenceWithIncorrectLength(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SKU must match pattern: PRD-123456 (3 uppercase letters, hyphen, 6 digits)');

        SKU::fromString('PRD-12345');
    }

    public function testSKUEqualityUsesCanonicalValue(): void
    {
        $sku1 = SKU::fromString('prd-123456');
        $sku2 = SKU::fromString('PRD-123456');
        $sku3 = SKU::fromString('PRD-654321');

        $this->assertTrue($sku1->equals($sku2));
        $this->assertFalse($sku1->equals($sku3));
    }

    public function testSKUToStringReturnsNormalizedValue(): void
    {
        $sku = SKU::fromString('prd-123456');

        $this->assertSame('PRD-123456', (string) $sku);
    }

    public function testSKUAcceptsDifferentValidPrefixes(): void
    {
        $sku1 = SKU::fromString('CAT-000001');
        $sku2 = SKU::fromString('ELC-999999');

        $this->assertSame('CAT-000001', $sku1->value());
        $this->assertSame('ELC-999999', $sku2->value());
    }

    public function testSKURejectsPrefixWithNumbers(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SKU must match pattern: PRD-123456 (3 uppercase letters, hyphen, 6 digits)');

        SKU::fromString('PR1-123456');
    }

    public function testSKURejectsSequenceTooLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SKU must match pattern: PRD-123456 (3 uppercase letters, hyphen, 6 digits)');

        SKU::fromString('PRD-1234567');
    }
}
