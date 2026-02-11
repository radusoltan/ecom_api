<?php

declare(strict_types=1);

namespace App\Pricing\Application\DTO;

use App\Shared\Domain\ValueObject\Money;

/**
 * Applied Discount DTO.
 *
 * Represents a discount that has been applied to a cart item or cart total
 */
final readonly class AppliedDiscountDTO
{
    public function __construct(
        public string $id,
        public string $type, // 'promotion', 'price_list', 'coupon'
        public string $name,
        public Money $amount,
        public string $discountType, // 'percentage', 'fixed_amount'
        public float $discountValue,
        public ?string $scope = null, // 'cart', 'item'
        public ?string $targetItemId = null
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'amount' => [
                'value' => $this->amount->getAmount(),
                'currency' => $this->amount->getCurrency()->getCurrencyCode(),
            ],
            'discount_type' => $this->discountType,
            'discount_value' => $this->discountValue,
            'scope' => $this->scope,
            'target_item_id' => $this->targetItemId,
        ];
    }
}
