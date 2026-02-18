<?php

declare(strict_types=1);

namespace App\Wishlist\Domain\Event;

use App\Catalog\Domain\Model\ProductId;
use App\Wishlist\Domain\ValueObject\WishlistId;

/**
 * Domain event: Item was removed from wishlist.
 */
final readonly class ItemRemovedFromWishlist
{
    public function __construct(
        public WishlistId $wishlistId,
        public ProductId $productId,
        public string $customerId,
        public \DateTimeImmutable $occurredAt,
    ) {
    }
}
