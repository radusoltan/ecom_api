<?php

declare(strict_types=1);

namespace App\Wishlist\Application\Query;

use App\Wishlist\Domain\Model\Wishlist;
use App\Wishlist\Domain\Repository\WishlistRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetWishlistHandler
{
    public function __construct(
        private WishlistRepositoryInterface $wishlistRepository
    ) {}

    public function __invoke(GetWishlist $query): ?Wishlist
    {
        return $this->wishlistRepository->findByCustomerId(
            $query->customerId,
            $query->tenantId
        );
    }
}
