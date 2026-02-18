<?php

declare(strict_types=1);

namespace App\Tax\Application\Query;

use App\Shared\Domain\ValueObject\TenantId;

/**
 * Get All Tax Rules Query.
 */
final readonly class GetAllTaxRules
{
    public function __construct(
        public TenantId $tenantId,
        public bool $activeOnly = false,
        public int $limit = 100,
        public int $offset = 0,
    ) {
    }
}
