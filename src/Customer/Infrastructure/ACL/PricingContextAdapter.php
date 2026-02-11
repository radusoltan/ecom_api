<?php

declare(strict_types=1);

namespace App\Customer\Infrastructure\ACL;

use App\Shared\Domain\ValueObject\Money;

/**
 * Anti-Corruption Layer adapter for Pricing context integration.
 *
 * This adapter provides a clean interface for applying loyalty discounts
 * to order totals, abstracting away any Pricing context implementation details.
 *
 * Purpose:
 * - Protect Customer bounded context from Pricing context changes
 * - Provide domain-friendly interface for discount application
 * - Handle currency conversion if needed
 * - Validate discount application rules
 *
 * Integration Points:
 * - Used by order checkout flow to apply loyalty redemption discounts
 * - Can be extended to integrate with Pricing context's discount engine
 */
final readonly class PricingContextAdapter
{
    /**
     * Apply loyalty discount to order total.
     *
     * Business Rules:
     * - Discount cannot exceed order total (floor at 0)
     * - Both amounts must be in same currency
     * - Negative discounts are not allowed
     *
     * @param Money $orderTotal      Original order total
     * @param Money $loyaltyDiscount Discount amount from redeemed points
     *
     * @return Money Final order total after discount
     *
     * @throws \InvalidArgumentException If currencies don't match or discount is negative
     */
    public function applyLoyaltyDiscount(Money $orderTotal, Money $loyaltyDiscount): Money
    {
        // Validate currency match
        if ($orderTotal->getCurrency()->getCurrencyCode() !== $loyaltyDiscount->getCurrency()->getCurrencyCode()) {
            throw new \InvalidArgumentException(sprintf('Currency mismatch: order total is %s but loyalty discount is %s', $orderTotal->getCurrency()->getCurrencyCode(), $loyaltyDiscount->getCurrency()->getCurrencyCode()));
        }

        // Validate discount is not negative
        if ($loyaltyDiscount->isNegative()) {
            throw new \InvalidArgumentException('Loyalty discount cannot be negative');
        }

        // Validate discount is not zero (would be pointless)
        if ($loyaltyDiscount->isZero()) {
            return $orderTotal;
        }

        // Apply discount
        $finalTotal = $orderTotal->subtract($loyaltyDiscount);

        // Ensure total doesn't go below zero (floor at 0)
        if ($finalTotal->isNegative()) {
            return Money::fromScalars(0, $orderTotal->getCurrency()->getCurrencyCode());
        }

        return $finalTotal;
    }

    /**
     * Calculate maximum applicable discount.
     *
     * Ensures discount doesn't exceed order total.
     *
     * @param Money $orderTotal        Original order total
     * @param Money $requestedDiscount Requested discount amount
     *
     * @return Money Actual discount that can be applied (capped at order total)
     */
    public function calculateMaximumApplicableDiscount(Money $orderTotal, Money $requestedDiscount): Money
    {
        // Validate currency match
        if ($orderTotal->getCurrency()->getCurrencyCode() !== $requestedDiscount->getCurrency()->getCurrencyCode()) {
            throw new \InvalidArgumentException(sprintf('Currency mismatch: order total is %s but requested discount is %s', $orderTotal->getCurrency()->getCurrencyCode(), $requestedDiscount->getCurrency()->getCurrencyCode()));
        }

        // If requested discount exceeds order total, cap it
        if ($requestedDiscount->isGreaterThan($orderTotal)) {
            return $orderTotal;
        }

        return $requestedDiscount;
    }

    /**
     * Validate if discount can be applied to order.
     *
     * @param Money $orderTotal Order total before discount
     * @param Money $discount   Discount to apply
     *
     * @return bool True if discount can be applied
     */
    public function canApplyDiscount(Money $orderTotal, Money $discount): bool
    {
        try {
            // Check currency match
            if ($orderTotal->getCurrency()->getCurrencyCode() !== $discount->getCurrency()->getCurrencyCode()) {
                return false;
            }

            // Check discount is valid
            if ($discount->isNegative() || $discount->isZero()) {
                return false;
            }

            return true;
        } catch (\Exception) {
            return false;
        }
    }
}
