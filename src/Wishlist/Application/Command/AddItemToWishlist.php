<?php

declare(strict_types=1);

namespace App\Wishlist\Application\Command;

use App\Catalog\Domain\Model\ProductId;
use App\Shared\Domain\ValueObject\TenantId;

final readonly class AddItemToWishlist
{
    public function __construct(
        public string $customerId,
        public ProductId $productId,
        public TenantId $tenantId
    ) {}
}
