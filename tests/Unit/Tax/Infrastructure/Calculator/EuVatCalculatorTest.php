<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tax\Infrastructure\Calculator;

use App\Tax\Domain\Service\TaxCalculationResult;
use App\Tax\Domain\ValueObject\TaxCategory;
use App\Tax\Domain\ValueObject\TaxJurisdiction;
use App\Tax\Infrastructure\Calculator\EuVatCalculator;
use PHPUnit\Framework\TestCase;

final class EuVatCalculatorTest extends TestCase
{
    private EuVatCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new EuVatCalculator();
    }

    public function testGetNameReturnsEuVat(): void
    {
        $this->assertSame('eu_vat', $this->calculator->getName());
    }

    public function testSupportsReturnsTrueForEuCountries(): void
    {
        $euCountries = ['DE', 'FR', 'IT', 'ES', 'NL', 'PL', 'BE', 'AT', 'SE', 'DK'];

        foreach ($euCountries as $countryCode) {
            $jurisdiction = TaxJurisdiction::fromCountry($countryCode);
            $this->assertTrue(
                $this->calculator->supports($jurisdiction),
                sprintf('Expected supports() to return true for %s', $countryCode)
            );
        }
    }

    public function testSupportsReturnsFalseForNonEuCountries(): void
    {
        $nonEuCountries = ['US', 'GB', 'AU', 'JP', 'CA', 'CH'];

        foreach ($nonEuCountries as $countryCode) {
            $jurisdiction = TaxJurisdiction::fromCountry($countryCode);
            $this->assertFalse(
                $this->calculator->supports($jurisdiction),
                sprintf('Expected supports() to return false for %s', $countryCode)
            );
        }
    }

    public function testCalculateStandardRateGermany(): void
    {
        $sellerJurisdiction = TaxJurisdiction::fromCountry('DE');
        $buyerJurisdiction = TaxJurisdiction::fromCountry('DE');
        $category = TaxCategory::standard();

        $result = $this->calculator->calculate(
            amountInCents: 10000,
            sellerJurisdiction: $sellerJurisdiction,
            buyerJurisdiction: $buyerJurisdiction,
            category: $category,
        );

        $this->assertInstanceOf(TaxCalculationResult::class, $result);
        $this->assertSame(10000, $result->netAmountInCents);
        // Germany standard VAT = 19%
        $this->assertSame(1900, $result->taxAmountInCents);
        $this->assertSame(11900, $result->grossAmountInCents);
        $this->assertSame(19.0, $result->appliedRate->getPercentage());
        $this->assertSame('vat', $result->taxType);
        $this->assertFalse($result->reverseCharge);
        $this->assertNull($result->exemptionReason);
    }

    public function testCalculateStandardRateFrance(): void
    {
        $sellerJurisdiction = TaxJurisdiction::fromCountry('FR');
        $buyerJurisdiction = TaxJurisdiction::fromCountry('FR');
        $category = TaxCategory::standard();

        $result = $this->calculator->calculate(
            amountInCents: 10000,
            sellerJurisdiction: $sellerJurisdiction,
            buyerJurisdiction: $buyerJurisdiction,
            category: $category,
        );

        // France standard VAT = 20%
        $this->assertSame(2000, $result->taxAmountInCents);
        $this->assertSame(12000, $result->grossAmountInCents);
        $this->assertSame(20.0, $result->appliedRate->getPercentage());
    }

    public function testCalculateReducedRateGermany(): void
    {
        $sellerJurisdiction = TaxJurisdiction::fromCountry('DE');
        $buyerJurisdiction = TaxJurisdiction::fromCountry('DE');
        $category = TaxCategory::reduced();

        $result = $this->calculator->calculate(
            amountInCents: 10000,
            sellerJurisdiction: $sellerJurisdiction,
            buyerJurisdiction: $buyerJurisdiction,
            category: $category,
        );

        // Germany reduced VAT = 7%
        $this->assertSame(700, $result->taxAmountInCents);
        $this->assertSame(10700, $result->grossAmountInCents);
        $this->assertSame(7.0, $result->appliedRate->getPercentage());
    }

    public function testCalculateExemptCategoryReturnsExemptResult(): void
    {
        $sellerJurisdiction = TaxJurisdiction::fromCountry('DE');
        $buyerJurisdiction = TaxJurisdiction::fromCountry('DE');
        $category = TaxCategory::exempt();

        $result = $this->calculator->calculate(
            amountInCents: 10000,
            sellerJurisdiction: $sellerJurisdiction,
            buyerJurisdiction: $buyerJurisdiction,
            category: $category,
        );

        $this->assertSame(0, $result->taxAmountInCents);
        $this->assertSame(10000, $result->grossAmountInCents);
        $this->assertSame('exempt', $result->taxType);
        $this->assertTrue($result->isExempt());
        $this->assertStringContainsString('Tax-exempt', $result->exemptionReason);
    }

    public function testCalculateZeroRatedCategoryReturnsExemptResult(): void
    {
        $sellerJurisdiction = TaxJurisdiction::fromCountry('DE');
        $buyerJurisdiction = TaxJurisdiction::fromCountry('DE');
        $category = TaxCategory::zeroRated();

        $result = $this->calculator->calculate(
            amountInCents: 10000,
            sellerJurisdiction: $sellerJurisdiction,
            buyerJurisdiction: $buyerJurisdiction,
            category: $category,
        );

        $this->assertSame(0, $result->taxAmountInCents);
        $this->assertSame('exempt', $result->taxType);
        $this->assertTrue($result->isExempt());
        $this->assertStringContainsString('Zero-rated', $result->exemptionReason);
    }

    public function testCalculateB2bCrossBorderAppliesReverseCharge(): void
    {
        $sellerJurisdiction = TaxJurisdiction::fromCountry('DE');
        $buyerJurisdiction = TaxJurisdiction::fromCountry('FR');
        $category = TaxCategory::standard();

        $result = $this->calculator->calculate(
            amountInCents: 10000,
            sellerJurisdiction: $sellerJurisdiction,
            buyerJurisdiction: $buyerJurisdiction,
            category: $category,
            isB2B: true,
            buyerVatNumber: 'FR12345678901',
        );

        $this->assertSame(0, $result->taxAmountInCents);
        $this->assertSame(10000, $result->grossAmountInCents);
        $this->assertTrue($result->reverseCharge);
        $this->assertSame('vat_reverse_charge', $result->taxType);
        $this->assertArrayHasKey('theoretical_rate', $result->breakdown);
    }

    public function testCalculateB2bDomesticDoesNotApplyReverseCharge(): void
    {
        $sellerJurisdiction = TaxJurisdiction::fromCountry('DE');
        $buyerJurisdiction = TaxJurisdiction::fromCountry('DE');
        $category = TaxCategory::standard();

        $result = $this->calculator->calculate(
            amountInCents: 10000,
            sellerJurisdiction: $sellerJurisdiction,
            buyerJurisdiction: $buyerJurisdiction,
            category: $category,
            isB2B: true,
            buyerVatNumber: 'DE123456789',
        );

        // Same country B2B - standard VAT applies
        $this->assertSame(1900, $result->taxAmountInCents);
        $this->assertFalse($result->reverseCharge);
        $this->assertSame('vat', $result->taxType);
    }

    public function testCalculateB2cCrossBorderDoesNotApplyReverseCharge(): void
    {
        $sellerJurisdiction = TaxJurisdiction::fromCountry('DE');
        $buyerJurisdiction = TaxJurisdiction::fromCountry('FR');
        $category = TaxCategory::standard();

        $result = $this->calculator->calculate(
            amountInCents: 10000,
            sellerJurisdiction: $sellerJurisdiction,
            buyerJurisdiction: $buyerJurisdiction,
            category: $category,
            isB2B: false,
        );

        // B2C cross-border - buyer country (FR) standard VAT = 20%
        $this->assertSame(2000, $result->taxAmountInCents);
        $this->assertFalse($result->reverseCharge);
        $this->assertSame('vat', $result->taxType);
    }

    public function testCalculateB2bWithoutVatNumberDoesNotApplyReverseCharge(): void
    {
        $sellerJurisdiction = TaxJurisdiction::fromCountry('DE');
        $buyerJurisdiction = TaxJurisdiction::fromCountry('FR');
        $category = TaxCategory::standard();

        $result = $this->calculator->calculate(
            amountInCents: 10000,
            sellerJurisdiction: $sellerJurisdiction,
            buyerJurisdiction: $buyerJurisdiction,
            category: $category,
            isB2B: true,
            buyerVatNumber: null,
        );

        // B2B without VAT number - apply buyer's standard VAT (FR = 20%)
        $this->assertSame(2000, $result->taxAmountInCents);
        $this->assertFalse($result->reverseCharge);
    }

    public function testCalculateThrowsForNonEuBuyer(): void
    {
        $sellerJurisdiction = TaxJurisdiction::fromCountry('DE');
        $buyerJurisdiction = TaxJurisdiction::fromCountry('US');
        $category = TaxCategory::standard();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/EU VAT calculator only supports EU jurisdictions/');

        $this->calculator->calculate(
            amountInCents: 10000,
            sellerJurisdiction: $sellerJurisdiction,
            buyerJurisdiction: $buyerJurisdiction,
            category: $category,
        );
    }

    public function testCalculateResultIncludesBreakdownWithCountryInfo(): void
    {
        $sellerJurisdiction = TaxJurisdiction::fromCountry('DE');
        $buyerJurisdiction = TaxJurisdiction::fromCountry('FR');
        $category = TaxCategory::standard();

        $result = $this->calculator->calculate(
            amountInCents: 10000,
            sellerJurisdiction: $sellerJurisdiction,
            buyerJurisdiction: $buyerJurisdiction,
            category: $category,
        );

        $this->assertArrayHasKey('seller_country', $result->breakdown);
        $this->assertArrayHasKey('buyer_country', $result->breakdown);
        $this->assertArrayHasKey('is_domestic', $result->breakdown);
        $this->assertArrayHasKey('is_b2b', $result->breakdown);
        $this->assertSame('DE', $result->breakdown['seller_country']);
        $this->assertSame('FR', $result->breakdown['buyer_country']);
        $this->assertFalse($result->breakdown['is_domestic']);
    }

    public function testCalculateHungaryHasHighestEuVatRate(): void
    {
        $sellerJurisdiction = TaxJurisdiction::fromCountry('HU');
        $buyerJurisdiction = TaxJurisdiction::fromCountry('HU');
        $category = TaxCategory::standard();

        $result = $this->calculator->calculate(
            amountInCents: 10000,
            sellerJurisdiction: $sellerJurisdiction,
            buyerJurisdiction: $buyerJurisdiction,
            category: $category,
        );

        // Hungary has highest EU VAT = 27%
        $this->assertSame(2700, $result->taxAmountInCents);
        $this->assertSame(27.0, $result->appliedRate->getPercentage());
    }

    public function testCalculateDenmarkHasNoReducedRateUsesStandard(): void
    {
        $sellerJurisdiction = TaxJurisdiction::fromCountry('DK');
        $buyerJurisdiction = TaxJurisdiction::fromCountry('DK');
        $category = TaxCategory::reduced();

        $result = $this->calculator->calculate(
            amountInCents: 10000,
            sellerJurisdiction: $sellerJurisdiction,
            buyerJurisdiction: $buyerJurisdiction,
            category: $category,
        );

        // Denmark has no reduced rate, falls back to standard 25%
        $this->assertSame(2500, $result->taxAmountInCents);
        $this->assertSame(25.0, $result->appliedRate->getPercentage());
    }

    public function testCalculateEffectiveRateCalculation(): void
    {
        $sellerJurisdiction = TaxJurisdiction::fromCountry('IT');
        $buyerJurisdiction = TaxJurisdiction::fromCountry('IT');
        $category = TaxCategory::standard();

        $result = $this->calculator->calculate(
            amountInCents: 10000,
            sellerJurisdiction: $sellerJurisdiction,
            buyerJurisdiction: $buyerJurisdiction,
            category: $category,
        );

        // Italy standard VAT = 22%
        $this->assertSame(2200, $result->taxAmountInCents);
        $this->assertEqualsWithDelta(22.0, $result->effectiveRate(), 0.01);
    }
}
