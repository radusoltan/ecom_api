<?php

declare(strict_types=1);

namespace App\Tax\Domain\Model;

/**
 * Tax Jurisdiction value object.
 *
 * Represents a geographic area where specific tax rules apply.
 * Uses ISO 3166-1 alpha-2 country codes and optional region codes.
 *
 * Business Rules:
 * - Country code must be valid ISO 3166-1 alpha-2 (2 uppercase letters)
 * - Region code is optional (for US states, Canadian provinces, etc.)
 * - Immutable once created
 * - EU membership list is maintained for VAT validation
 *
 * Examples:
 * - Germany: DE
 * - California, USA: US-CA
 * - Bavaria, Germany: DE-BY
 * - France: FR
 */
final readonly class TaxJurisdiction
{
    private const EU_COUNTRIES = [
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
        'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
        'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE',
    ];

    private function __construct(
        private string $countryCode,
        private ?string $regionCode = null,
    ) {
        if (!preg_match('/^[A-Z]{2}$/', $countryCode)) {
            throw new \InvalidArgumentException(sprintf('Invalid ISO country code: %s', $countryCode));
        }
    }

    public static function fromCountryCode(string $countryCode): self
    {
        return new self(strtoupper($countryCode));
    }

    public static function fromCountryAndRegion(string $countryCode, string $regionCode): self
    {
        return new self(strtoupper($countryCode), strtoupper($regionCode));
    }

    public function countryCode(): string
    {
        return $this->countryCode;
    }

    public function regionCode(): ?string
    {
        return $this->regionCode;
    }

    public function isEu(): bool
    {
        return in_array($this->countryCode, self::EU_COUNTRIES, true);
    }

    public function isUs(): bool
    {
        return 'US' === $this->countryCode;
    }

    public function equals(self $other): bool
    {
        return $this->countryCode === $other->countryCode
            && $this->regionCode === $other->regionCode;
    }

    public function toString(): string
    {
        return null !== $this->regionCode
            ? "{$this->countryCode}-{$this->regionCode}"
            : $this->countryCode;
    }
}
