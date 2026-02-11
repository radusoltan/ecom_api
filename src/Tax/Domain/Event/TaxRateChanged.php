<?php

declare(strict_types=1);

namespace App\Tax\Domain\Event;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tax\Domain\Model\TaxCategory;
use App\Tax\Domain\Model\TaxJurisdiction;
use App\Tax\Domain\Model\TaxRate;
use App\Tax\Domain\Model\TaxRuleId;

/**
 * Tax Rate Changed Domain Event.
 *
 * Dispatched when a tax rule's rate is updated.
 * This is critical for audit trails and tax calculation services.
 *
 * Contains both old and new rates for historical tracking
 * (important for tax compliance and reporting).
 */
final readonly class TaxRateChanged
{
    public function __construct(
        public TaxRuleId $taxRuleId,
        public TenantId $tenantId,
        public TaxJurisdiction $jurisdiction,
        public TaxCategory $category,
        public TaxRate $oldRate,
        public TaxRate $newRate,
        public \DateTimeImmutable $occurredAt = new \DateTimeImmutable()
    ) {
    }
}
