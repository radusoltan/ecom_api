<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tax\Infrastructure\Calculator;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tax\Domain\Model\TaxCategory as TaxCategoryModel;
use App\Tax\Domain\Model\TaxJurisdiction as TaxJurisdictionModel;
use App\Tax\Domain\Model\TaxRate as TaxRateModel;
use App\Tax\Domain\Model\TaxRule;
use App\Tax\Domain\Model\TaxRuleId;
use App\Tax\Domain\Repository\TaxRuleRepositoryInterface;
use App\Tax\Domain\Service\TaxCalculationResult;
use App\Tax\Domain\ValueObject\TaxCategory;
use App\Tax\Domain\ValueObject\TaxJurisdiction;
use App\Tax\Infrastructure\Calculator\CompositeTaxCalculator;
use App\Tax\Infrastructure\Calculator\EuVatCalculator;
use PHPUnit\Framework\TestCase;

final class CompositeTaxCalculatorTest extends TestCase
{
    private TaxRuleRepositoryInterface $taxRuleRepository;
    private EuVatCalculator $euVatCalculator;
    private CompositeTaxCalculator $calculator;

    protected function setUp(): void
    {
        $this->taxRuleRepository = $this->createMock(TaxRuleRepositoryInterface::class);
        $this->euVatCalculator = new EuVatCalculator();

        $this->calculator = new CompositeTaxCalculator(
            taxRuleRepository: $this->taxRuleRepository,
            euVatCalculator: $this->euVatCalculator,
        );
    }

    public function testSupportsReturnsTrueForAnyJurisdiction(): void
    {
        $euJurisdiction = TaxJurisdiction::fromCountry('DE');
        $usJurisdiction = TaxJurisdiction::fromCountry('US');
        $auJurisdiction = TaxJurisdiction::fromCountry('AU');

        $this->assertTrue($this->calculator->supports($euJurisdiction));
        $this->assertTrue($this->calculator->supports($usJurisdiction));
        $this->assertTrue($this->calculator->supports($auJurisdiction));
    }

    public function testGetNameReturnsCompositeTaxCalculator(): void
    {
        $this->assertSame('composite_tax_calculator', $this->calculator->getName());
    }

    public function testCalculateDelegatesToEuVatCalculatorForEuJurisdiction(): void
    {
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $sellerJurisdiction = TaxJurisdiction::fromCountry('DE');
        $buyerJurisdiction = TaxJurisdiction::fromCountry('DE');
        $category = TaxCategory::standard();

        $this->taxRuleRepository
            ->method('findBestMatchingRule')
            ->willReturn(null);

        $result = $this->calculator->calculate(
            amountInCents: 10000,
            sellerJurisdiction: $sellerJurisdiction,
            buyerJurisdiction: $buyerJurisdiction,
            category: $category,
            isB2B: false,
            buyerVatNumber: null,
            tenantId: $tenantId,
        );

        $this->assertInstanceOf(TaxCalculationResult::class, $result);
        $this->assertSame(10000, $result->netAmountInCents);
        // Germany standard VAT = 19%
        $this->assertSame(1900, $result->taxAmountInCents);
        $this->assertSame(11900, $result->grossAmountInCents);
        $this->assertSame('vat', $result->taxType);
        $this->assertFalse($result->reverseCharge);
    }

    public function testCalculateReturnsExemptResultForUnsupportedJurisdiction(): void
    {
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $sellerJurisdiction = TaxJurisdiction::fromCountry('US');
        $buyerJurisdiction = TaxJurisdiction::fromCountry('US');
        $category = TaxCategory::standard();

        $this->taxRuleRepository
            ->method('findBestMatchingRule')
            ->willReturn(null);

        $result = $this->calculator->calculate(
            amountInCents: 10000,
            sellerJurisdiction: $sellerJurisdiction,
            buyerJurisdiction: $buyerJurisdiction,
            category: $category,
            tenantId: $tenantId,
        );

        $this->assertSame(0, $result->taxAmountInCents);
        $this->assertSame(10000, $result->grossAmountInCents);
        $this->assertSame('exempt', $result->taxType);
        $this->assertNotNull($result->exemptionReason);
        $this->assertStringContainsString('No tax calculator available', $result->exemptionReason);
    }

    public function testCalculateUsesCustomRuleWhenTenantAndRuleExist(): void
    {
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $sellerJurisdiction = TaxJurisdiction::fromCountry('DE');
        $buyerJurisdiction = TaxJurisdiction::fromCountry('DE');
        $category = TaxCategory::standard();

        $customRate = TaxRateModel::fromPercentage(15.0);
        $customJurisdiction = TaxJurisdictionModel::fromCountryCode('DE');
        $customCategory = TaxCategoryModel::STANDARD;

        $customRule = TaxRule::create(
            id: TaxRuleId::generate(),
            tenantId: $tenantId,
            jurisdiction: $customJurisdiction,
            category: $customCategory,
            rate: $customRate,
            name: 'Custom DE Rule',
            priority: 10,
            isReverseCharge: false,
        );

        $this->taxRuleRepository
            ->expects($this->once())
            ->method('findBestMatchingRule')
            ->willReturn($customRule);

        $result = $this->calculator->calculate(
            amountInCents: 10000,
            sellerJurisdiction: $sellerJurisdiction,
            buyerJurisdiction: $buyerJurisdiction,
            category: $category,
            tenantId: $tenantId,
        );

        // Custom rule at 15% overrides default 19%
        $this->assertSame(1500, $result->taxAmountInCents);
        $this->assertSame(11500, $result->grossAmountInCents);
        $this->assertSame('custom_rule', $result->taxType);
        $this->assertArrayHasKey('rule_name', $result->breakdown);
        $this->assertSame('Custom DE Rule', $result->breakdown['rule_name']);
    }

