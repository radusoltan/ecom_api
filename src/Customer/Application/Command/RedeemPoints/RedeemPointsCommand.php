<?php

declare(strict_types=1);

namespace App\Customer\Application\Command\RedeemPoints;

/**
 * Command to redeem loyalty points for a discount.
 *
 * Business Rules:
 * - Customer must have enough points
 * - Points must be >= program's minimum redemption threshold
 * - Points must be <= program's maximum per order (if set)
 * - Loyalty program must be active
 */
final readonly class RedeemPointsCommand
{
    public function __construct(
        public string $customerId,
        public string $tenantId,
        public int $pointsToRedeem,
        public ?string $orderId = null
    ) {
    }
}
