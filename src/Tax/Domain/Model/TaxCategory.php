<?php

declare(strict_types=1);

namespace App\Tax\Domain\Model;

/**
 * Tax Category enum.
 *
 * Defines the type of tax rate applicable to products/services.
 * Based on EU VAT directive categories, applicable globally.
 *
 * Business Rules:
 * - STANDARD: Normal goods and services (e.g., 19% in DE, 20% in FR)
 * - REDUCED: Reduced rate items (e.g., 7% in DE for food, books)
 * - SUPER_REDUCED: Super reduced rate (e.g., 4% in ES for bread, milk)
 * - ZERO: Zero-rated but taxable (exports, some food in UK)
 * - EXEMPT: Not subject to VAT (financial services, education, healthcare)
 *
 * Examples:
 * - Electronics: STANDARD
 * - Books: REDUCED (in many EU countries)
 * - Basic food: REDUCED or SUPER_REDUCED
 * - International shipping: ZERO
 * - Insurance: EXEMPT
 */
enum TaxCategory: string
{
    case STANDARD = 'standard';
    case REDUCED = 'reduced';
    case SUPER_REDUCED = 'super_reduced';
    case ZERO = 'zero';
    case EXEMPT = 'exempt';

    public function isExempt(): bool
    {
        return $this === self::EXEMPT;
    }

    public function isZeroRate(): bool
    {
        return $this === self::ZERO || $this === self::EXEMPT;
    }

    public function description(): string
    {
        return match ($this) {
            self::STANDARD => 'Standard rate',
            self::REDUCED => 'Reduced rate',
            self::SUPER_REDUCED => 'Super reduced rate',
            self::ZERO => 'Zero rate (taxable)',
            self::EXEMPT => 'Tax exempt',
        };
    }
}
