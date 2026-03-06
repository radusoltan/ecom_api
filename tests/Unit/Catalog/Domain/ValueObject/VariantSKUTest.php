<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\ValueObject;

use App\Catalog\Domain\ValueObject\OptionValueCode;
use App\Catalog\Domain\ValueObject\VariantSKU;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(VariantSKU::class)]
final class VariantSKUTest extends TestCase
{
    #[Test]
    public function itCreatesFromString(): void
    {
        $sku = VariantSKU::fromString('CLO-NIK-000123-red-xl');

        self::assertSame('CLO-NIK-000123-red-xl', $sku->toString());
        self::assertSame('CLO-NIK-000123-red-xl', $sku->value());
        self::assertSame('CLO-NIK-000123-red-xl', (string) $sku);
    }

    #[Test]
    public function itGeneratesFromBaseSkuAndValueCodes(): void
    {
        $sku = VariantSKU::generate('CLO-NIK-000123', [
            OptionValueCode::fromString('red'),
            OptionValueCode::fromString('xl'),
        ]);

        self::assertSame('CLO-NIK-000123-red-xl', $sku->toString());
    }

    #[Test]
    public function itExtractsBaseSku(): void
    {
        $sku = VariantSKU::fromString('CLO-NIK-000123-red-xl');

        self::assertSame('CLO-NIK-000123', $sku->getBaseSku());
    }

    #[Test]
    public function itExtractsVariantSuffix(): void
    {
        $sku = VariantSKU::fromString('CLO-NIK-000123-red-xl');

        self::assertSame('red-xl', $sku->getVariantSuffix());
    }

    #[Test]
    public function itChecksEquality(): void
    {
        $sku1 = VariantSKU::fromString('CLO-NIK-000123-red');
        $sku2 = VariantSKU::fromString('CLO-NIK-000123-red');
        $sku3 = VariantSKU::fromString('CLO-NIK-000123-blue');

        self::assertTrue($sku1->equals($sku2));
        self::assertFalse($sku1->equals($sku3));
    }

    #[Test]
    public function itThrowsOnEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be empty');
        VariantSKU::fromString('');
    }

    #[Test]
    public function itThrowsOnTooLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot exceed');
        VariantSKU::fromString('CLO-NIK-000123-'.str_repeat('a', 60));
    }

    #[Test]
    public function itThrowsOnInvalidFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid variant SKU format');
        VariantSKU::fromString('invalid-sku');
    }

    #[Test]
    public function itThrowsWhenGeneratingWithEmptyValueCodes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one');
        VariantSKU::generate('CLO-NIK-000123', []);
    }

    #[Test]
    public function itThrowsWhenGeneratingWithInvalidBaseSku(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid base SKU');
        VariantSKU::generate('INVALID', [OptionValueCode::fromString('red')]);
    }

    #[Test]
    public function itTrimsInput(): void
    {
        $sku = VariantSKU::fromString('  CLO-NIK-000123-red  ');

        self::assertSame('CLO-NIK-000123-red', $sku->toString());
    }

    #[Test]
    public function itLowercasesSuffixOnGenerate(): void
    {
        $sku = VariantSKU::generate('CLO-NIK-000123', [
            OptionValueCode::fromString('red'),
        ]);

        self::assertSame('CLO-NIK-000123-red', $sku->toString());
    }
}
