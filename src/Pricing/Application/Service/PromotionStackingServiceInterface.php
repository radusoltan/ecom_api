<?php

declare(strict_types=1);

namespace App\Pricing\Application\Service;

use App\Pricing\Domain\Model\Promotion;
use App\Shared\Domain\ValueObject\Money;

/**
 * Interface for Promotion Stacking Service.
 *
 * Allows for mocking in unit tests while the implementation remains final.
 */
interface PromotionStackingServiceInterface
{
    /**
     * Calculate final price after applying stackable promotions.
     *
     * @param Promotion[] $applicablePromotions All applicable promotions (active, valid date, conditions met)
     * @param Money       $originalPrice        Original product/cart price
     *
     * @return array{finalPrice: Money, appliedPromotions: array<int, mixed>, totalDiscount: Money}
     */
    public function calculatePriceWithPromotions(array $applicablePromotions, Money $originalPrice): array;

    /**
     * Check if promotions can be stacked together.
     *
     * @param Promotion[] $promotions Array of promotions to check
     *
     * @return bool True if promotions can be stacked
     */
    public function canStack(array $promotions): bool;
}
