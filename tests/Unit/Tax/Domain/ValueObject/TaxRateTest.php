<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tax\Domain\ValueObject;

use App\Tax\Domain\ValueObject\TaxRate;
use PHPUnit\Framework\TestCase;

/**
 * Comprehensive tests for TaxRate Value Object.
 *
 * Tests all edge cases including boundary values, calculations, and validation.
 */
final class TaxRateTest extends TestCase
{
    public function testFromPercentageCreatesValidTaxRate(): void
    {
        // Given: Valid tax rate percentage
        $rate = TaxRate::fromPercentage(19.0);

        // Then: Should create tax rate correctly
        $this->assertSame(19.0, $rate->getPercentage());
        $this->assertSame(0.19, $rate->getDecimal());
    }

    public function testZeroCreatesZeroTaxRate(): void
    {
        // Given: Zero tax rate factory method
        $rate = TaxRate::zero();

        // Then: Should create 0% tax rate
        $this->assertSame(0.0, $rate->getPercentage());
        $this->assertSame(0.0, $rate->getDecimal());
    }

    public function testMinimumRateAllowed(): void
    {
        // Given: 0% tax rate (minimum)
        $rate = TaxRate::fromPercentage(0.0);

        // Then: Should be valid
        $this->assertSame(0.0, $rate->getPercentage());
    }

    public function testMaximumRateAllowed(): void
    {
        // Given: 100% tax rate (maximum)
        $rate = TaxRate::fromPercentage(100.0);

        // Then: Should be valid
        $this->assertSame(100.0, $rate->getPercentage());
        $this->assertSame(1.0, $rate->getDecimal());
    }

    public function testNegativeRateThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tax rate must be between 0 and 100, got -5.00');

