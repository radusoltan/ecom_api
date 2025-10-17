<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command;

use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\VariantId;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Command to update a specific variant
 */
final readonly class UpdateVariant
{
    /**
     * @param ProductImage[] $images
     */
    public function __construct(
        public VariantId $variantId,
        public ProductId $productId,
        public TenantId $tenantId,
        public ?Money $price = null,
        public ?int $stockQuantity = null,
        public ?bool $isActive = null,
        public ?bool $trackInventory = null,
        public ?bool $allowBackorder = null,
        public ?array $images = null
    ) {}
}