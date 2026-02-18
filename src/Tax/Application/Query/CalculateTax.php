<?php

declare(strict_types=1);

namespace App\Tax\Application\Query;

use App\Shared\Domain\ValueObject\TenantId;

/**
 * Calculate Tax Query.
 *
 * Calculates tax for given amount and destination.
 */
final readonly class CalculateTax
{
    public function __construct(
        public int $amountInCents,
        public string $countryCode,
        public ?string $regionCode,
        public TenantId $tenantId,
    ) {
    }
}
