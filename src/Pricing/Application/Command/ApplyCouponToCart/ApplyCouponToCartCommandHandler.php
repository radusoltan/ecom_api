<?php

declare(strict_types=1);

namespace App\Pricing\Application\Command\ApplyCouponToCart;

use App\Cart\Domain\Repository\CartRepositoryInterface;
use App\Pricing\Application\DTO\AppliedDiscountDTO;
use App\Pricing\Application\Service\PromotionApplicatorInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Apply Coupon to Cart Command Handler.
 *
 * Handles validation and application of coupon codes to carts
 */
#[AsMessageHandler]
final readonly class ApplyCouponToCartCommandHandler
{
    public function __construct(
        private CartRepositoryInterface $cartRepository,
        private PromotionApplicatorInterface $promotionApplicator,
    ) {
    }

    /**
     * @return AppliedDiscountDTO Details of the applied discount
     *
     * @throws \RuntimeException         If cart not found
     * @throws \InvalidArgumentException If coupon is invalid or cannot be applied
     */
    public function __invoke(ApplyCouponToCartCommand $command): AppliedDiscountDTO
    {
        // Retrieve cart from repository
        $cart = $this->cartRepository->findById($command->cartId);

        if (null === $cart) {
            throw new \RuntimeException(sprintf('Cart with ID "%s" not found', $command->cartId->toString()));
        }

        // Validate coupon code
        $promotion = $this->promotionApplicator->validateCoupon(
            $command->couponCode,
            $command->tenantId
        );

        // Calculate cart subtotal
        $subtotal = $cart->calculateTotal();

        // Apply promotion and return discount details
        return $this->promotionApplicator->applyPromotion(
            $promotion,
            $cart,
            $subtotal
        );
    }
}
