<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command;

use App\Catalog\Domain\Model\CategoryId;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\ProductName;
use App\Catalog\Domain\Model\SKU;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;

final readonly class CreateProduct
{
    public function __construct(
        public ProductId $id,
        public TenantId $tenantId,
        public SKU $sku,
        public ProductName $name,
        public ?string $description,
        public ?string $shortDescription,
        public Money $price,
        public ?CategoryId $categoryId,
        public int $stockQuantity,
        public bool $trackInventory = true,
        public bool $allowBackorder = false,
        public bool $isFeatured = false,
    ) {
    }
}
