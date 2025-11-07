<?php

declare(strict_types=1);

namespace App\Pricing\Application\Service;

use App\Pricing\Domain\Model\Promotion;
use App\Pricing\Domain\Repository\PromotionRepositoryInterface;
use App\Pricing\Domain\ValueObject\CouponCode;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use Psr\Log\LoggerInterface;

/**
 * Promotion Application Service.
 *
 * Orchestrates promotion application logic for cart/order checkout.
 *
 * Responsibilities:
 * - Find applicable promotions (active, valid dates, conditions met)
 * - Validate coupon codes
 * - Delegate to PromotionStackingService for discount calculation
 * - Return final price and applied promotions details
 */
final readonly class PromotionApplicationService
{
    public function __construct(
        private PromotionRepositoryInterface $promotionRepository,
        private PromotionStackingService $stackingService,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Apply promotions to cart/order.
     *
     * @param TenantId    $tenantId   Tenant context
     * @param Money       $subtotal   Cart/order subtotal before discounts
     * @param string|null $couponCode Optional coupon code provided by customer
     * @param array       $context    Additional context for condition evaluation (customer segment, product categories, etc.)
     *
     * @return array{finalPrice: Money, appliedPromotions: array, totalDiscount: Money, couponCode: string|null}
     */
    public function applyPromotions(
        TenantId $tenantId,
        Money $subtotal,
        ?string $couponCode = null,
        array $context = []
    ): array {
        $applicablePromotions = [];
        $now = new \DateTimeImmutable();

        // 1. Find all active promotions for tenant
        $activePromotions = $this->promotionRepository->findActivePromotions($tenantId);

        // 2. Filter by validity dates and conditions
        foreach ($activePromotions as $promotion) {
            if (!$promotion->isApplicable($now)) {
                continue;
            }

            // Check if promotion conditions are met
            if (!$this->meetsConditions($promotion, $subtotal, $context)) {
                continue;
            }

            // Add cart_rule and catalog_rule promotions automatically
            if ($promotion->type()->isCartRule() || $promotion->type()->isCatalogRule()) {
                $applicablePromotions[] = $promotion;
            }
        }

        // 3. Validate and add coupon if provided
        if (null !== $couponCode) {
            $validatedCoupon = $this->validateCoupon($tenantId, $couponCode, $subtotal, $context, $now);
            if (null !== $validatedCoupon) {
                $applicablePromotions[] = $validatedCoupon;
            }
        }

        // 4. Apply stacking logic to calculate final price
        $result = $this->stackingService->calculatePriceWithPromotions($applicablePromotions, $subtotal);

        // 5. Return result with coupon code reference
        return [
            'finalPrice' => $result['finalPrice'],
            'appliedPromotions' => $result['appliedPromotions'],
            'totalDiscount' => $result['totalDiscount'],
            'couponCode' => $couponCode,
        ];
    }

    /**
     * Validate coupon code and return promotion if valid.
     */
    private function validateCoupon(
        TenantId $tenantId,
        string $couponCode,
        Money $subtotal,
        array $context,
        \DateTimeImmutable $now
    ): ?Promotion {
        try {
            $code = CouponCode::fromString($couponCode);
        } catch (\InvalidArgumentException $e) {
            // Invalid coupon format
            return null;
        }

        $promotion = $this->promotionRepository->findByCouponCode($code, $tenantId);

        if (null === $promotion) {
            // Coupon not found
            return null;
        }

        if (!$promotion->isActive()) {
            // Coupon is not active
            return null;
        }

        if (!$promotion->isApplicable($now)) {
            // Coupon is expired or not yet valid
            return null;
        }

        if (!$this->meetsConditions($promotion, $subtotal, $context)) {
            // Coupon conditions not met
            return null;
        }

        return $promotion;
    }

    /**
     * Check if promotion conditions are met.
     *
     * Conditions structure (stored in JSON):
     * {
     *   "min_purchase": 50.00,
     *   "customer_segments": ["vip", "premium"],
     *   "product_categories": ["electronics", "clothing"],
     *   "min_quantity": 3,
     *   "exclude_sale_items": true
     * }
     */
    private function meetsConditions(Promotion $promotion, Money $subtotal, array $context): bool
    {
        $conditions = $promotion->conditions();

        if (empty($conditions)) {
            // No conditions, always applicable
            return true;
        }

        // Check minimum purchase amount
        if (isset($conditions['min_purchase'])) {
            $minPurchase = Money::fromScalars((int) ($conditions['min_purchase'] * 100), $subtotal->getCurrency()->getCurrencyCode());
            if ($subtotal->isLessThan($minPurchase)) {
                return false;
            }
        }

        // Check minimum cart value (alias for min_purchase)
        if (isset($conditions['min_cart_value'])) {
            $minCartValue = Money::fromScalars((int) ($conditions['min_cart_value'] * 100), $subtotal->getCurrency()->getCurrencyCode());
            if ($subtotal->isLessThan($minCartValue)) {
                return false;
            }
        }

        // Check customer segments
        if (isset($conditions['customer_segments']) && !empty($conditions['customer_segments'])) {
            $customerSegment = $context['customer_segment'] ?? null;
            if (null === $customerSegment || !in_array($customerSegment, $conditions['customer_segments'], true)) {
                return false;
            }
        }

        // Check product categories
        if (isset($conditions['product_categories']) && !empty($conditions['product_categories'])) {
            $cartCategories = $context['product_categories'] ?? [];
            if (empty(array_intersect($cartCategories, $conditions['product_categories']))) {
                return false;
            }
        }

        // Check minimum quantity
        if (isset($conditions['min_quantity'])) {
            $totalQuantity = $context['total_quantity'] ?? 0;
            if ($totalQuantity < $conditions['min_quantity']) {
                return false;
            }
        }

        // Check exclude sale items
        if (isset($conditions['exclude_sale_items']) && true === $conditions['exclude_sale_items']) {
            $hasSaleItems = $context['has_sale_items'] ?? false;
            if ($hasSaleItems) {
                return false;
            }
        }

        return true;
    }
}
