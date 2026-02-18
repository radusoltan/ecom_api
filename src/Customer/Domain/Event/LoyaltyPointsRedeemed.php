<?php

declare(strict_types=1);

namespace App\Customer\Domain\Event;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\Money;

/**
 * Domain Event: Loyalty Points Redeemed.
 *
 * Emitted when a customer redeems loyalty points for a discount.
 */
final readonly class LoyaltyPointsRedeemed
{
    public function __construct(
        public CustomerId $customerId,
        public int $pointsRedeemed,
        public int $remainingBalance,
        public Money $discountAmount,
        public ?string $orderId,
        public \DateTimeImmutable $occurredOn,
    ) {
    }
}
