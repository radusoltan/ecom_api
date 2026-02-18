<?php

declare(strict_types=1);

namespace App\Tax\Application\Command;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tax\Domain\Model\TaxRuleId;

/**
 * Activate Tax Rule Command.
 *
 * Activates a previously deactivated tax rule.
 *
 * Business Rules:
 * - Tax rule must exist
 * - Tax rule must be inactive (throws exception if already active)
 */
final readonly class ActivateTaxRule
{
    public function __construct(
        public TaxRuleId $id,
        public TenantId $tenantId,
    ) {
    }
}
