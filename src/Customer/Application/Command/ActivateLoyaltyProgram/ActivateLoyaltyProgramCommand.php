<?php

declare(strict_types=1);

namespace App\Customer\Application\Command\ActivateLoyaltyProgram;

/**
 * Activate Loyalty Program Command.
 *
 * Activates a loyalty program to allow customers to earn/redeem points.
 */
final readonly class ActivateLoyaltyProgramCommand
{
    public function __construct(
        public string $programId,
        public string $tenantId,
    ) {
    }
}
