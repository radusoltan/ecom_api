<?php

declare(strict_types=1);

namespace App\Customer\Application\Command\DeactivateLoyaltyProgram;

/**
 * Deactivate Loyalty Program Command.
 *
 * Deactivates a loyalty program preventing customers from earning/redeeming points.
 */
final readonly class DeactivateLoyaltyProgramCommand
{
    public function __construct(
        public string $programId,
        public string $tenantId
    ) {
    }
}
