<?php

declare(strict_types=1);

namespace App\Tax\Infrastructure\Calculator;

use App\Tax\Domain\Service\TaxCalculationResult;
use App\Tax\Domain\Service\TaxCalculatorInterface;
use App\Tax\Domain\ValueObject\TaxCategory;
use App\Tax\Domain\ValueObject\TaxJurisdiction;
use App\Tax\Domain\ValueObject\TaxRate;

/**
 * EU VAT Calculator - implements EU VAT rules and reverse charge mechanism.
 *
 * Implements Value Added Tax calculation for all 27 EU member states.
 * Handles B2B reverse charge, reduced rates, and exempt categories.
 *
 * Business Rules (2024 Rates):
 * 1. Standard Rates: 17%-27% depending on country
 * 2. Reduced Rates: 5%-13% for food, books, medical supplies
 * 3. Super Reduced Rates: 4%-10% for essential goods
 * 4. Zero Rates: Exports, international services
 * 5. Exempt: Financial, educational, medical services
 * 6. Reverse Charge: B2B cross-border transactions
 *
 * VAT Directive 2006/112/EC Compliance:
 * - Destination principle (buyer's location)
 * - B2B reverse charge (Article 194-197)
 * - B2C standard rates apply
 * - Domestic vs. cross-border handling
 *
 * References:
 * - https://taxation-customs.ec.europa.eu/taxation/vat/how-vat-works_en
 * - VAT Directive 2006/112/EC
 *
 * @see TaxCalculatorInterface
 */
final class EuVatCalculator implements TaxCalculatorInterface
{
    /**
     * EU member states (27 countries as of 2024).
     *
     * @var array<string>
     */
    private const EU_COUNTRIES = [
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
        'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
        'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE',
    ];

    /**
     * EU VAT rates by country (2024 rates).
     *
     * Format: [
     *   'COUNTRY_CODE' => [
     *     'standard' => XX.X,         // Standard rate (most goods/services)
     *     'reduced' => XX.X,          // Reduced rate (food, books, etc.)
     *     'super_reduced' => XX.X,    // Super reduced rate (essential goods)
     *     'parking' => XX.X,          // Parking rate (rarely used)
     *   ]
     * ]
     *
     * All 27 EU member states included.
     *
     * @var array<string, array<string, float>>
     */
    private const EU_VAT_RATES = [
        // Western Europe
        'DE' => ['standard' => 19.0, 'reduced' => 7.0],     // Germany
        'FR' => ['standard' => 20.0, 'reduced' => 5.5, 'super_reduced' => 2.1],  // France
        'IT' => ['standard' => 22.0, 'reduced' => 10.0, 'super_reduced' => 5.0], // Italy
        'ES' => ['standard' => 21.0, 'reduced' => 10.0, 'super_reduced' => 4.0], // Spain
        'NL' => ['standard' => 21.0, 'reduced' => 9.0],     // Netherlands
        'BE' => ['standard' => 21.0, 'reduced' => 6.0, 'parking' => 12.0],       // Belgium
        'AT' => ['standard' => 20.0, 'reduced' => 10.0, 'parking' => 13.0],      // Austria
        'LU' => ['standard' => 17.0, 'reduced' => 8.0, 'super_reduced' => 3.0, 'parking' => 14.0], // Luxembourg

        // Northern Europe
        'DK' => ['standard' => 25.0],                       // Denmark (no reduced rates)
        'SE' => ['standard' => 25.0, 'reduced' => 12.0, 'super_reduced' => 6.0], // Sweden
        'FI' => ['standard' => 24.0, 'reduced' => 14.0, 'super_reduced' => 10.0], // Finland
        'EE' => ['standard' => 22.0, 'reduced' => 9.0],     // Estonia
        'LV' => ['standard' => 21.0, 'reduced' => 12.0, 'super_reduced' => 5.0], // Latvia
        'LT' => ['standard' => 21.0, 'reduced' => 9.0, 'super_reduced' => 5.0],  // Lithuania

        // Southern Europe
        'PT' => ['standard' => 23.0, 'reduced' => 6.0, 'super_reduced' => 13.0, 'parking' => 13.0], // Portugal
        'GR' => ['standard' => 24.0, 'reduced' => 13.0, 'super_reduced' => 6.0], // Greece
        'CY' => ['standard' => 19.0, 'reduced' => 9.0, 'super_reduced' => 5.0],  // Cyprus
        'MT' => ['standard' => 18.0, 'reduced' => 7.0, 'super_reduced' => 5.0],  // Malta
        'HR' => ['standard' => 25.0, 'reduced' => 13.0, 'super_reduced' => 5.0], // Croatia
        'SI' => ['standard' => 22.0, 'reduced' => 9.5, 'super_reduced' => 5.0],  // Slovenia

        // Eastern Europe
        'PL' => ['standard' => 23.0, 'reduced' => 8.0, 'super_reduced' => 5.0],  // Poland
        'RO' => ['standard' => 19.0, 'reduced' => 9.0, 'super_reduced' => 5.0],  // Romania
        'BG' => ['standard' => 20.0, 'reduced' => 9.0],     // Bulgaria
        'CZ' => ['standard' => 21.0, 'reduced' => 12.0, 'super_reduced' => 10.0], // Czech Republic
        'SK' => ['standard' => 20.0, 'reduced' => 10.0],    // Slovakia
        'HU' => ['standard' => 27.0, 'reduced' => 18.0, 'super_reduced' => 5.0], // Hungary (highest in EU)

        // Ireland
        'IE' => ['standard' => 23.0, 'reduced' => 13.5, 'super_reduced' => 9.0, 'parking' => 13.5], // Ireland
    ];

