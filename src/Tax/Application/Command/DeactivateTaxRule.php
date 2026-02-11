<?php

declare(strict_types=1);

namespace App\Tax\Application\Command;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tax\Domain\Model\TaxRuleId;

/**
 * Deactivate Tax Rule Command.
 */
final readonly class DeactivateTaxRule
{
    public function __construct(
        public TaxRuleId $id,
        public TenantId $tenantId
    ) {
    }
}
