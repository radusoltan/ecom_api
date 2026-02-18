<?php

declare(strict_types=1);

namespace App\Cart\Application\Query;

use App\Cart\Application\DTO\CartSummaryDTO;
use App\Cart\Domain\Model\CartId;
use App\Cart\Domain\Repository\CartRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetCartSummaryHandler
{
    public function __construct(
        private CartRepositoryInterface $cartRepository,
    ) {
    }

    public function __invoke(GetCartSummary $query): ?CartSummaryDTO
    {
        $cart = $this->cartRepository->findById(
            CartId::fromString($query->cartId)
        );

        return $cart ? CartSummaryDTO::fromDomain($cart) : null;
    }
}
