<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tax\Domain\Service;

use App\Tax\Domain\Service\TaxCalculationResult;
use App\Tax\Domain\ValueObject\TaxCategory;
use App\Tax\Domain\ValueObject\TaxJurisdiction;
use App\Tax\Domain\ValueObject\TaxRate;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TaxCalculationResult::class)]
final class TaxCalculationResultTest extends TestCase
{
    private function jurisdiction(): TaxJurisdiction
    {
        return TaxJurisdiction::fromCountry('DE');
    }

    private function category(): TaxCategory
    {
        return TaxCategory::standard();
    }

    #[Test]
    public function itCreatesStandardResult(): void
    {
        $result = TaxCalculationResult::create(
            netAmountInCents: 10000,
            taxAmountInCents: 1900,
            appliedRate: TaxRate::fromPercentage(19.0),
            taxJurisdiction: $this->jurisdiction(),
            category: $this->category(),
            taxType: 'vat',
        );

        self::assertSame(10000, $result->netAmountInCents);
        self::assertSame(1900, $result->taxAmountInCents);
        self::assertSame(11900, $result->grossAmountInCents);
        self::assertSame('vat', $result->taxType);
        self::assertFalse($result->reverseCharge);
        self::assertNull($result->exemptionReason);
        self::assertFalse($result->isExempt());
        self::assertFalse($result->isReverseCharge());
    }

    #[Test]
    public function itCreatesExemptResult(): void
    {
        $result = TaxCalculationResult::exempt(
            amountInCents: 5000,
            jurisdiction: $this->jurisdiction(),
            category: $this->category(),
            reason: 'Charity organization',
        );

        self::assertSame(5000, $result->netAmountInCents);
        self::assertSame(0, $result->taxAmountInCents);
        self::assertSame(5000, $result->grossAmountInCents);
        self::assertTrue($result->isExempt());
        self::assertSame('Charity organization', $result->exemptionReason);
        self::assertSame('exempt', $result->taxType);
    }

    #[Test]
    public function itCreatesReverseChargeResult(): void
    {
        $result = TaxCalculationResult::reverseCharge(
            amountInCents: 20000,
            theoreticalRate: TaxRate::fromPercentage(19.0),
            jurisdiction: $this->jurisdiction(),
            category: $this->category(),
        );

        self::assertSame(20000, $result->netAmountInCents);
        self::assertSame(0, $result->taxAmountInCents);
        self::assertSame(20000, $result->grossAmountInCents);
        self::assertTrue($result->isReverseCharge());
        self::assertTrue($result->isExempt());
        self::assertSame('vat_reverse_charge', $result->taxType);
    }

    #[Test]
    public function itCreatesCompoundResult(): void
    {
        $components = [
            ['name' => 'State', 'rate' => 6.0, 'amount' => 600],
            ['name' => 'County', 'rate' => 1.5, 'amount' => 150],
            ['name' => 'City', 'rate' => 0.5, 'amount' => 50],
        ];

        $result = TaxCalculationResult::compound(
            netAmountInCents: 10000,
            jurisdiction: TaxJurisdiction::fromCountryAndRegion('US', 'CA'),
            category: $this->category(),
            taxType: 'sales_tax',
            components: $components,
        );

        self::assertSame(10000, $result->netAmountInCents);
        self::assertSame(800, $result->taxAmountInCents);
        self::assertSame(10800, $result->grossAmountInCents);
        self::assertSame('sales_tax', $result->taxType);
        self::assertArrayHasKey('components', $result->breakdown);
    }

    #[Test]
    public function itCalculatesEffectiveRate(): void
    {
        $result = TaxCalculationResult::create(
            netAmountInCents: 10000,
            taxAmountInCents: 2100,
            appliedRate: TaxRate::fromPercentage(21.0),
            taxJurisdiction: $this->jurisdiction(),
            category: $this->category(),
            taxType: 'vat',
        );

        self::assertSame(21.0, $result->effectiveRate());
    }

    #[Test]
    public function itReturnsZeroEffectiveRateForZeroNet(): void
    {
        $result = TaxCalculationResult::create(
            netAmountInCents: 0,
            taxAmountInCents: 0,
            appliedRate: TaxRate::zero(),
            taxJurisdiction: $this->jurisdiction(),
            category: $this->category(),
            taxType: 'vat',
        );

        self::assertSame(0.0, $result->effectiveRate());
    }

    #[Test]
    public function itReturnsPercentage(): void
    {
        $result = TaxCalculationResult::create(
            netAmountInCents: 10000,
            taxAmountInCents: 1900,
            appliedRate: TaxRate::fromPercentage(19.0),
            taxJurisdiction: $this->jurisdiction(),
            category: $this->category(),
            taxType: 'vat',
        );

        self::assertSame(19.0, $result->percentage());
    }

    #[Test]
    public function itCreatesWithReverseChargeFlag(): void
    {
        $result = TaxCalculationResult::create(
            netAmountInCents: 10000,
            taxAmountInCents: 0,
            appliedRate: TaxRate::zero(),
            taxJurisdiction: $this->jurisdiction(),
            category: $this->category(),
            taxType: 'vat',
            reverseCharge: true,
            exemptionReason: 'B2B reverse charge',
        );

        self::assertTrue($result->isReverseCharge());
        self::assertTrue($result->isExempt());
        self::assertSame('B2B reverse charge', $result->exemptionReason);
    }
}
