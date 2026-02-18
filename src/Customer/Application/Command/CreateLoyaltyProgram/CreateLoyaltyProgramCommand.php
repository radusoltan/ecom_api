<?php

declare(strict_types=1);

namespace App\Customer\Application\Command\CreateLoyaltyProgram;

/**
 * Create Loyalty Program Command.
 *
 * Creates a new loyalty program for a tenant.
 *
 * Business Rule: One loyalty program per tenant (enforced in handler).
 */
final readonly class CreateLoyaltyProgramCommand
{
    public function __construct(
        public string $tenantId,
        public string $name,
        public float $earningRate,
        public int $minOrderValue,
        public string $minOrderCurrency,
        public int $redemptionRate,
        public string $redemptionCurrency,
    ) {
    }
}
