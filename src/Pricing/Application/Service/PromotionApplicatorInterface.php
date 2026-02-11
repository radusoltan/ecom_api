<?php

declare(strict_types=1);

namespace App\Pricing\Application\Service;

use App\Cart\Domain\Model\Cart;
use App\Pricing\Application\DTO\AppliedDiscountDTO;
use App\Pricing\Domain\Model\Promotion;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Interface for Promotion Applicator Service.
 *
 * Allows for mocking in unit tests while the implementation remains final.
 */
interface PromotionApplicatorInterface
{
    /**
     * Validate and retrieve promotion by coupon code.
     *
     * @param string   $couponCode Coupon code to validate
     * @param TenantId $tenantId   Tenant context
     *
     * @return Promotion The validated promotion
     *
     * @throws \InvalidArgumentException If coupon is invalid
     */
    public function validateCoupon(string $couponCode, TenantId $tenantId): Promotion;

    /**
     * Check if promotion can be applied to cart.
     *
     * @param Promotion $promotion The promotion to check
     * @param Cart      $cart      The cart to check against
     * @param Money     $subtotal  Current cart subtotal
     *
     * @return bool True if promotion can be applied
     */
    public function canApplyToCart(Promotion $promotion, Cart $cart, Money $subtotal): bool;

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
    public function applyPromotion(Promotion $promotion, Cart $cart, Money $subtotal): AppliedDiscountDTO;

    /**
     * Get discount amount for a promotion without applying it.
     *
     * @param Promotion $promotion The promotion to calculate discount for
     * @param Money     $amount    The amount to apply promotion to
     *
     * @return Money The discount amount
     */
    public function calculateDiscount(Promotion $promotion, Money $amount): Money;

    /**
     * Validate multiple coupons for stacking.
     *
     * @param array<string> $couponCodes Array of coupon codes
     * @param TenantId      $tenantId    Tenant context
     *
     * @return array<Promotion> Array of validated promotions
     *
     * @throws \InvalidArgumentException If any coupon is invalid or stacking is not allowed
     */
    public function validateCouponStack(array $couponCodes, TenantId $tenantId): array;
}
