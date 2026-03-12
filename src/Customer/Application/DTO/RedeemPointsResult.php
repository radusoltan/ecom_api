<?php

declare(strict_types=1);

namespace App\Customer\Application\DTO;

/**
 * Result DTO for points redemption operation.
 */
final readonly class RedeemPointsResult
{
    /**
     * @param int $discountAmountMinorUnits Discount amount in minor units (cents)
     */
    public function __construct(
        public int $pointsRedeemed,
        public int $discountAmountMinorUnits,
        public string $discountCurrency,
        public int $remainingBalance,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'points_redeemed' => $this->pointsRedeemed,
            'discount_amount' => $this->discountAmountMinorUnits,
            'discount_currency' => $this->discountCurrency,
            'remaining_balance' => $this->remainingBalance,
        ];
    }
}
