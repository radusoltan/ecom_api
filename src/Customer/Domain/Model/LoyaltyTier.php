<?php

declare(strict_types=1);

namespace App\Customer\Domain\Model;

use App\Customer\Domain\ValueObject\LoyaltyTierId;
use App\Shared\Domain\ValueObject\Money;

/**
 * Loyalty Tier Entity.
 *
 * Represents a tier level in a loyalty program with associated benefits.
 *
 * Business Rules:
 * - Name must be between 3 and 50 characters
 * - Threshold must be >= 0 (points required to reach this tier)
 * - Discount percentage must be between 0 and 100
 * - Sort order must be >= 0 (lower numbers appear first)
 * - Free shipping min order can be null (no free shipping benefit)
 */
final readonly class LoyaltyTier
{
    private function __construct(
        private LoyaltyTierId $id,
        private string $name,
        private int $threshold,
        private int $discountPercentage,
        private ?Money $freeShippingMinOrder,
        private int $sortOrder
    ) {
        $this->validateName($name);
        $this->validateThreshold($threshold);
        $this->validateDiscountPercentage($discountPercentage);
        $this->validateSortOrder($sortOrder);
    }

    public static function create(
        LoyaltyTierId $id,
        string $name,
        int $threshold,
        int $discountPercentage,
        ?Money $freeShippingMinOrder = null,
        int $sortOrder = 0
    ): self {
        return new self(
            $id,
            trim($name),
            $threshold,
            $discountPercentage,
            $freeShippingMinOrder,
            $sortOrder
        );
    }

    private function validateName(string $name): void
    {
        $length = mb_strlen($name);

        if ($length < 3 || $length > 50) {
            throw new \InvalidArgumentException(sprintf('Tier name must be between 3 and 50 characters. Got: %d', $length));
        }
    }

    private function validateThreshold(int $threshold): void
    {
        if ($threshold < 0) {
            throw new \InvalidArgumentException('Tier threshold must be greater than or equal to 0');
        }
    }

    private function validateDiscountPercentage(int $discountPercentage): void
    {
        if ($discountPercentage < 0 || $discountPercentage > 100) {
            throw new \InvalidArgumentException(sprintf('Discount percentage must be between 0 and 100. Got: %d', $discountPercentage));
        }
    }

    private function validateSortOrder(int $sortOrder): void
    {
        if ($sortOrder < 0) {
            throw new \InvalidArgumentException('Sort order must be greater than or equal to 0');
        }
    }

    public function id(): LoyaltyTierId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function threshold(): int
    {
        return $this->threshold;
    }

    public function discountPercentage(): int
    {
        return $this->discountPercentage;
    }

    public function freeShippingMinOrder(): ?Money
    {
        return $this->freeShippingMinOrder;
    }

    public function sortOrder(): int
    {
        return $this->sortOrder;
    }

    public function hasFreeShippingBenefit(): bool
    {
        return null !== $this->freeShippingMinOrder;
    }

    public function equals(self $other): bool
    {
        return $this->id->equals($other->id);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id->toString(),
            'name' => $this->name,
            'threshold' => $this->threshold,
            'discountPercentage' => $this->discountPercentage,
            'freeShippingMinOrder' => $this->freeShippingMinOrder?->toArray(),
            'sortOrder' => $this->sortOrder,
        ];
    }
}
