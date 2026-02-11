<?php

declare(strict_types=1);

namespace App\Tax\Infrastructure\Calculator;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tax\Domain\Model\TaxCategory as TaxCategoryModel;
use App\Tax\Domain\Model\TaxJurisdiction as TaxJurisdictionModel;
use App\Tax\Domain\Repository\TaxRuleRepositoryInterface;
use App\Tax\Domain\Service\TaxCalculationResult;
use App\Tax\Domain\Service\TaxCalculatorInterface;
use App\Tax\Domain\ValueObject\TaxCategory;
use App\Tax\Domain\ValueObject\TaxJurisdiction;
use App\Tax\Domain\ValueObject\TaxRate;

/**
 * Composite Tax Calculator - orchestrates multiple tax calculators.
 *
 * Implements the Composite Pattern + Strategy Pattern to handle multiple tax systems:
 * - EU VAT (EuVatCalculator)
 * - US Sales Tax (future: UsStateTaxCalculator)
 * - Canada GST (future: CanadaGstCalculator)
 * - Custom tenant-specific rules (via TaxRuleRepository)
 *
 * Calculation Priority:
 * 1. Custom tenant-specific rules (highest priority)
 * 2. Default jurisdiction calculators (EU VAT, US Sales Tax, etc.)
 * 3. Zero tax (fallback for uncovered jurisdictions)
 *
 * Design Principles:
 * - Single Responsibility: Delegates to specialized calculators
 * - Open/Closed: New calculators can be added without modifying existing code
 * - Dependency Inversion: Depends on TaxCalculatorInterface abstraction
 * - Multi-tenancy: Tenant-specific rules override defaults
 *
 * Business Rules:
 * - Custom rules take precedence over default calculators
 * - Only active and valid rules are considered
 * - Falls back to zero tax for uncovered jurisdictions
 * - Preserves calculator-specific logic (B2B reverse charge, etc.)
 *
 * @see TaxCalculatorInterface
 * @see EuVatCalculator
 */
final class CompositeTaxCalculator implements TaxCalculatorInterface
{
    /**
     * @var array<TaxCalculatorInterface>
     */
    private array $calculators;

    public function __construct(
        private readonly TaxRuleRepositoryInterface $taxRuleRepository,
        private readonly EuVatCalculator $euVatCalculator,
        // Add more calculators as needed:
        // private readonly UsStateTaxCalculator $usStateTaxCalculator,
        // private readonly CanadaGstCalculator $canadaGstCalculator,
    ) {
        $this->calculators = [
            $this->euVatCalculator,
            // Add future calculators here
        ];
    }

    /**
     * Calculate tax using composite strategy.
     *
     * Priority-based calculation:
     * 1. Check for tenant-specific custom rules (TaxRule repository)
     * 2. Find appropriate default calculator (EU VAT, US Sales Tax, etc.)
     * 3. Fall back to zero tax if no calculator supports the jurisdiction
     *
     * @param int             $amountInCents      Net amount (before tax)
     * @param TaxJurisdiction $sellerJurisdiction Where the seller is established
     * @param TaxJurisdiction $buyerJurisdiction  Where the buyer is located
     * @param TaxCategory     $category           Product tax category
     * @param bool            $isB2B              True if buyer is a business with valid VAT number
     * @param string|null     $buyerVatNumber     Buyer's VAT number for B2B transactions
     * @param TenantId|null   $tenantId           Tenant context for custom rules
     *
     * @return TaxCalculationResult Tax calculation with breakdown
     */
    public function calculate(
        int $amountInCents,
        TaxJurisdiction $sellerJurisdiction,
        TaxJurisdiction $buyerJurisdiction,
        TaxCategory $category,
        bool $isB2B = false,
        ?string $buyerVatNumber = null,
        ?TenantId $tenantId = null
    ): TaxCalculationResult {
        // 1. Check for tenant-specific custom rules first (highest priority)
        if (null !== $tenantId) {
            // Convert ValueObject types to Model types for repository
            $jurisdictionModel = $this->convertToJurisdictionModel($buyerJurisdiction);
            $categoryModel = $this->convertToCategoryModel($category);

            $customRule = $this->taxRuleRepository->findBestMatchingRule(
                tenantId: $tenantId,
                jurisdiction: $jurisdictionModel,
                category: $categoryModel,
                asOfDate: new \DateTimeImmutable()
            );

            if (null !== $customRule) {
                return $this->calculateFromCustomRule($amountInCents, $customRule, $buyerJurisdiction, $category);
            }
        }

        // 2. Find appropriate default calculator
        foreach ($this->calculators as $calculator) {
            if ($calculator->supports($buyerJurisdiction)) {
                return $calculator->calculate(
                    amountInCents: $amountInCents,
                    sellerJurisdiction: $sellerJurisdiction,
                    buyerJurisdiction: $buyerJurisdiction,
                    category: $category,
                    isB2B: $isB2B,
                    buyerVatNumber: $buyerVatNumber
                );
            }
        }

        // 3. Fallback: No tax (jurisdiction not covered)
        return $this->createNoTaxResult($amountInCents, $buyerJurisdiction, $category);
    }

