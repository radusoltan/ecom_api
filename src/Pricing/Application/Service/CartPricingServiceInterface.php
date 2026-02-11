<?php

declare(strict_types=1);

namespace App\Pricing\Application\Service;

use App\Cart\Domain\Model\Cart;
use App\Pricing\Application\DTO\CartPriceCalculationResult;

/**
 * Interface for Cart Pricing Service.
 *
 * Allows for mocking in unit tests while the implementation remains final.
 */
interface CartPricingServiceInterface
{
    /**
     * Calculate complete pricing for a cart.
     *
     * @param Cart          $cart        The cart to calculate pricing for
     * @param array<string> $couponCodes Optional coupon codes to apply
     *
     * @return CartPriceCalculationResult Complete pricing breakdown
     */
    public function calculateCartPricing(Cart $cart, array $couponCodes = []): CartPriceCalculationResult;
}
