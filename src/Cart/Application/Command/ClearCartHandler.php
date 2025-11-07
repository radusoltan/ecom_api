<?php

declare(strict_types=1);

namespace App\Cart\Application\Command;

use App\Cart\Domain\Exception\CartNotFoundException;
use App\Cart\Domain\Model\CartId;
use App\Cart\Domain\Repository\CartRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ClearCartHandler
{
    public function __construct(
        private CartRepositoryInterface $cartRepository
    ) {
    }

    public function __invoke(ClearCart $command): void
    {
        $cartId = CartId::fromString($command->cartId);
        $cart = $this->cartRepository->findById($cartId);

        if (null === $cart) {
            throw CartNotFoundException::withId($command->cartId);
        }

        $cart->clear();

        $this->cartRepository->save($cart);
    }
}
