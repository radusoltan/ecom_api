<?php

declare(strict_types=1);

namespace App\Tax\Application\Command;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tax\Domain\ValueObject\TaxRuleId;

/**
 * Create Tax Rule Command
 *
 * Creates a new tax rule for a jurisdiction.
 */
final readonly class CreateTaxRule
{
    public function __construct(
        public TaxRuleId $id,
        public TenantId $tenantId,
        public string $name,
        public string $countryCode,
        public ?string $regionCode,
        public float $ratePercentage
    ) {
    }
}
