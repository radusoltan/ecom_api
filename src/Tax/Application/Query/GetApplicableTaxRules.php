<?php

declare(strict_types=1);

namespace App\Tax\Application\Query;

use App\Shared\Domain\ValueObject\TenantId;

/**
 * Get Applicable Tax Rules Query.
 *
 * Finds tax rules that apply to a specific jurisdiction, category, and date.
 * Returns rules sorted by priority (highest first).
 *
 * Use Case:
 * - Tax calculation for checkout
 * - Admin preview of applicable rules
 * - Rule conflict detection
 *
 * Business Rules:
 * - Only returns active rules
 * - Filters by validity date (validFrom <= asOfDate <= validTo)
 * - Sorted by priority descending (highest priority first)
 */
final readonly class GetApplicableTaxRules
{
    public function __construct(
        public TenantId $tenantId,
        public string $countryCode,
        public ?string $regionCode,
        public string $category,
        public \DateTimeImmutable $asOfDate
    ) {
    }
}
