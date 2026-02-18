<?php

declare(strict_types=1);

namespace App\Wishlist\Application\Command;

use App\Wishlist\Domain\Repository\WishlistRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RemoveItemFromWishlistHandler
{
    public function __construct(
        private WishlistRepositoryInterface $wishlistRepository,
    ) {
    }

    public function __invoke(RemoveItemFromWishlist $command): void
    {
        $wishlist = $this->wishlistRepository->findByCustomerId(
            $command->customerId,
            $command->tenantId
        );

        if (null === $wishlist) {
            throw new \DomainException('Wishlist not found');
        }

        $wishlist->removeItem($command->productId);

        $this->wishlistRepository->save($wishlist);
    }
}
