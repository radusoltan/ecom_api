<?php

declare(strict_types=1);

namespace App\Pricing\Application\Service;

use App\Cart\Domain\Model\Cart;
use App\Pricing\Application\DTO\AppliedDiscountDTO;
use App\Pricing\Domain\Model\Promotion;
use App\Pricing\Domain\Repository\PromotionRepositoryInterface;
use App\Pricing\Domain\ValueObject\CouponCode;
use App\Shared\Domain\ValueObject\Money;

/**
 * Promotion Applicator Service.
 *
 * Responsibilities:
 * - Validate promotion applicability
 * - Apply promotion to cart
 * - Validate coupon codes
 * - Check promotion conditions (min_purchase, min_quantity, etc.)
 *
 * Business Rules:
 * - Coupon codes are case-insensitive
 * - Promotion must be active and within valid date range
 * - Conditions must be satisfied (min_purchase, min_items_count, etc.)
 * - Multiple promotions can stack (handled by PromotionStackingService)
 */
final readonly class PromotionApplicator implements PromotionApplicatorInterface
{
    public function __construct(
        private PromotionRepositoryInterface $promotionRepository,
        private PromotionStackingServiceInterface $promotionStackingService,
    ) {
    }

    /**
     * Validate and retrieve promotion by coupon code.
     *
     * @param string                                  $couponCode Coupon code to validate
     * @param \App\Shared\Domain\ValueObject\TenantId $tenantId   Tenant context
     *
     * @return Promotion The validated promotion
     *
     * @throws \InvalidArgumentException If coupon is invalid
     */
    public function validateCoupon(
        string $couponCode,
        \App\Shared\Domain\ValueObject\TenantId $tenantId,
    ): Promotion {
        // Find promotion by coupon code
        $promotion = $this->promotionRepository->findByCouponCode(
            CouponCode::fromString($couponCode),
            $tenantId
        );

        if (null === $promotion) {
            throw new \InvalidArgumentException(sprintf('Invalid coupon code: %s', $couponCode));
        }

        // Check if promotion is active
        if (!$promotion->isActive()) {
            throw new \InvalidArgumentException(sprintf('Coupon "%s" is not active', $couponCode));
        }

        // Check if promotion is within valid date range
        $now = new \DateTimeImmutable();
        if (!$promotion->isValidAt($now)) {
            throw new \InvalidArgumentException(sprintf('Coupon "%s" is expired or not yet valid', $couponCode));
        }

        return $promotion;
    }

    /**
     * Check if promotion can be applied to cart.
     *
     * @param Promotion $promotion The promotion to check
     * @param Cart      $cart      The cart to check against
     * @param Money     $subtotal  Current cart subtotal
     *
     * @return bool True if promotion can be applied
     */
    public function canApplyToCart(
        Promotion $promotion,
        Cart $cart,
        Money $subtotal,
    ): bool {
        // Check if promotion is applicable (active + valid dates)
        $now = new \DateTimeImmutable();
        if (!$promotion->isApplicable($now)) {
            return false;
        }

        // Check promotion conditions
        return $this->checkPromotionConditions($promotion, $cart, $subtotal);
    }

    /**
     * Apply promotion to cart and return discount details.
     *
     * @param Promotion $promotion The promotion to apply
     * @param Cart      $cart      The cart to apply promotion to
     * @param Money     $subtotal  Current cart subtotal
     *
     * @return AppliedDiscountDTO The applied discount details
     *
     * @throws \InvalidArgumentException If promotion cannot be applied
     */
    public function applyPromotion(
        Promotion $promotion,
        Cart $cart,
        Money $subtotal,
    ): AppliedDiscountDTO {
        if (!$this->canApplyToCart($promotion, $cart, $subtotal)) {
            throw new \InvalidArgumentException(sprintf('Promotion "%s" cannot be applied to this cart', $promotion->name()));
        }

        // Calculate discount amount
        $discountAmount = $promotion->discount()->applyTo($subtotal);

        return new AppliedDiscountDTO(
            id: $promotion->id()->toString(),
            type: 'promotion',
            name: $promotion->name(),
            amount: $discountAmount,
            discountType: $promotion->discount()->type()->toString(),
            discountValue: $promotion->discount()->value(),
            scope: 'cart',
            targetItemId: null
        );
    }

    /**
     * Get discount amount for a promotion without applying it.
     *
     * @param Promotion $promotion The promotion to calculate discount for
     * @param Money     $amount    The amount to apply promotion to
     *
     * @return Money The discount amount
     */
    public function calculateDiscount(
        Promotion $promotion,
        Money $amount,
    ): Money {
        return $promotion->discount()->applyTo($amount);
    }

    /**
     * Check if promotion conditions are satisfied.
     *
     * @param Promotion $promotion The promotion to check
     * @param Cart      $cart      The cart to check against
     * @param Money     $subtotal  Current cart subtotal
     *
     * @return bool True if all conditions are satisfied
     */
    private function checkPromotionConditions(
        Promotion $promotion,
        Cart $cart,
        Money $subtotal,
    ): bool {
        $conditions = $promotion->conditions();

        // Check min_purchase condition
        if (isset($conditions['min_purchase']) && is_numeric($conditions['min_purchase'])) {
            $minPurchase = Money::fromScalars(
                (int) $conditions['min_purchase'],
                $subtotal->getCurrency()->getCurrencyCode()
            );

            if ($subtotal->isLessThan($minPurchase)) {
                return false;
            }
        }

        // Check min_items_count condition
        if (isset($conditions['min_items_count']) && is_int($conditions['min_items_count'])) {
            if ($cart->getItemCount() < $conditions['min_items_count']) {
                return false;
            }
        }

        // Check max_uses_per_customer condition (TODO: implement after Customer context is ready)
        // if (isset($conditions['max_uses_per_customer']) && $cart->customerId() !== null) {
        //     // Query usage count for this customer
        // }

        // Check product_ids condition (cart must contain at least one of these products)
        if (isset($conditions['product_ids']) && is_array($conditions['product_ids'])) {
            $hasMatchingProduct = false;

            foreach ($cart->items() as $cartItem) {
                if (in_array($cartItem->productId()->toString(), $conditions['product_ids'], true)) {
                    $hasMatchingProduct = true;

                    break;
                }
            }

            if (!$hasMatchingProduct) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate multiple coupons for stacking.
     *
     * @param array<string>                           $couponCodes Array of coupon codes
     * @param \App\Shared\Domain\ValueObject\TenantId $tenantId    Tenant context
     *
     * @return array<Promotion> Array of validated promotions
     *
     * @throws \InvalidArgumentException If any coupon is invalid or stacking is not allowed
     */
    public function validateCouponStack(
        array $couponCodes,
        \App\Shared\Domain\ValueObject\TenantId $tenantId,
    ): array {
        if (empty($couponCodes)) {
            return [];
        }

        // Validate each coupon
        $promotions = [];
        foreach ($couponCodes as $couponCode) {
            $promotions[] = $this->validateCoupon($couponCode, $tenantId);
        }

        // Check if stacking is allowed
        if (count($promotions) > 1) {
            $canStack = $this->promotionStackingService->canStack($promotions);

            if (!$canStack) {
                throw new \InvalidArgumentException('These promotions cannot be stacked together');
            }
        }

        return $promotions;
    }
}
