<?php

declare(strict_types=1);

namespace App\Cart\Domain\Event;

use App\Cart\Domain\Model\CartId;
use App\Cart\Domain\Model\CartItemId;
use App\Cart\Domain\Model\Quantity;
use App\Catalog\Domain\Model\ProductId;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;

final readonly class ItemAddedToCart
{
    public function __construct(
        public CartId $cartId,
        public TenantId $tenantId,
        public CartItemId $cartItemId,
        public ProductId $productId,
        public ?string $variantId,
        public Quantity $quantity,
        public Money $unitPrice
    ) {
    }
}
