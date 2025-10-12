<?php

declare(strict_types=1);

namespace App\Tax\Domain\Event;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tax\Domain\ValueObject\TaxRate;
use App\Tax\Domain\ValueObject\TaxRuleId;

/**
 * Tax Rule Updated Domain Event
 *
 * Dispatched when a tax rule is updated.
 */
final readonly class TaxRuleUpdated
{
    public function __construct(
        public TaxRuleId $taxRuleId,
        public TenantId $tenantId,
        public string $name,
        public TaxRate $rate
    ) {
    }
}
