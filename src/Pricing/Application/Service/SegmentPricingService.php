<?php

declare(strict_types=1);

namespace App\Pricing\Application\Service;

use App\Catalog\Domain\Model\ProductId;
use App\Customer\Domain\ValueObject\CustomerSegment;
use App\Pricing\Domain\Model\PriceList;
use App\Pricing\Domain\Model\Promotion;
use App\Pricing\Domain\Repository\PriceListRepositoryInterface;
use App\Pricing\Domain\Repository\PromotionRepositoryInterface;
use App\Pricing\Domain\ValueObject\SegmentPricingRule;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Segment Pricing Service.
 *
 * Calculates prices based on customer segment with support for segment-specific pricing rules.
 *
 * Priority order:
 * 1. PriceList segment rules (highest priority)
 * 2. PriceList general rules
 * 3. Base price (no discount)
 *
 * Business Rules:
 * - VIP customers: 10-20% better prices than REGULAR
 * - WHOLESALE customers: volume-based bulk pricing
 * - PREMIUM customers: exclusive promotions access
 */
final class SegmentPricingService
{
    public function __construct(
        private readonly PriceListRepositoryInterface $priceListRepository,
        private readonly PromotionRepositoryInterface $promotionRepository
    ) {
    }

    /**
     * Calculate price for a product based on customer segment.
     *
     * Applies segment-specific pricing rules from active price lists.
     *
     * @param Money           $basePrice       The base price of the product
     * @param ProductId       $productId       Product identifier
     * @param CustomerSegment $customerSegment Customer's segment
     * @param TenantId        $tenantId        Tenant identifier
     * @param int             $quantity        Quantity being purchased
     *
     * @return Money The final price after applying segment discounts
     */
    public function calculatePrice(
        Money $basePrice,
        ProductId $productId,
        CustomerSegment $customerSegment,
        TenantId $tenantId,
        int $quantity = 1
    ): Money {
        // Get all active price lists for this tenant, ordered by priority
        $priceLists = $this->priceListRepository->findValidForTenant($tenantId);

        if (empty($priceLists)) {
            return $basePrice;
        }

        // Sort by priority (higher priority first)
        usort($priceLists, fn (PriceList $a, PriceList $b) => $b->priority() <=> $a->priority());

        // Check segment-specific rules first (highest priority)
        foreach ($priceLists as $priceList) {
            $segmentRule = $priceList->getSegmentDiscount($customerSegment);
            if (null !== $segmentRule) {
                return $this->applySegmentRule($basePrice, $segmentRule, $quantity);
            }
        }

        // Fallback to general pricing rules
        foreach ($priceLists as $priceList) {
            $price = $priceList->calculatePrice($basePrice, $productId, null, $quantity);
            if (!$price->equals($basePrice)) {
                return $price;
            }
        }

        // No applicable rules, return base price
        return $basePrice;
    }

    /**
     * Get all promotions applicable to a customer segment.
     *
     * Filters active promotions by:
     * - Tenant ID
     * - Active status
     * - Validity period
     * - Target segments (empty = all segments)
     *
     * @param CustomerSegment $customerSegment Customer's segment
     * @param TenantId        $tenantId        Tenant identifier
     *
     * @return array<Promotion> List of applicable promotions
     */
    public function getApplicablePromotions(
        CustomerSegment $customerSegment,
        TenantId $tenantId
    ): array {
        // Get all active promotions for this tenant
        $allPromotions = $this->promotionRepository->findActivePromotions($tenantId);

        // Filter by segment applicability
        $applicablePromotions = [];
        $now = new \DateTimeImmutable();

        foreach ($allPromotions as $promotion) {
            if ($promotion->isApplicable($now) && $promotion->isApplicableToSegment($customerSegment)) {
                $applicablePromotions[] = $promotion;
            }
        }

        // Sort by priority (higher priority first)
        usort(
            $applicablePromotions,
            fn (Promotion $a, Promotion $b) => $b->priority() <=> $a->priority()
        );

        return $applicablePromotions;
    }

    /**
     * Check if a customer segment has exclusive access to any promotions.
     *
     * @param CustomerSegment $customerSegment Customer's segment
     * @param TenantId        $tenantId        Tenant identifier
     *
     * @return bool True if segment has exclusive promotions
     */
    public function hasExclusivePromotions(
        CustomerSegment $customerSegment,
        TenantId $tenantId
    ): bool {
        $promotions = $this->getApplicablePromotions($customerSegment, $tenantId);

        foreach ($promotions as $promotion) {
            // Check if promotion is exclusive to specific segments
            if (!empty($promotion->targetSegments())) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate the total discount percentage for a customer segment.
     *
     * Sums up all segment-specific discounts across active price lists.
     *
     * @param CustomerSegment $customerSegment Customer's segment
     * @param TenantId        $tenantId        Tenant identifier
     *
     * @return float Total discount percentage (0-100)
     */
    public function calculateTotalSegmentDiscount(
        CustomerSegment $customerSegment,
        TenantId $tenantId
    ): float {
        $priceLists = $this->priceListRepository->findValidForTenant($tenantId);

        if (empty($priceLists)) {
            return 0.0;
        }

        $totalDiscount = 0.0;

        foreach ($priceLists as $priceList) {
            $segmentRule = $priceList->getSegmentDiscount($customerSegment);
            if (null !== $segmentRule && $segmentRule->discount()->type()->isPercentage()) {
                $totalDiscount += $segmentRule->discount()->value();
            }
        }

        // Cap at 100%
        return min($totalDiscount, 100.0);
    }

    /**
     * Apply a segment pricing rule to a base price.
     *
     * @param Money               $basePrice    The base price
     * @param SegmentPricingRule  $rule         The segment rule to apply
     * @param int                 $quantity     Quantity
     *
     * @return Money The price after applying the segment discount
     */
    private function applySegmentRule(
        Money $basePrice,
        SegmentPricingRule $rule,
        int $quantity
    ): Money {
        $totalPrice = $basePrice->multiplyBy($quantity);
        $discountAmount = $rule->discount()->applyTo($totalPrice);

        // Subtract discount from total price
        $finalPrice = $totalPrice->subtract($discountAmount);

        // Divide by quantity to get unit price
        if ($quantity > 1) {
            return Money::fromScalars(
                (int) ($finalPrice->getAmount() / $quantity),
                $finalPrice->getCurrency()->getCurrencyCode()
            );
        }

        return $finalPrice;
    }
}