    /**
     * Calculate EU VAT for a given amount and parameters.
     *
     * VAT Calculation Logic:
     * 1. Check if buyer is in EU
     * 2. If B2B cross-border → Reverse charge (no VAT charged by seller)
     * 3. If B2B domestic → Standard VAT applies
     * 4. If B2C → Standard VAT applies
     * 5. Apply correct rate based on category (standard/reduced/exempt)
     *
     * @param int             $amountInCents      Net amount (before VAT)
     * @param TaxJurisdiction $sellerJurisdiction Seller's country
     * @param TaxJurisdiction $buyerJurisdiction  Buyer's country
     * @param TaxCategory     $category           Product category (determines rate)
     * @param bool            $isB2B              True if buyer is a business with valid VAT number
     * @param string|null     $buyerVatNumber     Buyer's VAT number (required for B2B)
     *
     * @return TaxCalculationResult Calculation with VAT breakdown
     */
    public function calculate(
        int $amountInCents,
        TaxJurisdiction $sellerJurisdiction,
        TaxJurisdiction $buyerJurisdiction,
        TaxCategory $category,
        bool $isB2B = false,
        ?string $buyerVatNumber = null,
    ): TaxCalculationResult {
        // Ensure buyer jurisdiction is in EU
        if (!$this->isEu($buyerJurisdiction)) {
            throw new \InvalidArgumentException(sprintf('EU VAT calculator only supports EU jurisdictions, got: %s', $buyerJurisdiction->toString()));
        }

        // Check if exempt category
        if ($category->isExempt()) {
            return TaxCalculationResult::exempt(
                amountInCents: $amountInCents,
                jurisdiction: $buyerJurisdiction,
                category: $category,
                reason: 'Tax-exempt category (financial, educational, medical services)'
            );
        }

        // Check if zero-rate category
        if ($category->isZeroRated()) {
            return TaxCalculationResult::exempt(
                amountInCents: $amountInCents,
                jurisdiction: $buyerJurisdiction,
                category: $category,
                reason: 'Zero-rated transaction (exports, international services)'
            );
        }

        // Get applicable VAT rate for buyer's country and category
        $rate = $this->getRateForCategory($buyerJurisdiction->getCountryCode(), $category);

        // Check for reverse charge mechanism (B2B cross-border only)
        if ($this->shouldApplyReverseCharge($sellerJurisdiction, $buyerJurisdiction, $isB2B, $buyerVatNumber)) {
            return TaxCalculationResult::reverseCharge(
                amountInCents: $amountInCents,
                theoreticalRate: $rate,
                jurisdiction: $buyerJurisdiction,
                category: $category
            );
        }

        // Standard VAT calculation
        $taxAmount = $rate->calculateTaxAmount($amountInCents);

        return TaxCalculationResult::create(
            netAmountInCents: $amountInCents,
            taxAmountInCents: $taxAmount,
            appliedRate: $rate,
            taxJurisdiction: $buyerJurisdiction,
            category: $category,
            taxType: 'vat',
            reverseCharge: false,
            breakdown: [
                'seller_country' => $sellerJurisdiction->getCountryCode(),
                'buyer_country' => $buyerJurisdiction->getCountryCode(),
                'is_domestic' => $sellerJurisdiction->equals($buyerJurisdiction),
                'is_b2b' => $isB2B,
            ]
        );
    }

