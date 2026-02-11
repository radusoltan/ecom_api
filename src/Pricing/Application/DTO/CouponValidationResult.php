<?php

declare(strict_types=1);

namespace App\Pricing\Application\DTO;

use App\Pricing\Domain\Model\Promotion;
use App\Shared\Domain\ValueObject\Money;

/**
 * DTO for coupon validation result.
 * Contains validation status, discount information, and user-friendly messages.
 */
final readonly class CouponValidationResult
{
    /**
     * @param array<string, mixed>|null $discountAmount
     */
    private function __construct(
        public bool $valid,
        public ?PromotionDTO $promotion,
        public ?array $discountAmount,
        public string $message
    ) {
    }

    public static function valid(Promotion $promotion, Money $discountAmount, string $message): self
    {
        return new self(
            valid: true,
            promotion: PromotionDTO::fromDomain($promotion),
            discountAmount: $discountAmount->toArray(),
            message: $message
        );
    }

    public static function invalid(string $message): self
    {
        return new self(
            valid: false,
            promotion: null,
            discountAmount: null,
            message: $message
        );
    }
}
