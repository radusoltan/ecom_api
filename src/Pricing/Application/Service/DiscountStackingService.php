<?php

declare(strict_types=1);

namespace App\Pricing\Application\Service;

use App\Pricing\Domain\Event\DiscountsStacked;
use App\Pricing\Domain\Model\Promotion;
use App\Pricing\Domain\ValueObject\DiscountApplication;
use App\Pricing\Domain\ValueObject\StackedDiscount;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Enhanced Discount Stacking Service with domain event support.
 *
 * Business Rules:
 * - Maximum 3 promotions can be stacked per order/cart
 * - Priority-based selection (higher priority applied first)
 * - Type-based stacking order: cart_rule -> catalog_rule -> coupon
 * - Within same type, higher priority number applied first
 * - Conflict resolution: same product can't have conflicting discounts
 * - Each discount applies to already-discounted price (sequential application)
 *
 * This service extends the basic PromotionStackingService with:
 * - Rich value objects (StackedDiscount, DiscountApplication)
 * - Domain event emission (DiscountsStacked)
 * - Conflict detection
 * - Enhanced validation
 */
final readonly class DiscountStackingService
{
    private const MAX_STACKABLE_PROMOTIONS = 3;

    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * Calculate final price with stacked discounts and emit domain event.
     *
     * @param Promotion[] $applicablePromotions All applicable promotions (active, valid date, conditions met)
     * @param Money       $originalPrice        Original product/cart price
     * @param TenantId    $tenantId             Tenant context for event
     *
     * @return StackedDiscount Rich value object with complete stacking result
     */
    public function calculateStackedDiscount(
        array $applicablePromotions,
        Money $originalPrice,
        TenantId $tenantId,
    ): StackedDiscount {
        if (empty($applicablePromotions)) {
            return new StackedDiscount(
                originalPrice: $originalPrice,
                finalPrice: $originalPrice,
                totalDiscount: Money::fromScalars(0, $originalPrice->getCurrency()->getCurrencyCode()),
                applications: []
            );
        }

        // Validate stackability
        if (!$this->validateStackability($applicablePromotions)) {
            throw new \DomainException(sprintf('Cannot stack %d promotions. Maximum allowed: %d', count($applicablePromotions), self::MAX_STACKABLE_PROMOTIONS));
        }

        // Sort promotions by stacking priority
        $sorted = $this->sortByStackingPriority($applicablePromotions);

        // Limit to max 3 promotions
        $stackablePromotions = array_slice($sorted, 0, self::MAX_STACKABLE_PROMOTIONS);

        // Detect conflicts
        $this->detectConflicts($stackablePromotions);

        // Apply promotions sequentially
        $applications = $this->applyPromotionsSequentially($stackablePromotions, $originalPrice);

        // Calculate final price and total discount
        $finalPrice = count($applications) > 0
            ? $applications[count($applications) - 1]->priceAfterDiscount()
            : $originalPrice;

        $totalDiscount = $originalPrice->subtract($finalPrice);

        // Create rich value object
        $stackedDiscount = new StackedDiscount(
            originalPrice: $originalPrice,
            finalPrice: $finalPrice,
            totalDiscount: $totalDiscount,
            applications: $applications
        );

        // Emit domain event
        if (count($applications) > 0) {
            $this->emitDiscountsStackedEvent($stackedDiscount, $tenantId);
        }

        return $stackedDiscount;
    }

    /**
     * Apply promotions sequentially and create DiscountApplication objects.
     *
     * @param Promotion[] $promotions
     *
     * @return DiscountApplication[]
     */
    private function applyPromotionsSequentially(array $promotions, Money $startPrice): array
    {
        $applications = [];
        $currentPrice = $startPrice;

        foreach ($promotions as $promotion) {
            $discountAmount = $promotion->discount()->applyTo($currentPrice);
            $priceAfterDiscount = $currentPrice->subtract($discountAmount);

            $application = new DiscountApplication(
                promotionId: $promotion->id(),
                promotionName: $promotion->name(),
                promotionType: $promotion->type(),
                discountAmount: $discountAmount,
                priceAfterDiscount: $priceAfterDiscount
            );

            $applications[] = $application;
            $currentPrice = $priceAfterDiscount;
        }

        return $applications;
    }

    /**
     * Sort promotions by stacking priority order.
     *
     * Priority rules:
     * 1. Type order: cart_rule (first) -> catalog_rule -> coupon (last)
     * 2. Within same type: higher priority number applied first
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
     *
     * @param Promotion[] $promotions
     */
    public function validateStackability(array $promotions): bool
    {
        if (count($promotions) > self::MAX_STACKABLE_PROMOTIONS) {
            return false;
        }

        return true;
    }

    /**
     * Detect conflicts between promotions.
     *
     * Conflict rules:
     * - Multiple coupons cannot be stacked (only one coupon allowed)
     * - Promotions with mutually exclusive conditions
     *
     * @param Promotion[] $promotions
     *
     * @throws \DomainException if conflicts detected
     */
    private function detectConflicts(array $promotions): void
    {
        // Count coupons
        $couponCount = 0;
        foreach ($promotions as $promotion) {
            if ($promotion->type()->isCoupon()) {
                ++$couponCount;
            }
        }

        if ($couponCount > 1) {
            throw new \DomainException('Cannot stack multiple coupon codes. Only one coupon is allowed per order.');
        }

        // Additional conflict detection can be added here:
        // - Check for mutually exclusive product categories
        // - Check for conflicting customer segments
        // - Check for exclusivity flags
    }

    /**
     * Emit DiscountsStacked domain event.
     */
    private function emitDiscountsStackedEvent(StackedDiscount $stackedDiscount, TenantId $tenantId): void
    {
        $appliedPromotionsArray = array_map(
            static fn (DiscountApplication $app) => [
                'promotionId' => $app->promotionId()->toString(),
                'name' => $app->promotionName(),
                'type' => $app->promotionType()->toString(),
                'discountAmount' => $app->discountAmount()->getAmount(),
                'priceAfterDiscount' => $app->priceAfterDiscount()->getAmount(),
            ],
            $stackedDiscount->applications()
        );

        $event = new DiscountsStacked(
            tenantId: $tenantId,
            appliedPromotions: $appliedPromotionsArray,
            originalAmount: $stackedDiscount->originalPrice()->getAmount(),
            finalAmount: $stackedDiscount->finalPrice()->getAmount(),
            totalDiscountAmount: $stackedDiscount->totalDiscount()->getAmount(),
            currency: $stackedDiscount->originalPrice()->getCurrency()->getCurrencyCode(),
            occurredAt: new \DateTimeImmutable()
        );

        $this->eventDispatcher->dispatch($event);
    }

    public function getMaxStackablePromotions(): int
    {
        return self::MAX_STACKABLE_PROMOTIONS;
    }
}
