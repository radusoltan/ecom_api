<?php

declare(strict_types=1);

namespace App\Cart\Application\Query;

use App\Cart\Application\DTO\CartDTO;
use App\Cart\Domain\Model\CartId;
use App\Cart\Domain\Repository\CartRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetCartHandler
{
    public function __construct(
        private CartRepositoryInterface $cartRepository
    ) {
    }

    public function __invoke(GetCart $query): ?CartDTO
    {
        $cart = $this->cartRepository->findById(
            CartId::fromString($query->cartId)
        );

        return $cart ? CartDTO::fromDomain($cart) : null;
    }
}
