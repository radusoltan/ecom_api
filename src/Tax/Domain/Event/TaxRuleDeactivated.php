<?php

declare(strict_types=1);

namespace App\Tax\Domain\Event;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tax\Domain\ValueObject\TaxRuleId;

/**
 * Tax Rule Deactivated Domain Event.
 *
 * Dispatched when a tax rule is deactivated.
 */
final readonly class TaxRuleDeactivated
{
    public function __construct(
        public TaxRuleId $taxRuleId,
        public TenantId $tenantId
    ) {
    }
}
