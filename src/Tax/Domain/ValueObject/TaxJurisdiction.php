<?php

declare(strict_types=1);

namespace App\Tax\Domain\ValueObject;

/**
 * Tax Jurisdiction Value Object
 *
 * Represents a tax jurisdiction (country/region/state).
 * Used for destination-based tax calculation.
 */
final readonly class TaxJurisdiction
{
    private function __construct(
        private string $countryCode,
        private ?string $regionCode = null
    ) {
        if (strlen($countryCode) !== 2) {
            throw new \InvalidArgumentException(
                sprintf('Country code must be 2 characters (ISO 3166-1 alpha-2), got "%s"', $countryCode)
            );
        }

        if ($regionCode !== null && (strlen($regionCode) < 2 || strlen($regionCode) > 3)) {
            throw new \InvalidArgumentException(
                sprintf('Region code must be 2-3 characters, got "%s"', $regionCode)
            );
        }
    }

    public static function fromCountry(string $countryCode): self
    {
        return new self(strtoupper($countryCode));
    }

    public static function fromCountryAndRegion(string $countryCode, string $regionCode): self
    {
        return new self(strtoupper($countryCode), strtoupper($regionCode));
    }

    public function getCountryCode(): string
    {
        return $this->countryCode;
    }

    public function getRegionCode(): ?string
    {
        return $this->regionCode;
    }

    public function hasRegion(): bool
    {
        return $this->regionCode !== null;
    }

    /**
     * Check if this jurisdiction matches another (country level or exact match)
     */
    public function matches(self $other): bool
    {
        // Country must match
        if ($this->countryCode !== $other->countryCode) {
            return false;
        }

        // If either has no region, match at country level
        if (!$this->hasRegion() || !$other->hasRegion()) {
            return true;
        }

        // Both have regions, must match exactly
        return $this->regionCode === $other->regionCode;
    }

    public function equals(self $other): bool
    {
        return $this->countryCode === $other->countryCode
            && $this->regionCode === $other->regionCode;
    }

    public function toString(): string
    {
        if ($this->regionCode !== null) {
            return "{$this->countryCode}-{$this->regionCode}";
        }

        return $this->countryCode;
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
