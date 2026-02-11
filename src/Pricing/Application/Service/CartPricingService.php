<?php

declare(strict_types=1);

namespace App\Pricing\Application\Service;

use App\Cart\Domain\Model\Cart;
use App\Cart\Domain\Model\CartItem;
use App\Pricing\Application\DTO\AppliedDiscountDTO;
use App\Pricing\Application\DTO\CartItemPricing;
use App\Pricing\Application\DTO\CartPriceCalculationResult;
use App\Pricing\Domain\Model\PriceList;
use App\Pricing\Domain\Model\Promotion;
use App\Pricing\Domain\Repository\PriceListRepositoryInterface;
use App\Pricing\Domain\Repository\PromotionRepositoryInterface;
use App\Shared\Domain\ValueObject\Money;

/**
 * Cart Pricing Service.
 *
 * Responsibilities:
 * - Apply active price lists to cart items
 * - Apply promotions (cart_rule, catalog_rule)
 * - Calculate final prices with discounts
 * - Return detailed breakdown of applied discounts
 *
 * Integration Points (ACL Pattern):
 * - Cart context: Receives Cart aggregate (read-only)
 * - Pricing context: Uses PriceList and Promotion repositories
 *
 * Business Rules:
 * - Price lists apply first (catalog-level discounts)
 * - Promotions apply after price lists
 * - Cart-level promotions apply to entire cart
 * - Item-level promotions apply to specific items
 * - Promotion stacking is handled by PromotionStackingService
 */
