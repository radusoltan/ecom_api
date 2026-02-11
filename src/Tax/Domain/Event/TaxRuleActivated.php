<?php

declare(strict_types=1);

namespace App\Tax\Domain\Event;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tax\Domain\Model\TaxCategory;
use App\Tax\Domain\Model\TaxJurisdiction;
use App\Tax\Domain\Model\TaxRuleId;

/**
 * Tax Rule Activated Domain Event.
 *
 * Dispatched when a tax rule is activated.
 * This is important for tax calculation services to know when to start using a rule.
 */
final readonly class TaxRuleActivated
{
    public function __construct(
        public TaxRuleId $taxRuleId,
        public TenantId $tenantId,
        public TaxJurisdiction $jurisdiction,
        public TaxCategory $category,
        public \DateTimeImmutable $occurredAt = new \DateTimeImmutable()
    ) {
    }
}
