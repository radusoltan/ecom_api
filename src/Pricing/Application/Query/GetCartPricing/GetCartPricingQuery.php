<?php

declare(strict_types=1);

namespace App\Pricing\Application\Query\GetCartPricing;

use App\Cart\Domain\Model\CartId;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Get Cart Pricing Query.
 *
 * Retrieve complete pricing breakdown for a cart including discounts
 */
final readonly class GetCartPricingQuery
{
    /**
     * @param CartId        $cartId            The cart to get pricing for
     * @param TenantId      $tenantId          Tenant context
     * @param array<string> $appliedCouponCodes Coupon codes applied to cart
     */
    public function __construct(
        public CartId $cartId,
        public TenantId $tenantId,
        public array $appliedCouponCodes = []
    ) {
    }
}
