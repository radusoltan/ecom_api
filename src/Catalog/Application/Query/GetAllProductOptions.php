<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query;

use App\Shared\Domain\ValueObject\TenantId;

/**
 * Query to get all unique product options across all configurable products
 * Used for filtering products in shop/catalog pages
 */
final readonly class GetAllProductOptions
{
    public function __construct(
        public TenantId $tenantId,
        public ?string $locale = null
    ) {}
}
