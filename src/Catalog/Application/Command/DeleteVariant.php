<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command;

use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\VariantId;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Command to delete a variant
 */
final readonly class DeleteVariant
{
    public function __construct(
        public VariantId $variantId,
        public ProductId $productId,
        public TenantId $tenantId
    ) {}
}
