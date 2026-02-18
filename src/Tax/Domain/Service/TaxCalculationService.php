<?php

declare(strict_types=1);

namespace App\Tax\Domain\Service;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tax\Domain\Repository\TaxRuleRepositoryInterface;
use App\Tax\Domain\ValueObject\TaxJurisdiction;

/**
 * Tax Calculation Service.
 *
 * Domain service for calculating taxes based on jurisdiction.
 * Implements destination-based tax calculation (based on shipping address).
 *
 * Business Rules:
 * - Tax is calculated based on destination (shipping address)
 * - If no tax rule found for jurisdiction, no tax is applied (0%)
 * - Tax amount is rounded to nearest cent
 * - Returns detailed breakdown for transparency
 */
final readonly class TaxCalculationService
{
    public function __construct(
        private TaxRuleRepositoryInterface $taxRuleRepository,
    ) {
    }

    /**
     * Calculate tax for given jurisdiction and amount.
     *
     * @return array{
     *     taxAmount: int,
     *     taxRate: float,
     *     jurisdiction: string,
     *     taxRuleId: string|null,
     *     taxRuleName: string|null
     * }
     */
    public function calculateTax(
        int $amountInCents,
        TaxJurisdiction $jurisdiction,
        TenantId $tenantId,
    ): array {
        // Find applicable tax rule
        $taxRule = $this->taxRuleRepository->findByJurisdiction($jurisdiction, $tenantId);

        // No tax rule = no tax
        if (null === $taxRule) {
            return [
                'taxAmount' => 0,
                'taxRate' => 0.0,
                'jurisdiction' => $jurisdiction->toString(),
                'taxRuleId' => null,
                'taxRuleName' => null,
            ];
        }

        // Calculate tax amount
        $taxAmount = $taxRule->calculateTax($amountInCents);

        return [
            'taxAmount' => $taxAmount,
            'taxRate' => $taxRule->rate()->getPercentage(),
            'jurisdiction' => $jurisdiction->toString(),
            'taxRuleId' => $taxRule->id()->toString(),
            'taxRuleName' => $taxRule->name(),
        ];
    }

    /**
     * Calculate tax for multiple line items.
     *
     * @param array<array{amountInCents: int, quantity: int}> $lineItems
     *
     * @return array{
     *     subtotal: int,
     *     taxAmount: int,
     *     total: int,
     *     taxRate: float,
     *     jurisdiction: string,
     *     taxRuleId: string|null,
     *     taxRuleName: string|null
     * }
     */
    public function calculateOrderTax(
        array $lineItems,
        TaxJurisdiction $jurisdiction,
        TenantId $tenantId,
    ): array {
        // Calculate subtotal
        $subtotal = 0;
        foreach ($lineItems as $item) {
            $subtotal += $item['amountInCents'] * $item['quantity'];
        }

        // Calculate tax on subtotal
        $taxCalculation = $this->calculateTax($subtotal, $jurisdiction, $tenantId);

        return [
            'subtotal' => $subtotal,
            'taxAmount' => $taxCalculation['taxAmount'],
            'total' => $subtotal + $taxCalculation['taxAmount'],
            'taxRate' => $taxCalculation['taxRate'],
            'jurisdiction' => $taxCalculation['jurisdiction'],
            'taxRuleId' => $taxCalculation['taxRuleId'],
            'taxRuleName' => $taxCalculation['taxRuleName'],
        ];
    }

    /**
     * Check if taxation is required for jurisdiction.
     */
    public function isTaxable(TaxJurisdiction $jurisdiction, TenantId $tenantId): bool
    {
        $taxRule = $this->taxRuleRepository->findByJurisdiction($jurisdiction, $tenantId);

        return null !== $taxRule && $taxRule->isActive();
    }

    /**
     * Get tax rate for jurisdiction (returns 0 if no rule).
     */
    public function getTaxRate(TaxJurisdiction $jurisdiction, TenantId $tenantId): float
    {
        $taxRule = $this->taxRuleRepository->findByJurisdiction($jurisdiction, $tenantId);

        if (null === $taxRule || !$taxRule->isActive()) {
            return 0.0;
        }

        return $taxRule->rate()->getPercentage();
    }
}
