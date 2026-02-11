<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Domain\ValueObject;

use App\Pricing\Domain\ValueObject\PriceChangeSource;
use PHPUnit\Framework\TestCase;

final class PriceChangeSourceTest extends TestCase
{
    public function testCanCreateFromValue(): void
    {
        $manual = PriceChangeSource::from('manual');
        $this->assertSame('manual', $manual->value);

        $import = PriceChangeSource::from('import');
        $this->assertSame('import', $import->value);

        $promotion = PriceChangeSource::from('promotion');
        $this->assertSame('promotion', $promotion->value);

        $flashSale = PriceChangeSource::from('flash_sale');
        $this->assertSame('flash_sale', $flashSale->value);
    }

    public function testCanCreateFromString(): void
    {
        $manual = PriceChangeSource::fromString('manual');
        $this->assertSame('manual', $manual->value);
    }

    public function testThrowsExceptionForInvalidValue(): void
    {
        $this->expectException(\ValueError::class);
        PriceChangeSource::from('invalid_source');
    }

    public function testLabelReturnsCorrectValues(): void
    {
        $this->assertSame('Manual Change', PriceChangeSource::MANUAL->label());
        $this->assertSame('Bulk Import', PriceChangeSource::IMPORT->label());
        $this->assertSame('Promotion', PriceChangeSource::PROMOTION->label());
        $this->assertSame('Flash Sale', PriceChangeSource::FLASH_SALE->label());
    }

    public function testIsAutomatedReturnsTrueForPromotionAndFlashSale(): void
    {
        $this->assertFalse(PriceChangeSource::MANUAL->isAutomated());
        $this->assertFalse(PriceChangeSource::IMPORT->isAutomated());
        $this->assertTrue(PriceChangeSource::PROMOTION->isAutomated());
        $this->assertTrue(PriceChangeSource::FLASH_SALE->isAutomated());
    }

    public function testEqualsComparesCorrectly(): void
    {
        $manual1 = PriceChangeSource::MANUAL;
        $manual2 = PriceChangeSource::MANUAL;
        $import = PriceChangeSource::IMPORT;

        $this->assertTrue($manual1->equals($manual2));
        $this->assertFalse($manual1->equals($import));
    }

    public function testAllReturnsAllCases(): void
    {
        $all = PriceChangeSource::all();
        $this->assertCount(4, $all);
        $this->assertContains(PriceChangeSource::MANUAL, $all);
        $this->assertContains(PriceChangeSource::IMPORT, $all);
        $this->assertContains(PriceChangeSource::PROMOTION, $all);
        $this->assertContains(PriceChangeSource::FLASH_SALE, $all);
    }

    public function testValuesReturnsArrayOfValues(): void
    {
        $values = PriceChangeSource::values();
        $this->assertCount(4, $values);
        $this->assertContains('manual', $values);
        $this->assertContains('import', $values);
        $this->assertContains('promotion', $values);
        $this->assertContains('flash_sale', $values);
    }
}
