<?php

declare(strict_types=1);

namespace App\Pricing\Application\DTO;

use App\Shared\Domain\ValueObject\Money;

/**
 * Cart Item Pricing DTO.
 *
 * Contains pricing details for a single cart item
 */
final readonly class CartItemPricing
{
    /**
     * @param AppliedDiscountDTO[] $appliedDiscounts
     */
    public function __construct(
        public string $cartItemId,
        public string $productId,
        public ?string $variantId,
        public int $quantity,
        public Money $baseUnitPrice,
        public Money $finalUnitPrice,
        public Money $rowSubtotal,
        public Money $rowTotal,
        public Money $totalDiscount,
        public array $appliedDiscounts = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'cart_item_id' => $this->cartItemId,
            'product_id' => $this->productId,
            'variant_id' => $this->variantId,
            'quantity' => $this->quantity,
            'base_unit_price' => [
                'value' => $this->baseUnitPrice->getAmount(),
                'currency' => $this->baseUnitPrice->getCurrency()->getCurrencyCode(),
            ],
            'final_unit_price' => [
                'value' => $this->finalUnitPrice->getAmount(),
                'currency' => $this->finalUnitPrice->getCurrency()->getCurrencyCode(),
            ],
            'row_subtotal' => [
                'value' => $this->rowSubtotal->getAmount(),
                'currency' => $this->rowSubtotal->getCurrency()->getCurrencyCode(),
            ],
            'row_total' => [
                'value' => $this->rowTotal->getAmount(),
                'currency' => $this->rowTotal->getCurrency()->getCurrencyCode(),
            ],
            'total_discount' => [
                'value' => $this->totalDiscount->getAmount(),
                'currency' => $this->totalDiscount->getCurrency()->getCurrencyCode(),
            ],
            'applied_discounts' => array_map(
                fn (AppliedDiscountDTO $discount) => $discount->toArray(),
                $this->appliedDiscounts
            ),
        ];
    }
}
