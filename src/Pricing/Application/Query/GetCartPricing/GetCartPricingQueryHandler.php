<?php

declare(strict_types=1);

namespace App\Pricing\Application\Query\GetCartPricing;

use App\Cart\Domain\Repository\CartRepositoryInterface;
use App\Pricing\Application\DTO\CartPriceCalculationResult;
use App\Pricing\Application\Service\CartPricingServiceInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Get Cart Pricing Query Handler.
 *
 * Handles retrieval of cart pricing with applied discounts
 */
#[AsMessageHandler]
final readonly class GetCartPricingQueryHandler
{
    public function __construct(
        private CartRepositoryInterface $cartRepository,
        private CartPricingServiceInterface $cartPricingService
    ) {
    }

    /**
     * @param GetCartPricingQuery $query
     *
     * @return CartPriceCalculationResult
     *
     * @throws \RuntimeException If cart not found
     */
    public function __invoke(GetCartPricingQuery $query): CartPriceCalculationResult
    {
        // Retrieve cart from repository
        $cart = $this->cartRepository->findById($query->cartId);

        if (null === $cart) {
            throw new \RuntimeException(sprintf('Cart with ID "%s" not found', $query->cartId->toString()));
        }

        // Calculate pricing with applied coupons
        return $this->cartPricingService->calculateCartPricing(
            $cart,
            $query->appliedCouponCodes
        );
    }
}
