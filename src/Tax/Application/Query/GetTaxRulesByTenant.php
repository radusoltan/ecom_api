<?php

declare(strict_types=1);

namespace App\Tax\Application\Query;

use App\Shared\Domain\ValueObject\TenantId;

/**
 * Get Tax Rules By Tenant Query.
 *
 * Returns all tax rules for a specific tenant.
 * Useful for admin panels to display all configured tax rules.
 */
final readonly class GetTaxRulesByTenant
{
    public function __construct(
        public TenantId $tenantId
    ) {
    }
}
