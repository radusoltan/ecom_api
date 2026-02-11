<?php

declare(strict_types=1);

namespace App\Pricing\Application\Command\ApplyCouponToCart;

use App\Cart\Domain\Model\CartId;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Apply Coupon to Cart Command.
 *
 * Validates and applies a coupon code to a cart
 */
final readonly class ApplyCouponToCartCommand
{
    public function __construct(
        public CartId $cartId,
        public TenantId $tenantId,
        public string $couponCode
    ) {
    }
}
