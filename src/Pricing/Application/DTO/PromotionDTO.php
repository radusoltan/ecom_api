<?php

declare(strict_types=1);

namespace App\Pricing\Application\DTO;

use App\Pricing\Domain\Model\Promotion;

final readonly class PromotionDTO
{
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $name,
        public string $type,
        public string $discountType,
        public float $discountValue,
        public int $priority,
        public bool $isActive,
        public ?string $couponCode,
        public array $conditions,
        public ?string $validFrom,
        public ?string $validTo,
        public string $createdAt,
        public string $updatedAt
    ) {
    }

    public static function fromDomain(Promotion $promotion): self
    {
        return new self(
            id: $promotion->id()->toString(),
            tenantId: $promotion->tenantId()->toString(),
            name: $promotion->name(),
            type: $promotion->type()->toString(),
            discountType: $promotion->discount()->type()->toString(),
            discountValue: $promotion->discount()->value(),
            priority: $promotion->priority(),
            isActive: $promotion->isActive(),
            couponCode: $promotion->couponCode()?->toString(),
            conditions: $promotion->conditions(),
            validFrom: $promotion->validFrom()?->format(\DateTimeInterface::ATOM),
            validTo: $promotion->validTo()?->format(\DateTimeInterface::ATOM),
            createdAt: $promotion->createdAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $promotion->updatedAt()->format(\DateTimeInterface::ATOM)
        );
    }
}
