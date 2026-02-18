<?php

declare(strict_types=1);

namespace App\Customer\Application\DTO;

use App\Customer\Domain\Model\LoyaltyTier;

/**
 * Loyalty Tier Data Transfer Object.
 *
 * Read-only representation of a loyalty tier for API responses.
 */
final readonly class LoyaltyTierDTO
{
    /**
     * @param array<string, mixed>|null $freeShippingMinOrder
     */
    public function __construct(
        public string $id,
        public string $name,
        public int $threshold,
        public int $discountPercentage,
        public ?array $freeShippingMinOrder,
        public int $sortOrder,
    ) {
    }

    public static function fromDomainModel(LoyaltyTier $tier): self
    {
        return new self(
            id: $tier->id()->toString(),
            name: $tier->name(),
            threshold: $tier->threshold(),
            discountPercentage: $tier->discountPercentage(),
            freeShippingMinOrder: $tier->freeShippingMinOrder()?->toArray(),
            sortOrder: $tier->sortOrder()
        );
    }
}
