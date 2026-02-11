<?php

declare(strict_types=1);

namespace App\Pricing\Application\DTO;

use App\Shared\Domain\ValueObject\Money;

/**
 * Cart Price Calculation Result DTO.
 *
 * Contains complete pricing breakdown for a cart
 */
final readonly class CartPriceCalculationResult
{
    /**
     * @param CartItemPricing[]    $items
     * @param AppliedDiscountDTO[] $cartLevelDiscounts
     */
    public function __construct(
        public string $cartId,
        public array $items,
        public Money $subtotal,
        public Money $totalDiscounts,
        public Money $grandTotal,
        public array $cartLevelDiscounts = [],
        public int $totalItemsCount = 0
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'cart_id' => $this->cartId,
            'items' => array_map(
                fn (CartItemPricing $item) => $item->toArray(),
                $this->items
            ),
            'subtotal' => [
                'value' => $this->subtotal->getAmount(),
                'currency' => $this->subtotal->getCurrency()->getCurrencyCode(),
            ],
            'total_discounts' => [
                'value' => $this->totalDiscounts->getAmount(),
                'currency' => $this->totalDiscounts->getCurrency()->getCurrencyCode(),
            ],
            'grand_total' => [
                'value' => $this->grandTotal->getAmount(),
                'currency' => $this->grandTotal->getCurrency()->getCurrencyCode(),
            ],
            'cart_level_discounts' => array_map(
                fn (AppliedDiscountDTO $discount) => $discount->toArray(),
                $this->cartLevelDiscounts
            ),
            'total_items_count' => $this->totalItemsCount,
        ];
    }
}
