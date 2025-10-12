<?php

declare(strict_types=1);

namespace App\Tax\Application\Command;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tax\Domain\ValueObject\TaxRuleId;

/**
 * Update Tax Rule Command
 *
 * Updates tax rule name and rate.
 * Note: Jurisdiction cannot be changed (create new rule instead).
 */
final readonly class UpdateTaxRule
{
    public function __construct(
        public TaxRuleId $id,
        public TenantId $tenantId,
        public string $name,
        public float $ratePercentage
    ) {
    }
}
