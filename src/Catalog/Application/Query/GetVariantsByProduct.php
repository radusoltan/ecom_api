<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query;

use App\Catalog\Domain\Model\ProductId;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Query to get all variants for a product
 */
final readonly class GetVariantsByProduct
{
    /**
     * @param array<string, string> $filters - Optional filters (e.g., ['color' => 'red', 'size' => 'xl'])
     */
    public function __construct(
        public ProductId $productId,
        public TenantId $tenantId,
        public array $filters = [],
        public ?bool $activeOnly = null,
        public int $page = 1,
        public int $limit = 50
    ) {}
}