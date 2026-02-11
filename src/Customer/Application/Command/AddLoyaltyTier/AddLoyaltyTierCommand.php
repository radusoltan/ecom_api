<?php

declare(strict_types=1);

namespace App\Customer\Application\Command\AddLoyaltyTier;

/**
 * Add Loyalty Tier Command.
 *
 * Adds a new tier to a loyalty program.
 */
final readonly class AddLoyaltyTierCommand
{
    public function __construct(
        public string $programId,
        public string $tenantId,
        public string $name,
        public int $threshold,
        public int $discountPercentage,
        public ?int $freeShippingMinOrder,
        public ?string $freeShippingCurrency,
        public int $sortOrder
    ) {
    }
}
