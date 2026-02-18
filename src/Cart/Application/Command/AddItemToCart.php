<?php

declare(strict_types=1);

namespace App\Cart\Application\Command;

/**
 * AddItemToCart Command.
 *
 * Adds a product (with optional variant) to the cart
 *
 * Note: If unitPriceAmount/Currency are not provided,
 * the handler will fetch current price from CartPriceCalculator
 */
final readonly class AddItemToCart
{
    public function __construct(
        public string $cartId,
        public string $productId,
        public ?string $variantId,
        public int $quantity,
        public ?float $unitPriceAmount = null,    // Optional: will fetch from catalog if null
        public ?string $unitPriceCurrency = null,  // Optional: will fetch from catalog if null
    ) {
    }
}
