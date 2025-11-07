<?php

declare(strict_types=1);

namespace App\Catalog\Domain\ValueObject;

/**
 * ProductStatus Value Object
 *
 * Represents the lifecycle status of a product.
 *
 * Business Rules (from PRD):
 * - Valid statuses: DRAFT, ACTIVE, INACTIVE, DISCONTINUED
 * - Only ACTIVE products appear in storefront
 * - DRAFT → ACTIVE (publish)
 * - ACTIVE ↔ INACTIVE (deactivate/reactivate)
 * - Any status → DISCONTINUED (cannot be reversed)
 */
final readonly class ProductStatus
{
    private const DRAFT = 'draft';
    private const ACTIVE = 'active';
    private const INACTIVE = 'inactive';
    private const DISCONTINUED = 'discontinued';

    private const VALID_STATUSES = [
        self::DRAFT,
        self::ACTIVE,
        self::INACTIVE,
        self::DISCONTINUED,
    ];

    private function __construct(
        private string $value
    ) {
        if (!in_array($value, self::VALID_STATUSES, true)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Invalid product status "%s". Valid statuses are: %s',
                    $value,
                    implode(', ', self::VALID_STATUSES)
                )
            );
        }
    }

    public static function draft(): self
    {
        return new self(self::DRAFT);
    }

    public static function active(): self
    {
        return new self(self::ACTIVE);
    }

    public static function inactive(): self
    {
        return new self(self::INACTIVE);
    }

    public static function discontinued(): self
    {
        return new self(self::DISCONTINUED);
    }

    public static function fromString(string $value): self
    {
        return new self(strtolower(trim($value)));
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isDraft(): bool
    {
        return $this->value === self::DRAFT;
    }

    public function isActive(): bool
    {
        return $this->value === self::ACTIVE;
    }

    public function isInactive(): bool
    {
        return $this->value === self::INACTIVE;
    }

    public function isDiscontinued(): bool
    {
        return $this->value === self::DISCONTINUED;
    }

    public function canTransitionTo(self $newStatus): bool
    {
        // DISCONTINUED is final - cannot transition from it
        if ($this->isDiscontinued()) {
            return false;
        }

        // Can always transition to DISCONTINUED
        if ($newStatus->isDiscontinued()) {
            return true;
        }

        // DRAFT can only transition to ACTIVE or DISCONTINUED
        if ($this->isDraft()) {
            return $newStatus->isActive() || $newStatus->isDiscontinued();
        }

        // ACTIVE can transition to INACTIVE or DISCONTINUED
        if ($this->isActive()) {
            return $newStatus->isInactive() || $newStatus->isDiscontinued();
        }

        // INACTIVE can transition to ACTIVE or DISCONTINUED
        if ($this->isInactive()) {
            return $newStatus->isActive() || $newStatus->isDiscontinued();
        }

        return false;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
