<?php

declare(strict_types=1);

namespace App\Customer\Application\Command\RemoveLoyaltyTier;

/**
 * Remove Loyalty Tier Command.
 *
 * Removes a tier from a loyalty program.
 */
final readonly class RemoveLoyaltyTierCommand
{
    public function __construct(
        public string $programId,
        public string $tenantId,
        public string $tierId
    ) {
    }
}
