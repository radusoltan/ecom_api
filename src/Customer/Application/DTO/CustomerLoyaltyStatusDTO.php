<?php

declare(strict_types=1);

namespace App\Customer\Application\DTO;

/**
 * DTO for comprehensive customer loyalty status.
 *
 * Contains all information about a customer's loyalty program participation.
 */
final readonly class CustomerLoyaltyStatusDTO
{
    public function __construct(
        public string $customerId,
        public int $currentPoints,
        public ?string $currentTier,
        public ?string $nextTier,
        public ?int $pointsToNextTier,
        public int $lifetimePointsEarned,
        public int $lifetimePointsRedeemed,
        public ?int $currentTierDiscount,
        public ?string $programName,
        public bool $programIsActive,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'customer_id' => $this->customerId,
            'current_points' => $this->currentPoints,
            'current_tier' => $this->currentTier,
            'next_tier' => $this->nextTier,
            'points_to_next_tier' => $this->pointsToNextTier,
            'lifetime_points_earned' => $this->lifetimePointsEarned,
            'lifetime_points_redeemed' => $this->lifetimePointsRedeemed,
            'current_tier_discount' => $this->currentTierDiscount,
            'program_name' => $this->programName,
            'program_is_active' => $this->programIsActive,
        ];
    }
}
