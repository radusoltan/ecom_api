<?php

declare(strict_types=1);

namespace App\Tests\Integration\Tax\Application\Service;

use App\Tax\Domain\Model\TaxCategory;
use App\Tax\Domain\Model\TaxJurisdiction as ModelTaxJurisdiction;
use App\Tax\Domain\Model\TaxRate;
use App\Tax\Domain\Model\TaxRule;
use App\Tax\Domain\Model\TaxRuleId;
use App\Tax\Domain\Repository\TaxRuleRepositoryInterface;
use App\Tax\Domain\Service\TaxCalculationService;
use App\Tax\Domain\ValueObject\TaxJurisdiction as VOTaxJurisdiction;
use App\Tests\Support\TenantTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for Tax Calculation Service.
 *
 * Tests with real database to verify tax rule persistence and calculation.
 * Ensures multi-tenancy isolation via PostgreSQL RLS.
 */
final class TaxCalculationServiceIntegrationTest extends KernelTestCase
{
    use TenantTestTrait;

    private TaxRuleRepositoryInterface $taxRuleRepository;
    private TaxCalculationService $taxCalculationService;

    protected function setUp(): void
    {
        parent::setUp();

        // Use default test tenant
        $this->tenantId = $this->getDefaultTenantId();

        // Set RLS context for multi-tenancy
        $this->setTenantContext($this->tenantId->toString());

        // Clean up any existing test data
        $this->cleanupTestData();

        $container = self::getContainer();
        $this->taxRuleRepository = $container->get(TaxRuleRepositoryInterface::class);
        $this->taxCalculationService = $container->get(TaxCalculationService::class);
    }

    protected function tearDown(): void
    {
        $this->cleanupTestData();
        parent::tearDown();
    }

    private function cleanupTestData(): void
    {
        $entityManager = self::getContainer()->get('doctrine')->getManager();

        // Clean up tax rules for test tenant
        $entityManager->createQuery(
            'DELETE FROM App\Tax\Infrastructure\Persistence\Doctrine\Entity\TaxRuleEntity e WHERE e.tenantId = :tenantId'
        )->setParameter('tenantId', $this->tenantId->toString())->execute();

        $entityManager->flush();
        $entityManager->clear();
    }

    public function testCalculateTaxWithPersistedTaxRule(): void
    {
        // Given: Persisted Germany VAT rule
        $modelJurisdiction = ModelTaxJurisdiction::fromCountryCode('DE');
        $taxRule = TaxRule::create(
            id: TaxRuleId::generate(),
            tenantId: $this->tenantId,
            jurisdiction: $modelJurisdiction,
            category: TaxCategory::STANDARD,
            rate: TaxRate::fromPercentage(19.0),
            name: 'MwSt (Germany)',
        );

        $this->taxRuleRepository->save($taxRule);

        // When: Calculate tax using the service
        $voJurisdiction = VOTaxJurisdiction::fromCountry('DE');
        $result = $this->taxCalculationService->calculateTax(
            amountInCents: 10000,  // €100.00
            jurisdiction: $voJurisdiction,
            tenantId: $this->tenantId
        );

        // Then: Should retrieve rule from database and calculate correctly
        $this->assertSame(1900, $result['taxAmount']);  // €19.00
        $this->assertSame(19.0, $result['taxRate']);
        $this->assertSame('DE', $result['jurisdiction']);
        $this->assertSame('MwSt (Germany)', $result['taxRuleName']);
        $this->assertNotNull($result['taxRuleId']);
    }

    public function testCalculateTaxRespectsMultiTenancy(): void
    {
        // Given: Tax rule for different jurisdiction per tenant
        $modelJurisdictionDE = ModelTaxJurisdiction::fromCountryCode('DE');
        $taxRuleDE = TaxRule::create(
            id: TaxRuleId::generate(),
            tenantId: $this->tenantId,
            jurisdiction: $modelJurisdictionDE,
            category: TaxCategory::STANDARD,
            rate: TaxRate::fromPercentage(19.0),
            name: 'MwSt (Germany)',
        );
        $this->taxRuleRepository->save($taxRuleDE);

        // When: Calculate tax for this tenant's jurisdiction
        $voJurisdictionDE = VOTaxJurisdiction::fromCountry('DE');
        $resultDE = $this->taxCalculationService->calculateTax(
            amountInCents: 10000,
            jurisdiction: $voJurisdictionDE,
            tenantId: $this->tenantId
        );

        // When: Try to calculate for different jurisdiction (no rule)
        $voJurisdictionFR = VOTaxJurisdiction::fromCountry('FR');
        $resultFR = $this->taxCalculationService->calculateTax(
            amountInCents: 10000,
            jurisdiction: $voJurisdictionFR,
            tenantId: $this->tenantId
        );

        // Then: Should find rule for DE, but not for FR
        $this->assertSame(1900, $resultDE['taxAmount']);
        $this->assertSame(0, $resultFR['taxAmount']);
        $this->assertNull($resultFR['taxRuleId']);
    }

