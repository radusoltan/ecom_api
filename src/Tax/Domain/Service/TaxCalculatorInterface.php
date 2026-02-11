<?php

declare(strict_types=1);

namespace App\Tax\Domain\Service;

use App\Tax\Domain\ValueObject\TaxCategory;
use App\Tax\Domain\ValueObject\TaxJurisdiction;

/**
 * Tax Calculator Port - defines contract for tax calculation adapters.
 *
 * This interface follows the Hexagonal Architecture pattern (Port/Adapter):
 * - Port: This interface (in Domain layer)
 * - Adapters: EuVatCalculator, UsStateTaxCalculator (in Infrastructure layer)
 *
 * Design Principles:
 * - NO framework dependencies
 * - Immutable result DTOs (TaxCalculationResult)
 * - Rich domain exceptions for error handling
 * - Supports multiple tax systems (VAT, sales tax, GST)
 *
 * Tax Calculation Patterns:
 * 1. EU VAT System:
 *    - Destination-based (buyer location)
 *    - B2B reverse charge (buyer accounts for VAT if valid VAT number)
 *    - B2C standard rates apply
 *    - Different rates per category (standard/reduced/zero-rated)
 *
 * 2. US Sales Tax System:
 *    - Destination-based (ship-to address)
 *    - State + county + city rates (compound tax)
 *    - Nexus rules determine obligation
 *
 * 3. Other Systems:
 *    - GST (Goods and Services Tax) - Australia, Canada, India
 *    - OSS (One-Stop-Shop) - EU cross-border
 *
 * Implementations: EuVatCalculator, UsStateTaxCalculator, CanadaGstCalculator
 */
interface TaxCalculatorInterface
{
    /**
     * Calculate tax for a given amount.
     *
     * @param int $amountInCents Gross or net amount depending on implementation
     * @param TaxJurisdiction $sellerJurisdiction Where the seller is established
     * @param TaxJurisdiction $buyerJurisdiction Where the buyer is located
     * @param TaxCategory $category Product tax category
     * @param bool $isB2B True if buyer is a business with valid VAT number
     * @param string|null $buyerVatNumber Buyer's VAT number for B2B transactions
     *
     * @return TaxCalculationResult Tax calculation with breakdown
     */
    public function calculate(
        int $amountInCents,
        TaxJurisdiction $sellerJurisdiction,
        TaxJurisdiction $buyerJurisdiction,
        TaxCategory $category,
        bool $isB2B = false,
        ?string $buyerVatNumber = null
    ): TaxCalculationResult;

    /**
     * Check if this calculator supports the given jurisdiction.
     *
     * @param TaxJurisdiction $jurisdiction Jurisdiction to check
     *
     * @return bool True if this calculator can handle the jurisdiction
     */
    public function supports(TaxJurisdiction $jurisdiction): bool;

    /**
     * Get the calculator identifier.
     *
     * @return string Calculator name (e.g., 'eu_vat', 'us_sales_tax', 'canada_gst')
     */
    public function getName(): string;
}
