<?php

declare(strict_types=1);

namespace App\Cart\Application\Command;

use App\Cart\Domain\Exception\CartNotFoundException;
use App\Cart\Domain\Model\CartId;
use App\Cart\Domain\Repository\CartRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class MergeCartsHandler
{
    public function __construct(
        private CartRepositoryInterface $cartRepository,
    ) {
    }

    /**
     * @throws CartNotFoundException
     * @throws \InvalidArgumentException
     */
    public function __invoke(MergeCarts $command): void
    {
        $sourceCartId = CartId::fromString($command->sourceCartId);
        $targetCartId = CartId::fromString($command->targetCartId);

        // Edge case: if source and target are the same cart, nothing to merge
        if ($sourceCartId->equals($targetCartId)) {
            return;
        }

        $sourceCart = $this->cartRepository->findById($sourceCartId);
        if (null === $sourceCart) {
            throw CartNotFoundException::withId($command->sourceCartId);
        }

        // Issue #1: Validate source cart is active before merging
        if (!$sourceCart->isActive()) {
            throw new \InvalidArgumentException('Cannot merge from inactive source cart');
        }

        $targetCart = $this->cartRepository->findById($targetCartId);
        if (null === $targetCart) {
            throw CartNotFoundException::withId($command->targetCartId);
        }

        // Merge items from source into target
        // The Cart::addItem method handles quantity merging for matching products
        foreach ($sourceCart->items() as $item) {
            $targetCart->addItem(
                $item->productId(),
                $item->variantId(),
                $item->quantity(),
                $item->unitPrice()
            );
        }

        // Save the target cart with merged items
        $this->cartRepository->save($targetCart);

        // Clear or delete source cart based on flag
        if ($command->deleteSourceAfterMerge) {
            $this->cartRepository->remove($sourceCart);
        } else {
            // Just clear the source cart items
            $sourceCart->clear();
            $this->cartRepository->save($sourceCart);
        }
    }
}
