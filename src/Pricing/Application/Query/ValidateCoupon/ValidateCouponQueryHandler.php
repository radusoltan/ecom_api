<?php

declare(strict_types=1);

namespace App\Pricing\Application\Query\ValidateCoupon;

use App\Pricing\Application\DTO\CouponValidationResult;
use App\Pricing\Domain\Repository\PromotionRepositoryInterface;
use App\Shared\Domain\ValueObject\Money;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ValidateCouponQueryHandler
{
    public function __construct(
        private PromotionRepositoryInterface $promotionRepository
    ) {
    }

    public function __invoke(ValidateCouponQuery $query): CouponValidationResult
    {
        // Find promotion by coupon code
        $promotion = $this->promotionRepository->findByCouponCode($query->couponCode, $query->tenantId);

        // Coupon doesn't exist
        if (null === $promotion) {
            return CouponValidationResult::invalid('Coupon code not found');
        }

        // Check if promotion is active
        if (!$promotion->isActive()) {
            return CouponValidationResult::invalid('Coupon is not active');
        }

        // Check validity period
        $now = new \DateTimeImmutable();
        if (!$promotion->isValidAt($now)) {
            if (null !== $promotion->validFrom() && $now < $promotion->validFrom()) {
                return CouponValidationResult::invalid(
                    sprintf('Coupon is not yet valid. Valid from: %s', $promotion->validFrom()->format('Y-m-d H:i:s'))
                );
            }

            if (null !== $promotion->validTo() && $now > $promotion->validTo()) {
                return CouponValidationResult::invalid('Coupon has expired');
            }
        }

        // Check minimum purchase requirement
        $conditions = $promotion->conditions();
        if (isset($conditions['min_purchase_amount'])) {
            $minPurchase = Money::of((string) $conditions['min_purchase_amount'], $query->cartTotal->getCurrency()->getCurrencyCode());

            if ($query->cartTotal->isLessThan($minPurchase)) {
                return CouponValidationResult::invalid(
                    sprintf('Minimum purchase amount of %s required', (string) $minPurchase)
                );
            }
        }

        // Check maximum discount limit
        $discountAmount = $promotion->discount()->applyTo($query->cartTotal);
        if (isset($conditions['max_discount_amount'])) {
            $maxDiscount = Money::of((string) $conditions['max_discount_amount'], $query->cartTotal->getCurrency()->getCurrencyCode());

            if ($discountAmount->isGreaterThan($maxDiscount)) {
                $discountAmount = $maxDiscount;
            }
        }

        // Check usage limits (if implemented in conditions)
        if (isset($conditions['max_uses']) && isset($conditions['current_uses'])) {
            if ($conditions['current_uses'] >= $conditions['max_uses']) {
                return CouponValidationResult::invalid('Coupon usage limit has been reached');
            }
        }

        // Coupon is valid
        return CouponValidationResult::valid(
            promotion: $promotion,
            discountAmount: $discountAmount,
            message: sprintf('Coupon "%s" applied successfully. You save %s', $query->couponCode->toString(), (string) $discountAmount)
        );
    }
}