    public function testCalculateOrderTaxWithPersistedRule(): void
    {
        // Given: Persisted France VAT rule
        $modelJurisdiction = ModelTaxJurisdiction::fromCountryCode('FR');
        $taxRule = TaxRule::create(
            id: TaxRuleId::generate(),
            tenantId: $this->tenantId,
            jurisdiction: $modelJurisdiction,
            category: TaxCategory::STANDARD,
            rate: TaxRate::fromPercentage(20.0),
            name: 'TVA (France)',
        );

        $this->taxRuleRepository->save($taxRule);

        // When: Calculate order tax with multiple line items
        $lineItems = [
            ['amountInCents' => 5000, 'quantity' => 2],  // €50.00 × 2 = €100.00
            ['amountInCents' => 3000, 'quantity' => 1],  // €30.00 × 1 = €30.00
        ];

        $voJurisdiction = VOTaxJurisdiction::fromCountry('FR');
        $result = $this->taxCalculationService->calculateOrderTax(
            lineItems: $lineItems,
            jurisdiction: $voJurisdiction,
            tenantId: $this->tenantId
        );

        // Then: Should calculate correctly using persisted rule
        $this->assertSame(13000, $result['subtotal']);  // €130.00
        $this->assertSame(2600, $result['taxAmount']);  // €26.00 (20%)
        $this->assertSame(15600, $result['total']);     // €156.00
        $this->assertSame(20.0, $result['taxRate']);
    }

    public function testIsTaxableWithPersistedActiveRule(): void
    {
        // Given: Persisted active tax rule
        $modelJurisdiction = ModelTaxJurisdiction::fromCountryCode('HU');
        $taxRule = TaxRule::create(
            id: TaxRuleId::generate(),
            tenantId: $this->tenantId,
            jurisdiction: $modelJurisdiction,
            category: TaxCategory::STANDARD,
            rate: TaxRate::fromPercentage(27.0),
            name: 'ÁFA (Hungary)',
        );

        $this->taxRuleRepository->save($taxRule);

        // When/Then: Should be taxable
        $voJurisdiction = VOTaxJurisdiction::fromCountry('HU');
        $this->assertTrue(
            $this->taxCalculationService->isTaxable($voJurisdiction, $this->tenantId)
        );
    }

    public function testIsTaxableWithDeactivatedRule(): void
    {
        // Given: Persisted but deactivated tax rule
        $modelJurisdiction = ModelTaxJurisdiction::fromCountryCode('IT');
        $taxRule = TaxRule::create(
            id: TaxRuleId::generate(),
            tenantId: $this->tenantId,
            jurisdiction: $modelJurisdiction,
            category: TaxCategory::STANDARD,
            rate: TaxRate::fromPercentage(22.0),
            name: 'IVA (Italy)',
        );
        $taxRule->deactivate();

        $this->taxRuleRepository->save($taxRule);

        // When/Then: Should not be taxable (inactive rule)
        $voJurisdiction = VOTaxJurisdiction::fromCountry('IT');
        $this->assertFalse(
            $this->taxCalculationService->isTaxable($voJurisdiction, $this->tenantId)
        );
    }

