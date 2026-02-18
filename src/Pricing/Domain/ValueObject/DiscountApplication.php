<?php

declare(strict_types=1);

namespace App\Pricing\Domain\ValueObject;

use App\Shared\Domain\ValueObject\Money;

/**
 * Represents a single discount application in a stacking sequence.
 *
 * Tracks which promotion was applied, how much discount it provided,
 * and the resulting price after that specific discount.
 */
final readonly class DiscountApplication
{
    public function __construct(
        private PromotionId $promotionId,
        private string $promotionName,
        private PromotionType $promotionType,
        private Money $discountAmount,
        private Money $priceAfterDiscount,
    ) {
        if ($this->discountAmount->isNegative()) {
            throw new \InvalidArgumentException('Discount amount cannot be negative');
        }

        if ($this->priceAfterDiscount->isNegative()) {
            throw new \InvalidArgumentException('Price after discount cannot be negative');
        }
    }

    public function promotionId(): PromotionId
    {
        return $this->promotionId;
    }

    public function promotionName(): string
    {
        return $this->promotionName;
    }

    public function promotionType(): PromotionType
    {
        return $this->promotionType;
    }

    public function discountAmount(): Money
    {
        return $this->discountAmount;
    }

    public function priceAfterDiscount(): Money
    {
        return $this->priceAfterDiscount;
    }

    /**
     * Convert to array for API responses or event payloads.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'promotionId' => $this->promotionId->toString(),
            'promotionName' => $this->promotionName,
            'promotionType' => $this->promotionType->toString(),
            'discountAmount' => $this->discountAmount->toArray(),
            'priceAfterDiscount' => $this->priceAfterDiscount->toArray(),
        ];
    }
}