    /**
     * Check if this calculator supports the jurisdiction.
     *
     * @param TaxJurisdiction $jurisdiction Jurisdiction to check
     *
     * @return bool True if jurisdiction is in EU
     */
    public function supports(TaxJurisdiction $jurisdiction): bool
    {
        return $this->isEu($jurisdiction);
    }

    /**
     * Get calculator name.
     *
     * @return string Calculator identifier
     */
    public function getName(): string
    {
        return 'eu_vat';
    }

    /**
     * Check if jurisdiction is in EU.
     *
     * @param TaxJurisdiction $jurisdiction Jurisdiction to check
     *
     * @return bool True if country is EU member state
     */
    private function isEu(TaxJurisdiction $jurisdiction): bool
    {
        return in_array($jurisdiction->getCountryCode(), self::EU_COUNTRIES, true);
    }

    /**
     * Determine if reverse charge mechanism applies.
     *
     * Reverse Charge Rules (VAT Directive Article 194-197):
     * - ONLY applies to B2B cross-border transactions within EU
     * - Seller does NOT charge VAT (issues invoice without VAT)
     * - Buyer accounts for VAT in their own country
     * - Requires valid VAT number from buyer
     * - Does NOT apply to domestic transactions (same country)
     * - Does NOT apply to B2C transactions (even cross-border)
     *
     * Examples:
     * - DE seller → FR buyer (B2B, valid VAT) = REVERSE CHARGE ✅
     * - DE seller → DE buyer (B2B, valid VAT) = STANDARD VAT ❌
     * - DE seller → FR buyer (B2C) = STANDARD VAT ❌
     *
     * @param TaxJurisdiction $sellerJurisdiction Seller's country
     * @param TaxJurisdiction $buyerJurisdiction  Buyer's country
     * @param bool            $isB2B              Is this a B2B transaction?
     * @param string|null     $buyerVatNumber     Buyer's VAT number
     *
     * @return bool True if reverse charge applies
     */
    private function shouldApplyReverseCharge(
        TaxJurisdiction $sellerJurisdiction,
        TaxJurisdiction $buyerJurisdiction,
        bool $isB2B,
        ?string $buyerVatNumber,
    ): bool {
        // Reverse charge ONLY for B2B
        if (!$isB2B) {
            return false;
        }

        // Reverse charge REQUIRES valid VAT number
        if (null === $buyerVatNumber || '' === trim($buyerVatNumber)) {
            return false;
        }

        // Reverse charge ONLY for cross-border (different countries)
        if ($sellerJurisdiction->equals($buyerJurisdiction)) {
            return false;
        }

        // Both must be in EU
        if (!$this->isEu($sellerJurisdiction) || !$this->isEu($buyerJurisdiction)) {
            return false;
        }

        return true;
    }

    /**
     * Get VAT rate for specific country and category.
     *
     * Rate Selection Logic:
     * - STANDARD → standard rate
     * - REDUCED → reduced rate (or standard if not available)
     * - ZERO_RATED → 0% (handled before this method)
     * - EXEMPT → 0% (handled before this method)
     *
     * Fallback Chain:
     * - reduced → standard
     * - standard (always available)
     *
     * Note: TaxCategory uses constants (REDUCED, ZERO_RATED, etc.) not enum cases,
     * so we check with isReduced(), isStandard(), etc.
     *
     * @param string      $countryCode ISO country code (e.g., 'DE', 'FR')
     * @param TaxCategory $category    Tax category
     *
     * @return TaxRate Applicable VAT rate
     *
     * @throws \InvalidArgumentException If country not found
     */
    private function getRateForCategory(string $countryCode, TaxCategory $category): TaxRate
    {
        $rates = self::EU_VAT_RATES[$countryCode] ?? null;

        if (null === $rates) {
            throw new \InvalidArgumentException(sprintf('VAT rates not configured for country: %s', $countryCode));
        }

        // Standard rate
        if ($category->isStandard()) {
            return TaxRate::fromPercentage($rates['standard']);
        }

        // Reduced rate (fallback to standard if not available)
        if ($category->isReduced()) {
            return TaxRate::fromPercentage(
                $rates['reduced'] ?? $rates['standard']
            );
        }

        // Zero-rated and exempt (should be handled before this method, but defensive)
        if ($category->isZeroRated() || $category->isExempt()) {
            return TaxRate::zero();
        }

        // Default to standard rate for unknown categories
        return TaxRate::fromPercentage($rates['standard']);
    }
}