    public function testGetTaxRateWithPersistedRule(): void
    {
        // Given: Multiple persisted tax rules with different rates
        $modelJurisdictionStandard = ModelTaxJurisdiction::fromCountryCode('DE');
        $taxRuleStandard = TaxRule::create(
            id: TaxRuleId::generate(),
            tenantId: $this->tenantId,
            jurisdiction: $modelJurisdictionStandard,
            category: TaxCategory::STANDARD,
            rate: TaxRate::fromPercentage(19.0),
            name: 'MwSt Standard (Germany)',
        );
        $this->taxRuleRepository->save($taxRuleStandard);

        // Create another rule for same country but will be deactivated
        $modelJurisdictionReduced = ModelTaxJurisdiction::fromCountryCode('LU');
        $taxRuleReduced = TaxRule::create(
            id: TaxRuleId::generate(),
            tenantId: $this->tenantId,
            jurisdiction: $modelJurisdictionReduced,
            category: TaxCategory::STANDARD,
            rate: TaxRate::fromPercentage(17.0),
            name: 'TVA (Luxembourg)',
        );
        $this->taxRuleRepository->save($taxRuleReduced);

        // When: Get tax rates
        $voJurisdictionStandard = VOTaxJurisdiction::fromCountry('DE');
        $voJurisdictionReduced = VOTaxJurisdiction::fromCountry('LU');
        $rateDE = $this->taxCalculationService->getTaxRate($voJurisdictionStandard, $this->tenantId);
        $rateLU = $this->taxCalculationService->getTaxRate($voJurisdictionReduced, $this->tenantId);
        $rateUS = $this->taxCalculationService->getTaxRate(
            VOTaxJurisdiction::fromCountry('US'),
            $this->tenantId
        );

        // Then: Should return correct rates
        $this->assertSame(19.0, $rateDE);
        $this->assertSame(17.0, $rateLU);
        $this->assertSame(0.0, $rateUS);  // No rule for US
    }

    public function testCalculateTaxWithRegionalJurisdiction(): void
    {
        // Given: Persisted US California sales tax rule
        $modelJurisdiction = ModelTaxJurisdiction::fromCountryAndRegion('US', 'CA');
        $taxRule = TaxRule::create(
            id: TaxRuleId::generate(),
            tenantId: $this->tenantId,
            jurisdiction: $modelJurisdiction,
            category: TaxCategory::STANDARD,
            rate: TaxRate::fromPercentage(7.25),
            name: 'California Sales Tax',
        );

        $this->taxRuleRepository->save($taxRule);

        // When: Calculate tax for US-CA jurisdiction
        $voJurisdiction = VOTaxJurisdiction::fromCountryAndRegion('US', 'CA');
        $result = $this->taxCalculationService->calculateTax(
            amountInCents: 10000,  // $100.00
            jurisdiction: $voJurisdiction,
            tenantId: $this->tenantId
        );

        // Then: Should find regional rule and calculate correctly
        $this->assertSame(725, $result['taxAmount']);  // $7.25
        $this->assertSame(7.25, $result['taxRate']);
        $this->assertSame('US-CA', $result['jurisdiction']);
        $this->assertSame('California Sales Tax', $result['taxRuleName']);
    }

    public function testMultipleTaxRulesForDifferentJurisdictions(): void
    {
        // Given: Multiple tax rules for different jurisdictions
        $jurisdictions = [
            'DE' => ['name' => 'MwSt (Germany)', 'rate' => 19.0, 'amount' => 1900],
            'FR' => ['name' => 'TVA (France)', 'rate' => 20.0, 'amount' => 2000],
            'HU' => ['name' => 'ÁFA (Hungary)', 'rate' => 27.0, 'amount' => 2700],
        ];

        foreach ($jurisdictions as $code => $data) {
            $modelJurisdiction = ModelTaxJurisdiction::fromCountryCode($code);
            $taxRule = TaxRule::create(
                id: TaxRuleId::generate(),
                tenantId: $this->tenantId,
                jurisdiction: $modelJurisdiction,
                category: TaxCategory::STANDARD,
                rate: TaxRate::fromPercentage($data['rate']),
                name: $data['name'],
            );
            $this->taxRuleRepository->save($taxRule);
        }

        // When/Then: Calculate tax for each jurisdiction
        foreach ($jurisdictions as $code => $expected) {
            $voJurisdiction = VOTaxJurisdiction::fromCountry($code);
            $result = $this->taxCalculationService->calculateTax(
                amountInCents: 10000,
                jurisdiction: $voJurisdiction,
                tenantId: $this->tenantId
            );

            $this->assertSame($expected['amount'], $result['taxAmount'], "Failed for {$code}");
            $this->assertSame($expected['rate'], $result['taxRate'], "Failed for {$code}");
            $this->assertSame($expected['name'], $result['taxRuleName'], "Failed for {$code}");
        }
    }
}
