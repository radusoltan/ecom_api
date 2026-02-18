<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tax\Domain\Service;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tax\Domain\Model\TaxCategory;
use App\Tax\Domain\Model\TaxJurisdiction;
use App\Tax\Domain\Model\TaxRule;
use App\Tax\Domain\Model\TaxRuleId;
use App\Tax\Domain\Model\TaxRate;
use App\Tax\Domain\Repository\TaxRuleRepositoryInterface;
use App\Tax\Domain\Service\TaxCalculationService;
use App\Tax\Domain\ValueObject\TaxJurisdiction as TaxJurisdictionVO;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Tax Calculation Service.
 *
 * Verifies EU VAT calculation logic works correctly for all scenarios.
 */
final class TaxCalculationServiceTest extends TestCase
{
    private TaxRuleRepositoryInterface $taxRuleRepository;
    private TaxCalculationService $taxCalculationService;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->taxRuleRepository = $this->createMock(TaxRuleRepositoryInterface::class);
        $this->taxCalculationService = new TaxCalculationService($this->taxRuleRepository);
        $this->tenantId = TenantId::generate();
    }

    private function createTaxRule(
        TaxJurisdictionVO $jurisdiction,
        float $rate,
        string $name,
    ): TaxRule {
        return TaxRule::create(
            id: TaxRuleId::generate(),
            tenantId: $this->tenantId,
            jurisdiction: TaxJurisdiction::fromCountryCode($jurisdiction->getCountryCode()),
            category: TaxCategory::STANDARD,
            rate: TaxRate::fromPercentage($rate),
            name: $name,
        );
    }

    public function testCalculateTaxForGermany(): void
    {
        // Given: Germany VAT rule (19%)
        $jurisdiction = TaxJurisdictionVO::fromCountry('DE');
        $taxRule = $this->createTaxRule($jurisdiction, 19.0, 'MwSt (Germany)');

        $this->taxRuleRepository
            ->method('findByJurisdiction')
            ->with($this->anything(), $this->tenantId)
            ->willReturn($taxRule);

        // When: Calculate tax for €100.00
        $result = $this->taxCalculationService->calculateTax(
            amountInCents: 10000,  // €100.00
            jurisdiction: $jurisdiction,
            tenantId: $this->tenantId
        );

        // Then: Should return €19.00 tax
        $this->assertSame(1900, $result['taxAmount']);  // €19.00
        $this->assertSame(19.0, $result['taxRate']);
        $this->assertSame('DE', $result['jurisdiction']);
        $this->assertSame('MwSt (Germany)', $result['taxRuleName']);
        $this->assertNotNull($result['taxRuleId']);
    }

    public function testCalculateTaxForFrance(): void
    {
        // Given: France VAT rule (20%)
        $jurisdiction = TaxJurisdictionVO::fromCountry('FR');
        $taxRule = $this->createTaxRule($jurisdiction, 20.0, 'TVA (France)');

        $this->taxRuleRepository
            ->method('findByJurisdiction')
            ->willReturn($taxRule);

        // When: Calculate tax for €50.00
        $result = $this->taxCalculationService->calculateTax(
            amountInCents: 5000,  // €50.00
            jurisdiction: $jurisdiction,
            tenantId: $this->tenantId
        );

        // Then: Should return €10.00 tax
        $this->assertSame(1000, $result['taxAmount']);  // €10.00
        $this->assertSame(20.0, $result['taxRate']);
    }

    public function testCalculateTaxForHungary(): void
    {
        // Given: Hungary VAT rule (27% - highest in EU)
        $jurisdiction = TaxJurisdictionVO::fromCountry('HU');
        $taxRule = $this->createTaxRule($jurisdiction, 27.0, 'ÁFA (Hungary)');

        $this->taxRuleRepository
            ->method('findByJurisdiction')
            ->willReturn($taxRule);

        // When: Calculate tax for €100.00
        $result = $this->taxCalculationService->calculateTax(
            amountInCents: 10000,
            jurisdiction: $jurisdiction,
            tenantId: $this->tenantId
        );

        // Then: Should return €27.00 tax
        $this->assertSame(2700, $result['taxAmount']);
        $this->assertSame(27.0, $result['taxRate']);
    }

    public function testCalculateTaxForLuxembourg(): void
    {
        // Given: Luxembourg VAT rule (17% - lowest in EU)
        $jurisdiction = TaxJurisdictionVO::fromCountry('LU');
        $taxRule = $this->createTaxRule($jurisdiction, 17.0, 'TVA (Luxembourg)');

        $this->taxRuleRepository
            ->method('findByJurisdiction')
            ->willReturn($taxRule);

        // When: Calculate tax for €100.00
        $result = $this->taxCalculationService->calculateTax(
            amountInCents: 10000,
            jurisdiction: $jurisdiction,
            tenantId: $this->tenantId
        );

        // Then: Should return €17.00 tax
        $this->assertSame(1700, $result['taxAmount']);
        $this->assertSame(17.0, $result['taxRate']);
    }

    public function testCalculateTaxWithNoTaxRule(): void
    {
        // Given: No tax rule for jurisdiction
        $jurisdiction = TaxJurisdictionVO::fromCountry('US');

        $this->taxRuleRepository
            ->method('findByJurisdiction')
            ->willReturn(null);

        // When: Calculate tax for $100.00
        $result = $this->taxCalculationService->calculateTax(
            amountInCents: 10000,
            jurisdiction: $jurisdiction,
            tenantId: $this->tenantId
        );

        // Then: Should return no tax
        $this->assertSame(0, $result['taxAmount']);
        $this->assertSame(0.0, $result['taxRate']);
        $this->assertSame('US', $result['jurisdiction']);
        $this->assertNull($result['taxRuleId']);
        $this->assertNull($result['taxRuleName']);
    }

    public function testCalculateOrderTaxWithMultipleLineItems(): void
    {
        // Given: France VAT rule (20%)
        $jurisdiction = TaxJurisdictionVO::fromCountry('FR');
        $taxRule = $this->createTaxRule($jurisdiction, 20.0, 'TVA (France)');

        $this->taxRuleRepository
            ->method('findByJurisdiction')
            ->willReturn($taxRule);

        // When: Calculate tax for order with multiple items
        $lineItems = [
            ['amountInCents' => 5000, 'quantity' => 2],  // €50.00 × 2 = €100.00
            ['amountInCents' => 3000, 'quantity' => 1],  // €30.00 × 1 = €30.00
        ];

        $result = $this->taxCalculationService->calculateOrderTax(
            lineItems: $lineItems,
            jurisdiction: $jurisdiction,
            tenantId: $this->tenantId
        );

        // Then: Should calculate tax on subtotal of €130.00
        $this->assertSame(13000, $result['subtotal']);  // €130.00
        $this->assertSame(2600, $result['taxAmount']);   // €26.00 (20% of €130.00)
        $this->assertSame(15600, $result['total']);      // €156.00
        $this->assertSame(20.0, $result['taxRate']);
    }

    public function testIsTaxableForJurisdictionWithTaxRule(): void
    {
        // Given: Active tax rule exists
        $jurisdiction = TaxJurisdictionVO::fromCountry('DE');
        $taxRule = $this->createTaxRule($jurisdiction, 19.0, 'MwSt (Germany)');

        $this->taxRuleRepository
            ->method('findByJurisdiction')
            ->willReturn($taxRule);

        // When/Then: Should be taxable
        $this->assertTrue(
            $this->taxCalculationService->isTaxable($jurisdiction, $this->tenantId)
        );
    }

    public function testIsTaxableForJurisdictionWithoutTaxRule(): void
    {
        // Given: No tax rule exists
        $jurisdiction = TaxJurisdictionVO::fromCountry('US');

        $this->taxRuleRepository
            ->method('findByJurisdiction')
            ->willReturn(null);

        // When/Then: Should not be taxable
        $this->assertFalse(
            $this->taxCalculationService->isTaxable($jurisdiction, $this->tenantId)
        );
    }

    public function testIsTaxableForInactiveRule(): void
    {
        // Given: Inactive tax rule exists
        $jurisdiction = TaxJurisdictionVO::fromCountry('DE');
        $taxRule = $this->createTaxRule($jurisdiction, 19.0, 'MwSt (Germany)');
        $taxRule->deactivate();

        $this->taxRuleRepository
            ->method('findByJurisdiction')
            ->willReturn($taxRule);

        // When/Then: Should not be taxable
        $this->assertFalse(
            $this->taxCalculationService->isTaxable($jurisdiction, $this->tenantId)
        );
    }

    public function testGetTaxRateReturnsCorrectRate(): void
    {
        // Given: Germany VAT rule (19%)
        $jurisdiction = TaxJurisdictionVO::fromCountry('DE');
        $taxRule = $this->createTaxRule($jurisdiction, 19.0, 'MwSt (Germany)');

        $this->taxRuleRepository
            ->method('findByJurisdiction')
            ->willReturn($taxRule);

        // When/Then: Should return 19.0%
        $rate = $this->taxCalculationService->getTaxRate($jurisdiction, $this->tenantId);
        $this->assertSame(19.0, $rate);
    }

    public function testGetTaxRateReturnsZeroWhenNoRule(): void
    {
        // Given: No tax rule
        $jurisdiction = TaxJurisdictionVO::fromCountry('US');

        $this->taxRuleRepository
            ->method('findByJurisdiction')
            ->willReturn(null);

        // When/Then: Should return 0.0%
        $rate = $this->taxCalculationService->getTaxRate($jurisdiction, $this->tenantId);
        $this->assertSame(0.0, $rate);
    }

    public function testRoundingForOddAmounts(): void
    {
        // Given: Germany VAT rule (19%)
        $jurisdiction = TaxJurisdictionVO::fromCountry('DE');
        $taxRule = $this->createTaxRule($jurisdiction, 19.0, 'MwSt (Germany)');

        $this->taxRuleRepository
            ->method('findByJurisdiction')
            ->willReturn($taxRule);

        // When: Calculate tax for €99.99 (9999 cents)
        $result = $this->taxCalculationService->calculateTax(
            amountInCents: 9999,
            jurisdiction: $jurisdiction,
            tenantId: $this->tenantId
        );

        // Then: 9999 * 0.19 = 1899.81 → should round to 1900 cents (€19.00)
        $this->assertSame(1900, $result['taxAmount']);
    }

    public function testCalculateTaxForSmallAmount(): void
    {
        // Given: Germany VAT rule (19%)
        $jurisdiction = TaxJurisdictionVO::fromCountry('DE');
        $taxRule = $this->createTaxRule($jurisdiction, 19.0, 'MwSt (Germany)');

        $this->taxRuleRepository
            ->method('findByJurisdiction')
            ->willReturn($taxRule);

        // When: Calculate tax for €1.00 (100 cents)
        $result = $this->taxCalculationService->calculateTax(
            amountInCents: 100,
            jurisdiction: $jurisdiction,
            tenantId: $this->tenantId
        );

        // Then: Should calculate €0.19 (19 cents)
        $this->assertSame(19, $result['taxAmount']);
    }

    public function testCalculateTaxForReducedVATRate(): void
    {
        // Given: Germany reduced VAT rule (7% for books, food)
        $jurisdiction = TaxJurisdictionVO::fromCountry('DE');
        $taxRule = $this->createTaxRule($jurisdiction, 7.0, 'MwSt ermäßigt (Germany)');

        $this->taxRuleRepository
            ->method('findByJurisdiction')
            ->willReturn($taxRule);

        // When: Calculate tax for €50.00
        $result = $this->taxCalculationService->calculateTax(
            amountInCents: 5000,
            jurisdiction: $jurisdiction,
            tenantId: $this->tenantId
        );

        // Then: Should calculate €3.50 (350 cents)
        $this->assertSame(350, $result['taxAmount']);
        $this->assertSame(7.0, $result['taxRate']);
    }

    public function testCalculateTaxForRegionalJurisdiction(): void
    {
        // Given: US state tax rule (CA 7.25%)
        $jurisdiction = TaxJurisdictionVO::fromCountryAndRegion('US', 'CA');
        $taxRule = TaxRule::create(
            id: TaxRuleId::generate(),
            tenantId: $this->tenantId,
            jurisdiction: TaxJurisdiction::fromCountryAndRegion('US', 'CA'),
            category: TaxCategory::STANDARD,
            rate: TaxRate::fromPercentage(7.25),
            name: 'California Sales Tax',
        );

        $this->taxRuleRepository
            ->method('findByJurisdiction')
            ->willReturn($taxRule);

        // When: Calculate tax for $100.00
        $result = $this->taxCalculationService->calculateTax(
            amountInCents: 10000,
            jurisdiction: $jurisdiction,
            tenantId: $this->tenantId
        );

        // Then: Should calculate $7.25 (725 cents)
        $this->assertSame(725, $result['taxAmount']);
        $this->assertSame(7.25, $result['taxRate']);
        $this->assertSame('US-CA', $result['jurisdiction']);
    }

    public function testCalculateOrderTaxWithEmptyLineItems(): void
    {
        // Given: France VAT rule (20%)
        $jurisdiction = TaxJurisdictionVO::fromCountry('FR');
        $taxRule = $this->createTaxRule($jurisdiction, 20.0, 'TVA (France)');

        $this->taxRuleRepository
            ->method('findByJurisdiction')
            ->willReturn($taxRule);

        // When: Calculate tax with no line items
        $result = $this->taxCalculationService->calculateOrderTax(
            lineItems: [],
            jurisdiction: $jurisdiction,
            tenantId: $this->tenantId
        );

        // Then: Should return zero amounts
        $this->assertSame(0, $result['subtotal']);
        $this->assertSame(0, $result['taxAmount']);
        $this->assertSame(0, $result['total']);
    }

    public function testCalculateOrderTaxWithSingleLineItem(): void
    {
        // Given: Germany VAT rule (19%)
        $jurisdiction = TaxJurisdictionVO::fromCountry('DE');
        $taxRule = $this->createTaxRule($jurisdiction, 19.0, 'MwSt (Germany)');

        $this->taxRuleRepository
            ->method('findByJurisdiction')
            ->willReturn($taxRule);

        // When: Calculate tax for single item
        $lineItems = [
            ['amountInCents' => 10000, 'quantity' => 1],  // €100.00 × 1
        ];

        $result = $this->taxCalculationService->calculateOrderTax(
            lineItems: $lineItems,
            jurisdiction: $jurisdiction,
            tenantId: $this->tenantId
        );

        // Then: Should calculate correctly
        $this->assertSame(10000, $result['subtotal']);   // €100.00
        $this->assertSame(1900, $result['taxAmount']);   // €19.00
        $this->assertSame(11900, $result['total']);      // €119.00
    }

    public function testCalculateOrderTaxWithLargeQuantities(): void
    {
        // Given: France VAT rule (20%)
        $jurisdiction = TaxJurisdictionVO::fromCountry('FR');
        $taxRule = $this->createTaxRule($jurisdiction, 20.0, 'TVA (France)');

        $this->taxRuleRepository
            ->method('findByJurisdiction')
            ->willReturn($taxRule);

        // When: Calculate tax for bulk order
        $lineItems = [
            ['amountInCents' => 1000, 'quantity' => 100],  // €10.00 × 100 = €1000.00
        ];

        $result = $this->taxCalculationService->calculateOrderTax(
            lineItems: $lineItems,
            jurisdiction: $jurisdiction,
            tenantId: $this->tenantId
        );

        // Then: Should calculate correctly
        $this->assertSame(100000, $result['subtotal']);  // €1000.00
        $this->assertSame(20000, $result['taxAmount']);  // €200.00 (20%)
        $this->assertSame(120000, $result['total']);     // €1200.00
    }

    public function testCalculateTaxForZeroPercentRate(): void
    {
        // Given: Tax-exempt jurisdiction (0% rate)
        $jurisdiction = TaxJurisdictionVO::fromCountry('AE');  // UAE (no VAT on certain goods)
        $taxRule = TaxRule::create(
            id: TaxRuleId::generate(),
            tenantId: $this->tenantId,
            jurisdiction: TaxJurisdiction::fromCountryCode('AE'),
            category: TaxCategory::ZERO,
            rate: TaxRate::fromPercentage(0.0),
            name: 'Zero-rated (UAE)',
        );

        $this->taxRuleRepository
            ->method('findByJurisdiction')
            ->willReturn($taxRule);

        // When: Calculate tax for €100.00
        $result = $this->taxCalculationService->calculateTax(
            amountInCents: 10000,
            jurisdiction: $jurisdiction,
            tenantId: $this->tenantId
        );

        // Then: Should return no tax
        $this->assertSame(0, $result['taxAmount']);
        $this->assertSame(0.0, $result['taxRate']);
    }

    public function testGetTaxRateForInactiveRule(): void
    {
        // Given: Inactive tax rule
        $jurisdiction = TaxJurisdictionVO::fromCountry('IT');
        $taxRule = $this->createTaxRule($jurisdiction, 22.0, 'IVA (Italy)');
        $taxRule->deactivate();

        $this->taxRuleRepository
            ->method('findByJurisdiction')
            ->willReturn($taxRule);

        // When/Then: Should return 0.0% for inactive rule
        $rate = $this->taxCalculationService->getTaxRate($jurisdiction, $this->tenantId);
        $this->assertSame(0.0, $rate);
    }

    public function testCalculateOrderTaxWithMixedPriceRanges(): void
    {
        // Given: Hungary VAT rule (27% - highest in EU)
        $jurisdiction = TaxJurisdictionVO::fromCountry('HU');
        $taxRule = $this->createTaxRule($jurisdiction, 27.0, 'ÁFA (Hungary)');

        $this->taxRuleRepository
            ->method('findByJurisdiction')
            ->willReturn($taxRule);

        // When: Calculate tax for order with items at different price points
        $lineItems = [
            ['amountInCents' => 99, 'quantity' => 1],     // €0.99 × 1
            ['amountInCents' => 1000, 'quantity' => 2],   // €10.00 × 2
            ['amountInCents' => 50000, 'quantity' => 1],  // €500.00 × 1
        ];

        $result = $this->taxCalculationService->calculateOrderTax(
            lineItems: $lineItems,
            jurisdiction: $jurisdiction,
            tenantId: $this->tenantId
        );

        // Then: Subtotal = €0.99 + €20.00 + €500.00 = €520.99 (52099 cents)
        $this->assertSame(52099, $result['subtotal']);
        // Tax = 52099 * 0.27 = 14066.73 → 14067 cents (€140.67)
        $this->assertSame(14067, $result['taxAmount']);
        // Total = 52099 + 14067 = 66166 cents (€661.66)
        $this->assertSame(66166, $result['total']);
    }
}