        TaxRate::fromPercentage(-5.0);
    }

    public function testRateAbove100ThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tax rate must be between 0 and 100, got 150.00');

        TaxRate::fromPercentage(150.0);
    }

    public function testGetDecimalConversion(): void
    {
        // Given: Different tax rates
        $rate19 = TaxRate::fromPercentage(19.0);
        $rate20 = TaxRate::fromPercentage(20.0);
        $rate27 = TaxRate::fromPercentage(27.0);

        // Then: Should convert to decimal correctly
        $this->assertSame(0.19, $rate19->getDecimal());
        $this->assertSame(0.20, $rate20->getDecimal());
        $this->assertSame(0.27, $rate27->getDecimal());
    }

    public function testCalculateTaxAmountForStandardPrice(): void
    {
        // Given: 19% VAT rate
        $rate = TaxRate::fromPercentage(19.0);

        // When: Calculate tax for €100.00 (10000 cents)
        $taxAmount = $rate->calculateTaxAmount(10000);

        // Then: Should calculate €19.00 (1900 cents)
        $this->assertSame(1900, $taxAmount);
    }

    public function testCalculateTaxAmountForZeroRate(): void
    {
        // Given: 0% tax rate
        $rate = TaxRate::zero();

        // When: Calculate tax for €100.00
        $taxAmount = $rate->calculateTaxAmount(10000);

        // Then: Should return 0
        $this->assertSame(0, $taxAmount);
    }

    public function testCalculateTaxAmountFor100PercentRate(): void
    {
        // Given: 100% tax rate
        $rate = TaxRate::fromPercentage(100.0);

        // When: Calculate tax for €50.00 (5000 cents)
        $taxAmount = $rate->calculateTaxAmount(5000);

        // Then: Should equal the original amount (5000 cents)
        $this->assertSame(5000, $taxAmount);
    }

    public function testCalculateTaxAmountRoundsCorrectly(): void
    {
        // Given: 19% VAT rate
        $rate = TaxRate::fromPercentage(19.0);

        // When: Calculate tax for €99.99 (9999 cents)
        // 9999 * 0.19 = 1899.81 → should round to 1900
        $taxAmount = $rate->calculateTaxAmount(9999);

        // Then: Should round to nearest cent (1900 cents)
        $this->assertSame(1900, $taxAmount);
    }

    public function testCalculateTaxAmountForFractionalRate(): void
    {
        // Given: 19.5% tax rate
        $rate = TaxRate::fromPercentage(19.5);

        // When: Calculate tax for €100.00 (10000 cents)
        $taxAmount = $rate->calculateTaxAmount(10000);

        // Then: Should calculate €19.50 (1950 cents)
        $this->assertSame(1950, $taxAmount);
    }

    public function testCalculatePriceWithTax(): void
    {
        // Given: 19% VAT rate
        $rate = TaxRate::fromPercentage(19.0);

        // When: Calculate price with tax for €100.00 (10000 cents)
        $priceWithTax = $rate->calculatePriceWithTax(10000);

        // Then: Should return €119.00 (11900 cents)
        $this->assertSame(11900, $priceWithTax);
    }

    public function testCalculatePriceWithTaxForZeroRate(): void
    {
        // Given: 0% tax rate
        $rate = TaxRate::zero();

        // When: Calculate price with tax
        $priceWithTax = $rate->calculatePriceWithTax(10000);

        // Then: Should return original price
        $this->assertSame(10000, $priceWithTax);
    }

    public function testEqualsTrueForSameRate(): void
    {
        // Given: Two identical tax rates
        $rate1 = TaxRate::fromPercentage(19.0);
        $rate2 = TaxRate::fromPercentage(19.0);

        // Then: Should be equal
        $this->assertTrue($rate1->equals($rate2));
    }

    public function testEqualsFalseForDifferentRate(): void
    {
        // Given: Two different tax rates
        $rate1 = TaxRate::fromPercentage(19.0);
        $rate2 = TaxRate::fromPercentage(20.0);

        // Then: Should not be equal
        $this->assertFalse($rate1->equals($rate2));
    }

    public function testEqualsHandlesFloatingPointPrecision(): void
    {
        // Given: Tax rates with very small difference (within tolerance)
        $rate1 = TaxRate::fromPercentage(19.00001);
        $rate2 = TaxRate::fromPercentage(19.00009);

        // Then: Should be considered equal (within 0.0001 tolerance)
        $this->assertTrue($rate1->equals($rate2));
    }

    public function testToStringFormatsCorrectly(): void
    {
        // Given: Various tax rates
        $rate19 = TaxRate::fromPercentage(19.0);
        $rate20_5 = TaxRate::fromPercentage(20.5);
        $rate0 = TaxRate::zero();

        // Then: Should format as percentage with 2 decimal places
        $this->assertSame('19.00%', (string) $rate19);
        $this->assertSame('20.50%', (string) $rate20_5);
        $this->assertSame('0.00%', (string) $rate0);
    }

    public function testRealWorldEUVATRates(): void
    {
        // Test common EU VAT rates
        $germany = TaxRate::fromPercentage(19.0);    // Germany (MwSt)
        $france = TaxRate::fromPercentage(20.0);      // France (TVA)
        $hungary = TaxRate::fromPercentage(27.0);     // Hungary (ÁFA) - highest in EU
        $luxembourg = TaxRate::fromPercentage(17.0);  // Luxembourg - lowest in EU

        // Verify they all work correctly
        $this->assertSame(1900, $germany->calculateTaxAmount(10000));
        $this->assertSame(2000, $france->calculateTaxAmount(10000));
        $this->assertSame(2700, $hungary->calculateTaxAmount(10000));
        $this->assertSame(1700, $luxembourg->calculateTaxAmount(10000));
    }

    public function testReducedVATRates(): void
    {
        // Test common reduced VAT rates
        $reducedDE = TaxRate::fromPercentage(7.0);    // Germany reduced rate
        $reducedFR = TaxRate::fromPercentage(5.5);    // France reduced rate
        $superReduced = TaxRate::fromPercentage(2.5); // Super reduced rate

        // Verify calculations
        $this->assertSame(700, $reducedDE->calculateTaxAmount(10000));
        $this->assertSame(550, $reducedFR->calculateTaxAmount(10000));
        $this->assertSame(250, $superReduced->calculateTaxAmount(10000));
    }

    public function testPrecisionForSmallAmounts(): void
    {
        // Given: 19% tax rate
        $rate = TaxRate::fromPercentage(19.0);

        // When: Calculate tax for very small amounts
        $tax1Cent = $rate->calculateTaxAmount(1);    // 1 cent
        $tax10Cents = $rate->calculateTaxAmount(10); // 10 cents

        // Then: Should handle small amounts correctly
        // 1 * 0.19 = 0.19 → rounds to 0
        $this->assertSame(0, $tax1Cent);

        // 10 * 0.19 = 1.9 → rounds to 2
        $this->assertSame(2, $tax10Cents);
    }

    public function testCalculationConsistency(): void
    {
        // Given: 20% tax rate
        $rate = TaxRate::fromPercentage(20.0);
        $priceInCents = 12345; // €123.45

        // When: Calculate tax and price with tax
        $taxAmount = $rate->calculateTaxAmount($priceInCents);
        $priceWithTax = $rate->calculatePriceWithTax($priceInCents);

        // Then: Price with tax should equal price + tax
        $this->assertSame($priceInCents + $taxAmount, $priceWithTax);
    }
}
