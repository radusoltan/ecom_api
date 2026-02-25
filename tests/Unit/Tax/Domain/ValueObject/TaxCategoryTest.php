<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tax\Domain\ValueObject;

use App\Tax\Domain\ValueObject\TaxCategory;
use PHPUnit\Framework\TestCase;

/**
 * Tests for TaxCategory Value Object (Domain/ValueObject).
 */
final class TaxCategoryTest extends TestCase
{
    public function testFactoryMethods(): void
    {
        $this->assertSame('standard', TaxCategory::standard()->value());
        $this->assertSame('reduced', TaxCategory::reduced()->value());
        $this->assertSame('zero_rated', TaxCategory::zeroRated()->value());
        $this->assertSame('exempt', TaxCategory::exempt()->value());
    }

    public function testFromStringCreatesValidCategory(): void
    {
        $category = TaxCategory::fromString('standard');
        $this->assertSame('standard', $category->value());
    }

    public function testFromStringNormalizesCase(): void
    {
        $category = TaxCategory::fromString('STANDARD');
        $this->assertSame('standard', $category->value());
    }

    public function testFromStringFailsWithInvalidCategory(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid tax category');

        TaxCategory::fromString('invalid');
    }

    public function testIsStandard(): void
    {
        $this->assertTrue(TaxCategory::standard()->isStandard());
        $this->assertFalse(TaxCategory::reduced()->isStandard());
    }

    public function testIsReduced(): void
    {
        $this->assertTrue(TaxCategory::reduced()->isReduced());
        $this->assertFalse(TaxCategory::standard()->isReduced());
    }

    public function testIsZeroRated(): void
    {
        $this->assertTrue(TaxCategory::zeroRated()->isZeroRated());
        $this->assertFalse(TaxCategory::standard()->isZeroRated());
    }

    public function testIsExempt(): void
    {
        $this->assertTrue(TaxCategory::exempt()->isExempt());
        $this->assertFalse(TaxCategory::standard()->isExempt());
    }

    public function testEquals(): void
    {
        $a = TaxCategory::standard();
        $b = TaxCategory::standard();
        $c = TaxCategory::reduced();

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }

    public function testToStringReturnsValue(): void
    {
        $this->assertSame('standard', (string) TaxCategory::standard());
        $this->assertSame('exempt', (string) TaxCategory::exempt());
    }
}
