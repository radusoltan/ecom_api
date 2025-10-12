<?php

declare(strict_types=1);

namespace App\Tax\Domain\Event;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tax\Domain\ValueObject\TaxJurisdiction;
use App\Tax\Domain\ValueObject\TaxRate;
use App\Tax\Domain\ValueObject\TaxRuleId;

/**
 * Tax Rule Created Domain Event
 *
 * Dispatched when a new tax rule is created.
 */
final readonly class TaxRuleCreated
{
    public function __construct(
        public TaxRuleId $taxRuleId,
        public TenantId $tenantId,
        public string $name,
        public TaxJurisdiction $jurisdiction,
        public TaxRate $rate
    ) {
    }
}
