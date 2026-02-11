<?php

declare(strict_types=1);

namespace App\Tax\Application\Command;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tax\Domain\Model\TaxRuleId;

/**
 * Create Tax Rule Command.
 *
 * Creates a new tax rule for a jurisdiction.
 *
 * Business Rules:
 * - All fields must match TaxRule::create() signature
 * - category: one of standard, reduced, super_reduced, zero, exempt
 * - priority: >= 0 (default 0)
 * - validFrom: must be <= validTo (if validTo is set)
 * - isReverseCharge: only applicable to EU jurisdictions
 */
final readonly class CreateTaxRule
{
    public function __construct(
        public TaxRuleId $id,
        public TenantId $tenantId,
        public string $countryCode,
        public ?string $regionCode,
        public string $category,
        public float $ratePercentage,
        public string $name,
        public ?string $description = null,
        public int $priority = 0,
        public ?\DateTimeImmutable $validFrom = null,
        public ?\DateTimeImmutable $validTo = null,
        public bool $isReverseCharge = false,
    ) {
    }
}
