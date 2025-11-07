<?php

declare(strict_types=1);

namespace App\Tax\Application\Query;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tax\Domain\ValueObject\TaxRuleId;

/**
 * Get Tax Rule By ID Query.
 */
final readonly class GetTaxRuleById
{
    public function __construct(
        public TaxRuleId $id,
        public TenantId $tenantId
    ) {
    }
}
