<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command;

use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\VariantId;
use App\Catalog\Domain\ValueObject\VariantSKU;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Command to create a single variant
 */
final readonly class CreateVariant
{
    /**
     * @param array<string, string> $optionValueMap - Map of option code to value code
     */
    public function __construct(
        public VariantId $variantId,
        public ProductId $productId,
        public TenantId $tenantId,
        public VariantSKU $sku,
        public array $optionValueMap,
        public Money $price,
        public int $stockQuantity,
        public bool $trackInventory = true,
        public bool $allowBackorder = false,
        public bool $isActive = true
    ) {}
}
