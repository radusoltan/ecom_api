<?php

declare(strict_types=1);

namespace App\Customer\Application\Command\UpdateLoyaltyProgram;

/**
 * Update Loyalty Program Command.
 *
 * Updates the core settings of a loyalty program.
 */
final readonly class UpdateLoyaltyProgramCommand
{
    public function __construct(
        public string $programId,
        public string $tenantId,
        public string $name,
        public ?string $description,
        public float $earningRate,
        public int $minOrderValue,
        public string $minOrderCurrency,
        public ?int $validityDays,
    ) {
    }
}
