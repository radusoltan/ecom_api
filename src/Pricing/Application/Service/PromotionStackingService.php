<?php

declare(strict_types=1);

namespace App\Pricing\Application\Service;

use App\Pricing\Domain\Model\Promotion;
use App\Shared\Domain\ValueObject\Money;

/**
 * Promotion Stacking Service.
 *
 * Business Rules:
 * - Maximum 3 promotions can be stacked
 * - Promotions are applied in priority order (highest first)
 * - Each promotion is applied to the already-discounted price
 * - Discount cannot exceed original price
 *
 * Stacking Priority Order (as per PRD Section 2.2):
 * 1. cart_rule (applied first)
 * 2. catalog_rule (applied second)
 * 3. coupon (applied last)
 */
final class PromotionStackingService
{
    private const MAX_STACKABLE_PROMOTIONS = 3;

    /**
     * Calculate final price after applying stackable promotions.
     *
     * @param Promotion[] $applicablePromotions All applicable promotions (active, valid date, conditions met)
     * @param Money       $originalPrice        Original product/cart price
     *
     * @return array{finalPrice: Money, appliedPromotions: array, totalDiscount: Money}
     */
    public function calculatePriceWithPromotions(array $applicablePromotions, Money $originalPrice): array
    {
        if (empty($applicablePromotions)) {
            return [
                'finalPrice' => $originalPrice,
                'appliedPromotions' => [],
                'totalDiscount' => Money::fromScalars(0, $originalPrice->getCurrency()->getCurrencyCode()),
            ];
        }

        // Sort promotions by priority order: cart_rule -> catalog_rule -> coupon
        $sorted = $this->sortByStackingPriority($applicablePromotions);

        // Limit to max 3 promotions
        $stackablePromotions = array_slice($sorted, 0, self::MAX_STACKABLE_PROMOTIONS);

        // Apply promotions sequentially
        $currentPrice = $originalPrice;
        $appliedPromotions = [];

        foreach ($stackablePromotions as $promotion) {
            $discountAmount = $promotion->discount()->applyTo($currentPrice);
            $currentPrice = $currentPrice->subtract($discountAmount);

            $appliedPromotions[] = [
                'promotionId' => $promotion->id()->toString(),
                'name' => $promotion->name(),
                'type' => $promotion->type()->toString(),
                'discountAmount' => $discountAmount->getAmount(),
                'priceAfterDiscount' => $currentPrice->getAmount(),
            ];
        }

        $totalDiscount = $originalPrice->subtract($currentPrice);

        return [
            'finalPrice' => $currentPrice,
            'appliedPromotions' => $appliedPromotions,
            'totalDiscount' => $totalDiscount,
        ];
    }

    /**
     * Sort promotions by stacking priority order.
     * Priority: cart_rule (first) -> catalog_rule -> coupon (last)
     * Within same type, sort by priority number (higher first).
     *
     * @param Promotion[] $promotions
     *
     * @return Promotion[]
     */
    private function sortByStackingPriority(array $promotions): array
    {
        $typeOrder = [
            'cart_rule' => 1,
            'catalog_rule' => 2,
            'coupon' => 3,
        ];

        usort($promotions, static function (Promotion $a, Promotion $b) use ($typeOrder): int {
            $typeA = $typeOrder[$a->type()->toString()] ?? 999;
            $typeB = $typeOrder[$b->type()->toString()] ?? 999;

            // First, sort by type order
            if ($typeA !== $typeB) {
                return $typeA <=> $typeB;
            }

            // Within same type, sort by priority (higher first)
            return $b->priority() <=> $a->priority();
        });

        return $promotions;
    }

    /**
     * Validate if promotions can be stacked together.
     */
    public function validateStackability(array $promotions): bool
    {
        if (count($promotions) > self::MAX_STACKABLE_PROMOTIONS) {
            return false;
        }

        // Additional validation logic can be added here
        // e.g., check for conflicting promotions, exclusivity rules, etc.

        return true;
    }

    public function getMaxStackablePromotions(): int
    {
        return self::MAX_STACKABLE_PROMOTIONS;
    }
}