final readonly class CartPricingService implements CartPricingServiceInterface
{
    public function __construct(
        private PriceListRepositoryInterface $priceListRepository,
        private PromotionRepositoryInterface $promotionRepository,
        // private PromotionStackingServiceInterface $promotionStackingService // TODO: Implement promotion stacking
    ) {
    }

    /**
     * Calculate complete pricing for a cart.
     *
     * @param Cart                 $cart              The cart to calculate pricing for
     * @param array<string>        $appliedCouponCodes Coupon codes to apply
     *
     * @return CartPriceCalculationResult Complete pricing breakdown
     */
    public function calculateCartPricing(
        Cart $cart,
        array $appliedCouponCodes = []
    ): CartPriceCalculationResult {
        if ($cart->isEmpty()) {
            $currency = 'USD'; // Default currency for empty cart

            return new CartPriceCalculationResult(
                cartId: $cart->id()->toString(),
                items: [],
                subtotal: Money::fromScalars(0, $currency),
                totalDiscounts: Money::fromScalars(0, $currency),
                grandTotal: Money::fromScalars(0, $currency),
                cartLevelDiscounts: [],
                totalItemsCount: 0
            );
        }

        // Step 1: Get active price lists (ordered by priority)
        $activePriceLists = $this->priceListRepository->findActiveByTenantId(
            $cart->tenantId(),
            new \DateTimeImmutable()
        );

        // Step 2: Get active promotions
        $activePromotions = $this->promotionRepository->findActiveByTenantId(
            $cart->tenantId(),
            new \DateTimeImmutable()
        );

        // Step 3: Filter promotions by applied coupons
        $applicablePromotions = $this->filterApplicablePromotions(
            $activePromotions,
            $appliedCouponCodes
        );

        // Step 4: Calculate pricing for each cart item
        $itemPricings = [];
        $currency = $cart->items()[0]->unitPrice()->getCurrency()->getCurrencyCode();

        foreach ($cart->items() as $cartItem) {
            $itemPricings[] = $this->calculateItemPricing(
                $cartItem,
                $activePriceLists,
                $applicablePromotions
            );
        }

        // Step 5: Calculate subtotal (sum of all item row totals)
        $subtotal = Money::fromScalars(0, $currency);
        foreach ($itemPricings as $itemPricing) {
            $subtotal = $subtotal->add($itemPricing->rowTotal);
        }

        // Step 6: Apply cart-level promotions
        $cartLevelResult = $this->applyCartLevelPromotions(
            $subtotal,
            $applicablePromotions,
            $cart
        );

        // Step 7: Calculate totals
        $totalItemDiscounts = Money::fromScalars(0, $currency);
        foreach ($itemPricings as $itemPricing) {
            $totalItemDiscounts = $totalItemDiscounts->add($itemPricing->totalDiscount);
        }

        $totalDiscounts = $totalItemDiscounts->add($cartLevelResult['totalDiscount']);
        $grandTotal = $subtotal->subtract($cartLevelResult['totalDiscount']);

        return new CartPriceCalculationResult(
            cartId: $cart->id()->toString(),
            items: $itemPricings,
            subtotal: $subtotal,
            totalDiscounts: $totalDiscounts,
            grandTotal: $grandTotal,
            cartLevelDiscounts: $cartLevelResult['appliedDiscounts'],
            totalItemsCount: $cart->getItemCount()
        );
    }

    /**
     * Calculate pricing for a single cart item.
     *
     * @param CartItem      $cartItem
     * @param PriceList[]   $priceLists
     * @param Promotion[]   $promotions
     *
     * @return CartItemPricing
     */
    private function calculateItemPricing(
        CartItem $cartItem,
        array $priceLists,
        array $promotions
    ): CartItemPricing {
        $baseUnitPrice = $cartItem->unitPrice();
        $quantity = $cartItem->quantity()->toInt();
        $currency = $baseUnitPrice->getCurrency()->getCurrencyCode();

        $appliedDiscounts = [];
        $currentUnitPrice = $baseUnitPrice;

        // Step 1: Apply price lists (catalog-level discounts)
        foreach ($priceLists as $priceList) {
            $discountedPrice = $priceList->calculatePrice(
                $currentUnitPrice,
                $cartItem->productId(),
                null, // categoryId - TODO: get from product
                $quantity
            );

            if (!$discountedPrice->equals($currentUnitPrice)) {
                $discountAmount = $currentUnitPrice->subtract($discountedPrice);

                $appliedDiscounts[] = new AppliedDiscountDTO(
                    id: $priceList->id()->toString(),
                    type: 'price_list',
                    name: $priceList->name()->value(),
                    amount: $discountAmount,
                    discountType: 'price_list',
                    discountValue: 0.0,
                    scope: 'item',
                    targetItemId: $cartItem->id()->toString()
                );

                $currentUnitPrice = $discountedPrice;
            }
        }

        // Step 2: Apply catalog-rule promotions (item-level)
        $catalogPromotions = array_filter(
            $promotions,
            fn (Promotion $p) => $p->type()->isCatalogRule()
        );

        foreach ($catalogPromotions as $promotion) {
            // Check if promotion conditions match this item
            if ($this->promotionMatchesItem($promotion, $cartItem)) {
                $discountAmount = $promotion->discount()->applyTo($currentUnitPrice);
                $discountedPrice = $currentUnitPrice->subtract($discountAmount);

                if (!$discountAmount->isZero()) {
                    $appliedDiscounts[] = new AppliedDiscountDTO(
                        id: $promotion->id()->toString(),
                        type: 'promotion',
                        name: $promotion->name(),
                        amount: $discountAmount,
                        discountType: $promotion->discount()->type()->toString(),
                        discountValue: $promotion->discount()->value(),
                        scope: 'item',
                        targetItemId: $cartItem->id()->toString()
                    );

                    $currentUnitPrice = $discountedPrice;
                }
            }
        }

        // Step 3: Calculate totals
        $finalUnitPrice = $currentUnitPrice;
        $rowSubtotal = $baseUnitPrice->multiplyBy($quantity);
        $rowTotal = $finalUnitPrice->multiplyBy($quantity);
        $totalDiscount = $rowSubtotal->subtract($rowTotal);

        return new CartItemPricing(
            cartItemId: $cartItem->id()->toString(),
            productId: $cartItem->productId()->toString(),
            variantId: $cartItem->variantId(),
            quantity: $quantity,
            baseUnitPrice: $baseUnitPrice,
            finalUnitPrice: $finalUnitPrice,
            rowSubtotal: $rowSubtotal,
            rowTotal: $rowTotal,
            totalDiscount: $totalDiscount,
            appliedDiscounts: $appliedDiscounts
        );
    }

    /**
     * Apply cart-level promotions.
     *
     * @param Money       $subtotal
     * @param Promotion[] $promotions
     * @param Cart        $cart
     *
     * @return array{totalDiscount: Money, appliedDiscounts: AppliedDiscountDTO[]}
     */
    private function applyCartLevelPromotions(
        Money $subtotal,
        array $promotions,
        Cart $cart
    ): array {
        $currency = $subtotal->getCurrency()->getCurrencyCode();
        $totalDiscount = Money::fromScalars(0, $currency);
        $appliedDiscounts = [];

        // Filter cart-rule promotions
        $cartPromotions = array_filter(
            $promotions,
            fn (Promotion $p) => $p->type()->isCartRule()
        );

        // Check conditions and apply
        foreach ($cartPromotions as $promotion) {
            if ($this->promotionMatchesCart($promotion, $cart, $subtotal)) {
                $discountAmount = $promotion->discount()->applyTo($subtotal);

                $appliedDiscounts[] = new AppliedDiscountDTO(
                    id: $promotion->id()->toString(),
                    type: 'promotion',
                    name: $promotion->name(),
                    amount: $discountAmount,
                    discountType: $promotion->discount()->type()->toString(),
                    discountValue: $promotion->discount()->value(),
                    scope: 'cart',
                    targetItemId: null
                );

                $totalDiscount = $totalDiscount->add($discountAmount);
            }
        }

        return [
            'totalDiscount' => $totalDiscount,
            'appliedDiscounts' => $appliedDiscounts,
        ];
    }

    /**
     * Filter promotions by type and applied coupons.
     *
     * @param Promotion[]   $promotions
     * @param array<string> $appliedCouponCodes
     *
     * @return Promotion[]
     */
    private function filterApplicablePromotions(
        array $promotions,
        array $appliedCouponCodes
    ): array {
        return array_filter($promotions, function (Promotion $promotion) use ($appliedCouponCodes) {
            // Auto-apply promotions (cart_rule, catalog_rule)
            if (!$promotion->requiresCoupon()) {
                return true;
            }

            // Coupon-based promotions (must match applied coupons)
            if ($promotion->couponCode() !== null) {
                return in_array($promotion->couponCode()->toString(), $appliedCouponCodes, true);
            }

            return false;
        });
    }

    /**
     * Check if promotion conditions match cart item.
     */
    private function promotionMatchesItem(Promotion $promotion, CartItem $cartItem): bool
    {
        $conditions = $promotion->conditions();

        // Check product_ids condition
        if (isset($conditions['product_ids']) && is_array($conditions['product_ids'])) {
            if (!in_array($cartItem->productId()->toString(), $conditions['product_ids'], true)) {
                return false;
            }
        }

        // Check min_quantity condition
        if (isset($conditions['min_quantity']) && is_int($conditions['min_quantity'])) {
            if ($cartItem->quantity()->toInt() < $conditions['min_quantity']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if promotion conditions match cart.
     */
    private function promotionMatchesCart(Promotion $promotion, Cart $cart, Money $subtotal): bool
    {
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

        return true;
    }
}
