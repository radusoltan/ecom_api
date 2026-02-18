<?php

declare(strict_types=1);

namespace App\Cart\Domain\Event;

use App\Cart\Domain\Model\CartId;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Domain event raised when a cart is identified as abandoned.
 *
 * Business Rules:
 * - Cart is considered abandoned after 24 hours of inactivity
 * - Only active carts with items can be abandoned
 * - Only one abandonment email per cart
 * - Cart must have customer email (authenticated or guest)
 */
final readonly class CartAbandoned
{
    public function __construct(
        public CartId $cartId,
        public TenantId $tenantId,
        public ?CustomerId $customerId,
        public string $customerEmail,
        public Money $cartTotal,
        public int $itemCount,
        public \DateTimeImmutable $abandonedAt,
    ) {
    }
}
