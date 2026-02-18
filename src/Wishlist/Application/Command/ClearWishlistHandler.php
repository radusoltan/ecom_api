<?php

declare(strict_types=1);

namespace App\Wishlist\Application\Command;

use App\Wishlist\Domain\Repository\WishlistRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ClearWishlistHandler
{
    public function __construct(
        private WishlistRepositoryInterface $wishlistRepository,
    ) {
    }

    public function __invoke(ClearWishlist $command): void
    {
        $wishlist = $this->wishlistRepository->findByCustomerId(
            $command->customerId,
            $command->tenantId
        );

        if (null === $wishlist) {
            // Nothing to clear
            return;
        }

        $wishlist->clear();

        $this->wishlistRepository->save($wishlist);
    }
}
