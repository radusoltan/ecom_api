<?php

declare(strict_types=1);

namespace App\Tax\Application\Command;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tax\Domain\Model\TaxRuleId;

/**
 * Update Tax Rate Command.
 *
 * Updates the tax rate percentage for an existing active tax rule.
 *
 * Business Rules:
 * - Tax rule must exist
 * - Tax rule must be active (cannot change rate on inactive rule)
 * - Rate must be valid (handled by TaxRate value object)
 */
final readonly class UpdateTaxRate
{
    public function __construct(
        public TaxRuleId $id,
        public TenantId $tenantId,
        public float $ratePercentage,
    ) {
    }
}
