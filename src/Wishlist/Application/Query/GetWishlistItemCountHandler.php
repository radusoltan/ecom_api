<?php

declare(strict_types=1);

namespace App\Wishlist\Application\Query;

use App\Wishlist\Domain\Repository\WishlistRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetWishlistItemCountHandler
{
    public function __construct(
        private WishlistRepositoryInterface $wishlistRepository
    ) {
    }

    public function __invoke(GetWishlistItemCount $query): int
    {
        $wishlist = $this->wishlistRepository->findByCustomerId(
            $query->customerId,
            $query->tenantId
        );

        return $wishlist ? $wishlist->itemCount() : 0;
    }
}