    /**
     * Check if this calculator supports the jurisdiction.
     *
     * Composite calculator supports ALL jurisdictions:
     * - Returns true always (delegates to specialized calculators or custom rules)
     *
     * @param TaxJurisdiction $jurisdiction Jurisdiction to check
     *
     * @return bool Always true (composite supports all)
     */
    public function supports(TaxJurisdiction $jurisdiction): bool
    {
        // Composite supports all jurisdictions
        // It delegates to specialized calculators or returns zero tax
        return true;
    }

    /**
     * Get calculator name.
     *
     * @return string Calculator identifier
     */
    public function getName(): string
    {
        return 'composite_tax_calculator';
    }

    /**
     * Calculate tax from custom tenant rule.
     *
     * Applies the custom rule's rate to the amount.
     * Handles reverse charge if specified in the rule.
     *
     * @param int                           $amountInCents Net amount (before tax)
     * @param \App\Tax\Domain\Model\TaxRule $rule          Custom tax rule
     * @param TaxJurisdiction               $jurisdiction  Buyer's jurisdiction
     * @param TaxCategory                   $category      Tax category
     *
     * @return TaxCalculationResult Calculation result
     */
    private function calculateFromCustomRule(
        int $amountInCents,
        \App\Tax\Domain\Model\TaxRule $rule,
        TaxJurisdiction $jurisdiction,
        TaxCategory $category
    ): TaxCalculationResult {
        // Convert Model TaxRate to ValueObject TaxRate
        $rateModel = $rule->rate();
        $rate = TaxRate::fromPercentage($rateModel->percentage());

        // Check if reverse charge applies (custom rule setting)
        if ($rule->isReverseCharge()) {
            return TaxCalculationResult::reverseCharge(
                amountInCents: $amountInCents,
                theoreticalRate: $rate,
                jurisdiction: $jurisdiction,
                category: $category
            );
        }

        // Standard calculation with custom rate
        $taxAmount = $rate->calculateTaxAmount($amountInCents);

        return TaxCalculationResult::create(
            netAmountInCents: $amountInCents,
            taxAmountInCents: $taxAmount,
            appliedRate: $rate,
            taxJurisdiction: $jurisdiction,
            category: $category,
            taxType: 'custom_rule',
            reverseCharge: false,
            exemptionReason: null,
            breakdown: [
                'rule_id' => $rule->id()->toString(),
                'rule_name' => $rule->name(),
                'rule_priority' => $rule->priority(),
            ]
        );
    }

    /**
     * Create zero tax result for unsupported jurisdictions.
     *
     * Used as fallback when no calculator supports the jurisdiction
     * and no custom rules exist.
     *
     * @param int             $amountInCents Net amount
     * @param TaxJurisdiction $jurisdiction  Buyer's jurisdiction
     * @param TaxCategory     $category      Tax category
     *
     * @return TaxCalculationResult Zero tax result
     */
    private function createNoTaxResult(
        int $amountInCents,
        TaxJurisdiction $jurisdiction,
        TaxCategory $category
    ): TaxCalculationResult {
        return TaxCalculationResult::exempt(
            amountInCents: $amountInCents,
            jurisdiction: $jurisdiction,
            category: $category,
            reason: sprintf(
                'No tax calculator available for jurisdiction: %s (%s)',
                $jurisdiction->getCountryCode(),
                $jurisdiction->toString()
            )
        );
    }

    /**
     * Convert ValueObject TaxJurisdiction to Model TaxJurisdiction.
     *
     * Both share the same interface, just need to create a new instance
     * from the country code.
     */
    private function convertToJurisdictionModel(TaxJurisdiction $jurisdiction): TaxJurisdictionModel
    {
        return TaxJurisdictionModel::fromCountryCode($jurisdiction->getCountryCode());
    }

    /**
     * Convert ValueObject TaxCategory to Model TaxCategory.
     *
     * Model version is an enum, ValueObject uses constants.
     * Map between the two based on string value.
     */
    private function convertToCategoryModel(TaxCategory $category): TaxCategoryModel
    {
        return match ($category->value()) {
            TaxCategory::STANDARD => TaxCategoryModel::STANDARD,
            TaxCategory::REDUCED => TaxCategoryModel::REDUCED,
            TaxCategory::ZERO_RATED => TaxCategoryModel::ZERO,
            TaxCategory::EXEMPT => TaxCategoryModel::EXEMPT,
            default => TaxCategoryModel::STANDARD,
        };
    }
}
