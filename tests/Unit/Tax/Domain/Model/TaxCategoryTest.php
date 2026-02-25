<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tax\Domain\Model;

use App\Tax\Domain\Model\TaxCategory;
use PHPUnit\Framework\TestCase;

/**
 * Tests for TaxCategory enum (Domain/Model).
 */
final class TaxCategoryTest extends TestCase
{
    public function testAllCasesExist(): void
    {
        $cases = TaxCategory::cases();
        $values = array_map(fn (TaxCategory $c) => $c->value, $cases);

        $this->assertContains('standard', $values);
        $this->assertContains('reduced', $values);
        $this->assertContains('super_reduced', $values);
        $this->assertContains('zero', $values);
        $this->assertContains('exempt', $values);
        $this->assertCount(5, $cases);
    }

    public function testFromValidValues(): void
    {
        $this->assertSame(TaxCategory::STANDARD, TaxCategory::from('standard'));
        $this->assertSame(TaxCategory::REDUCED, TaxCategory::from('reduced'));
        $this->assertSame(TaxCategory::SUPER_REDUCED, TaxCategory::from('super_reduced'));
        $this->assertSame(TaxCategory::ZERO, TaxCategory::from('zero'));
        $this->assertSame(TaxCategory::EXEMPT, TaxCategory::from('exempt'));
    }

    public function testFromInvalidValueThrows(): void
    {
        $this->expectException(\ValueError::class);

        TaxCategory::from('invalid');
    }

    public function testIsExemptOnlyForExempt(): void
    {
        $this->assertTrue(TaxCategory::EXEMPT->isExempt());
        $this->assertFalse(TaxCategory::STANDARD->isExempt());
        $this->assertFalse(TaxCategory::REDUCED->isExempt());
        $this->assertFalse(TaxCategory::ZERO->isExempt());
    }

    public function testIsZeroRateForZeroAndExempt(): void
    {
        $this->assertTrue(TaxCategory::ZERO->isZeroRate());
        $this->assertTrue(TaxCategory::EXEMPT->isZeroRate());
        $this->assertFalse(TaxCategory::STANDARD->isZeroRate());
        $this->assertFalse(TaxCategory::REDUCED->isZeroRate());
        $this->assertFalse(TaxCategory::SUPER_REDUCED->isZeroRate());
    }

    public function testDescriptionReturnsNonEmptyStringForAll(): void
    {
        foreach (TaxCategory::cases() as $category) {
            $description = $category->description();
            $this->assertNotEmpty($description, "Description for {$category->value} should not be empty");
        }
    }

    public function testDescriptionSpecificValues(): void
    {
        $this->assertSame('Standard rate', TaxCategory::STANDARD->description());
        $this->assertSame('Reduced rate', TaxCategory::REDUCED->description());
        $this->assertSame('Tax exempt', TaxCategory::EXEMPT->description());
    }
}
