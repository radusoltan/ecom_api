<?php

declare(strict_types=1);

namespace App\Tax\Application\Command;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tax\Domain\Model\TaxRuleId;

/**
 * Delete Tax Rule Command.
 */
final readonly class DeleteTaxRule
{
    public function __construct(
        public TaxRuleId $id,
        public TenantId $tenantId
    ) {
    }
}
