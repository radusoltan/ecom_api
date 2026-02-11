<?php

declare(strict_types=1);

namespace App\Customer\Application\Query\CalculateRedemption;

/**
 * Query to preview redemption calculation without actually redeeming points.
 *
 * Useful for showing customers how much discount they would get
 * before they commit to redeeming points.
 */
final readonly class CalculateRedemptionQuery
{
    public function __construct(
        public string $customerId,
        public string $tenantId,
        public int $pointsToRedeem
    ) {
    }
}
