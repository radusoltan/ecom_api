<?php

declare(strict_types=1);

namespace App\Tax\Domain\Event;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tax\Domain\Model\TaxCategory;
use App\Tax\Domain\Model\TaxJurisdiction;
use App\Tax\Domain\Model\TaxRate;
use App\Tax\Domain\Model\TaxRuleId;

/**
 * Tax Rule Created Domain Event.
 *
 * Dispatched when a new tax rule is created.
 * Contains essential information about the newly created rule.
 */
final readonly class TaxRuleCreated
{
    public function __construct(
        public TaxRuleId $taxRuleId,
        public TenantId $tenantId,
        public TaxJurisdiction $jurisdiction,
        public TaxCategory $category,
        public TaxRate $rate,
        public string $name,
        public int $priority,
        public bool $isActive,
        public \DateTimeImmutable $occurredAt = new \DateTimeImmutable()
    ) {
    }
}
