<?php

declare(strict_types=1);

namespace App\Customer\Application\Command\UpdateLoyaltyTier;

/**
 * Update Loyalty Tier Command.
 *
 * Updates an existing tier in a loyalty program.
 */
final readonly class UpdateLoyaltyTierCommand
{
    public function __construct(
        public string $programId,
        public string $tenantId,
        public string $tierId,
        public string $name,
        public int $threshold,
        public int $discountPercentage,
        public ?int $freeShippingMinOrder,
        public ?string $freeShippingCurrency,
        public int $sortOrder,
    ) {
    }
}
