<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command;

use App\Catalog\Domain\Model\ProductId;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Command to generate all variant combinations for a configurable product.
 */
final readonly class GenerateVariants
{
    public function __construct(
        public ProductId $productId,
        public TenantId $tenantId,
        public ?Money $defaultPrice = null,
        public int $defaultStock = 0,
        public bool $activateByDefault = true,
        public ?string $skuBase = null
    ) {
    }
}
