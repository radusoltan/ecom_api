<?php

declare(strict_types=1);

namespace App\Customer\Application\DTO;

use App\Customer\Domain\Model\LoyaltyProgram;

/**
 * Loyalty Program Data Transfer Object.
 *
 * Read-only representation of a loyalty program for API responses.
 */
final readonly class LoyaltyProgramDTO
{
    /**
     * @param array<string, mixed>       $minOrderValue
     * @param array<string, mixed>       $redemptionRule
     * @param array<int, LoyaltyTierDTO> $tiers
     */
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $name,
        public ?string $description,
        public float $earningRate,
        public array $minOrderValue,
        public array $redemptionRule,
        public ?int $validityDays,
        public bool $isActive,
        public array $tiers,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public static function fromDomainModel(LoyaltyProgram $program): self
    {
        // Convert tiers to DTOs
        $tierDTOs = array_map(
            fn ($tier) => LoyaltyTierDTO::fromDomainModel($tier),
            $program->getTiers()
        );

        return new self(
            id: $program->id()->toString(),
            tenantId: $program->tenantId()->toString(),
            name: $program->name(),
            description: $program->description(),
            earningRate: $program->earningRate()->toFloat(),
            minOrderValue: $program->minOrderValue()->toArray(),
            redemptionRule: $program->redemptionRule()->toArray(),
            validityDays: $program->validityDays(),
            isActive: $program->isActive(),
            tiers: $tierDTOs,
            createdAt: $program->createdAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $program->updatedAt()->format(\DateTimeInterface::ATOM)
        );
    }
}
