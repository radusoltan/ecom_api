<?php

declare(strict_types=1);

namespace App\Cart\Application\Command;

use App\Cart\Application\Service\StockValidator;
use App\Cart\Domain\Exception\CartNotFoundException;
use App\Cart\Domain\Model\CartId;
use App\Cart\Domain\Model\CartItemId;
use App\Cart\Domain\Model\Quantity;
use App\Cart\Domain\Repository\CartRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateCartQuantityHandler
{
    public function __construct(
        private CartRepositoryInterface $cartRepository,
        private StockValidator $stockValidator
    ) {
    }

    public function __invoke(UpdateCartQuantity $command): void
    {
        $cartId = CartId::fromString($command->cartId);
        $cart = $this->cartRepository->findById($cartId);

        if (null === $cart) {
            throw CartNotFoundException::withId($command->cartId);
        }

        // Find the cart item to get product details for stock validation
        $cartItemId = CartItemId::fromString($command->cartItemId);
        $cartItem = null;
        foreach ($cart->items() as $item) {
            if ($item->id()->equals($cartItemId)) {
                $cartItem = $item;

                break;
            }
        }

        if (null !== $cartItem) {
            // Validate stock availability for the new quantity
            $this->stockValidator->validateStockAvailability(
                $cartItem->productId(),
                $cartItem->variantId(),
                $cart->tenantId(),
                $command->newQuantity
            );
        }

        $cart->updateQuantity(
            $cartItemId,
            Quantity::fromInt($command->newQuantity)
        );

        $this->cartRepository->save($cart);
    }
}
