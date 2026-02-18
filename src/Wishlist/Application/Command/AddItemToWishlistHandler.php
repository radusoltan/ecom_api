<?php

declare(strict_types=1);

namespace App\Wishlist\Application\Command;

use App\Wishlist\Domain\Model\Wishlist;
use App\Wishlist\Domain\Repository\WishlistRepositoryInterface;
use App\Wishlist\Domain\ValueObject\WishlistId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AddItemToWishlistHandler
{
    public function __construct(
        private WishlistRepositoryInterface $wishlistRepository,
    ) {
    }

    public function __invoke(AddItemToWishlist $command): void
    {
        // Find or create wishlist for customer
        $wishlist = $this->wishlistRepository->findByCustomerId(
            $command->customerId,
            $command->tenantId
        );

        if (null === $wishlist) {
            $wishlist = Wishlist::create(
                WishlistId::generate(),
                $command->customerId,
                $command->tenantId
            );
        }

        // Add item to wishlist (will throw exception if already exists)
        $wishlist->addItem($command->productId);

        // Save wishlist
        $this->wishlistRepository->save($wishlist);
    }
}
