<?php

declare(strict_types=1);

namespace App\Tax\Domain\Service;

use App\Tax\Domain\ValueObject\TaxCategory;
use App\Tax\Domain\ValueObject\TaxJurisdiction;
use App\Tax\Domain\ValueObject\TaxRate;

/**
 * Tax Calculation Result DTO.
 *
 * Immutable result object from tax calculation.
 * Contains all information needed for tax reporting and order processing.
 *
 * Design:
 * - Immutable (readonly class)
 * - Named constructors for different scenarios
 * - Rich helper methods for queries
 * - Supports compound taxes (US state+county+city)
 */
final readonly class TaxCalculationResult
{
    /**
     * @param int $netAmountInCents Net amount (before tax)
     * @param int $taxAmountInCents Total tax amount
     * @param int $grossAmountInCents Gross amount (net + tax)
     * @param TaxRate $appliedRate Effective tax rate applied
     * @param TaxJurisdiction $taxJurisdiction Jurisdiction where tax applies
     * @param TaxCategory $category Tax category used
     * @param string $taxType Type of tax ('vat', 'sales_tax', 'gst', etc.)
     * @param bool $reverseCharge True if reverse charge applies (B2B VAT)
     * @param string|null $exemptionReason Reason if tax exempt
     * @param array<string, mixed> $breakdown Detailed breakdown for compound taxes
     */
    private function __construct(
        public int $netAmountInCents,
        public int $taxAmountInCents,
        public int $grossAmountInCents,
        public TaxRate $appliedRate,
        public TaxJurisdiction $taxJurisdiction,
        public TaxCategory $category,
        public string $taxType,
        public bool $reverseCharge,
        public ?string $exemptionReason,
        public array $breakdown
    ) {
    }

    /**
     * Create standard tax calculation result.
     *
     * @param array<string, mixed> $breakdown
     */
    public static function create(
        int $netAmountInCents,
        int $taxAmountInCents,
        TaxRate $appliedRate,
        TaxJurisdiction $taxJurisdiction,
        TaxCategory $category,
        string $taxType,
        bool $reverseCharge = false,
        ?string $exemptionReason = null,
        array $breakdown = []
    ): self {
        return new self(
            netAmountInCents: $netAmountInCents,
            taxAmountInCents: $taxAmountInCents,
            grossAmountInCents: $netAmountInCents + $taxAmountInCents,
            appliedRate: $appliedRate,
            taxJurisdiction: $taxJurisdiction,
            category: $category,
            taxType: $taxType,
            reverseCharge: $reverseCharge,
            exemptionReason: $exemptionReason,
            breakdown: $breakdown
        );
    }

    /**
     * Create exempt (no tax) result.
     */
    public static function exempt(
        int $amountInCents,
        TaxJurisdiction $jurisdiction,
        TaxCategory $category,
        string $reason
    ): self {
        return new self(
            netAmountInCents: $amountInCents,
            taxAmountInCents: 0,
            grossAmountInCents: $amountInCents,
            appliedRate: TaxRate::zero(),
            taxJurisdiction: $jurisdiction,
            category: $category,
            taxType: 'exempt',
            reverseCharge: false,
            exemptionReason: $reason,
            breakdown: []
        );
    }

    /**
     * Create reverse charge result (EU B2B VAT).
     */
    public static function reverseCharge(
        int $amountInCents,
        TaxRate $theoreticalRate,
        TaxJurisdiction $jurisdiction,
        TaxCategory $category
    ): self {
        return new self(
            netAmountInCents: $amountInCents,
            taxAmountInCents: 0,
            grossAmountInCents: $amountInCents,
            appliedRate: TaxRate::zero(),
            taxJurisdiction: $jurisdiction,
            category: $category,
            taxType: 'vat_reverse_charge',
            reverseCharge: true,
            exemptionReason: 'B2B reverse charge - buyer accounts for VAT',
            breakdown: ['theoretical_rate' => $theoreticalRate->getPercentage()]
        );
    }

    /**
     * Create compound tax result (e.g., US state + county + city).
     *
     * @param array<array{name: string, rate: float, amount: int}> $components
     */
    public static function compound(
        int $netAmountInCents,
        TaxJurisdiction $jurisdiction,
        TaxCategory $category,
        string $taxType,
        array $components
    ): self {
        $totalTaxAmount = array_sum(array_column($components, 'amount'));
        $totalRate = array_sum(array_column($components, 'rate'));

        return new self(
            netAmountInCents: $netAmountInCents,
            taxAmountInCents: $totalTaxAmount,
            grossAmountInCents: $netAmountInCents + $totalTaxAmount,
            appliedRate: TaxRate::fromPercentage($totalRate),
            taxJurisdiction: $jurisdiction,
            category: $category,
            taxType: $taxType,
            reverseCharge: false,
            exemptionReason: null,
            breakdown: ['components' => $components]
        );
    }

    public function isExempt(): bool
    {
        return $this->exemptionReason !== null;
    }

    public function isReverseCharge(): bool
    {
        return $this->reverseCharge;
    }

    /**
     * Get effective tax rate as percentage.
     */
    public function effectiveRate(): float
    {
        if ($this->netAmountInCents === 0) {
            return 0.0;
        }

        return ($this->taxAmountInCents / $this->netAmountInCents) * 100;
    }

    /**
     * Get the percentage rate (shortcut for appliedRate->getPercentage()).
     */
    public function percentage(): float
    {
        return $this->appliedRate->getPercentage();
    }
}