    public function testCalculateAppliesReverseChargeFromCustomRule(): void
    {
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $sellerJurisdiction = TaxJurisdiction::fromCountry('DE');
        $buyerJurisdiction = TaxJurisdiction::fromCountry('FR');
        $category = TaxCategory::standard();

        $customRate = TaxRateModel::fromPercentage(19.0);
        $customJurisdiction = TaxJurisdictionModel::fromCountryCode('FR');
        $customCategory = TaxCategoryModel::STANDARD;

        $customRule = TaxRule::create(
            id: TaxRuleId::generate(),
            tenantId: $tenantId,
            jurisdiction: $customJurisdiction,
            category: $customCategory,
            rate: $customRate,
            name: 'Reverse Charge Rule',
            priority: 10,
            isReverseCharge: true,
        );

        $this->taxRuleRepository
            ->expects($this->once())
            ->method('findBestMatchingRule')
            ->willReturn($customRule);

        $result = $this->calculator->calculate(
            amountInCents: 10000,
            sellerJurisdiction: $sellerJurisdiction,
            buyerJurisdiction: $buyerJurisdiction,
            category: $category,
            isB2B: true,
            buyerVatNumber: 'FR12345678901',
            tenantId: $tenantId,
        );

        $this->assertSame(0, $result->taxAmountInCents);
        $this->assertTrue($result->reverseCharge);
        $this->assertSame('vat_reverse_charge', $result->taxType);
    }

    public function testCalculateWithoutTenantIdSkipsCustomRuleLookup(): void
    {
        $sellerJurisdiction = TaxJurisdiction::fromCountry('DE');
        $buyerJurisdiction = TaxJurisdiction::fromCountry('DE');
        $category = TaxCategory::standard();

        $this->taxRuleRepository
            ->expects($this->never())
            ->method('findBestMatchingRule');

        $result = $this->calculator->calculate(
            amountInCents: 10000,
            sellerJurisdiction: $sellerJurisdiction,
            buyerJurisdiction: $buyerJurisdiction,
            category: $category,
            tenantId: null,
        );

        // Falls through to EU VAT calculator for DE (19%)
        $this->assertSame(1900, $result->taxAmountInCents);
        $this->assertSame('vat', $result->taxType);
    }

    public function testCalculateEuB2bCrossBorderAppliesReverseCharge(): void
    {
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $sellerJurisdiction = TaxJurisdiction::fromCountry('DE');
        $buyerJurisdiction = TaxJurisdiction::fromCountry('FR');
        $category = TaxCategory::standard();

        $this->taxRuleRepository
            ->method('findBestMatchingRule')
            ->willReturn(null);

        $result = $this->calculator->calculate(
            amountInCents: 10000,
            sellerJurisdiction: $sellerJurisdiction,
            buyerJurisdiction: $buyerJurisdiction,
            category: $category,
            isB2B: true,
            buyerVatNumber: 'FR12345678901',
            tenantId: $tenantId,
        );

        $this->assertSame(0, $result->taxAmountInCents);
        $this->assertTrue($result->reverseCharge);
        $this->assertSame('vat_reverse_charge', $result->taxType);
    }

    public function testCalculateExemptCategoryReturnsExemptResult(): void
    {
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $sellerJurisdiction = TaxJurisdiction::fromCountry('DE');
        $buyerJurisdiction = TaxJurisdiction::fromCountry('DE');
        $category = TaxCategory::exempt();

        $this->taxRuleRepository
            ->method('findBestMatchingRule')
            ->willReturn(null);

        $result = $this->calculator->calculate(
            amountInCents: 10000,
            sellerJurisdiction: $sellerJurisdiction,
            buyerJurisdiction: $buyerJurisdiction,
            category: $category,
            tenantId: $tenantId,
        );

        $this->assertSame(0, $result->taxAmountInCents);
        $this->assertTrue($result->isExempt());
        $this->assertSame('exempt', $result->taxType);
    }

    public function testCalculateFallbackToZeroTaxForNonEuWithoutTenant(): void
    {
        $sellerJurisdiction = TaxJurisdiction::fromCountry('AU');
        $buyerJurisdiction = TaxJurisdiction::fromCountry('JP');
        $category = TaxCategory::standard();

        $this->taxRuleRepository->expects($this->never())->method('findBestMatchingRule');

        $result = $this->calculator->calculate(
            amountInCents: 5000,
            sellerJurisdiction: $sellerJurisdiction,
            buyerJurisdiction: $buyerJurisdiction,
            category: $category,
            tenantId: null,
        );

        $this->assertSame(0, $result->taxAmountInCents);
        $this->assertSame(5000, $result->grossAmountInCents);
        $this->assertSame('exempt', $result->taxType);
        $this->assertStringContainsString('JP', $result->exemptionReason);
    }
}
