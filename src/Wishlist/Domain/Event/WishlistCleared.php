<?php

declare(strict_types=1);

namespace App\Wishlist\Domain\Event;

use App\Wishlist\Domain\ValueObject\WishlistId;

/**
 * Domain event: Wishlist was cleared
 */
final readonly class WishlistCleared
{
    public function __construct(
        public WishlistId $wishlistId,
        public string $customerId,
        public int $itemCount,
        public \DateTimeImmutable $occurredAt
    ) {}
}
