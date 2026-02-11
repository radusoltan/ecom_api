<?php

declare(strict_types=1);

namespace App\Tax\Domain\Model;

/**
 * EU VAT Number value object with format validation.
 *
 * Validates VAT numbers according to each EU member state's format.
 * Used for B2B transactions and reverse charge mechanism.
 *
 * Business Rules:
 * - Format: 2-letter country code + 2-12 alphanumeric characters
 * - Examples: DE123456789, FR12345678901, GB123456789
 * - Country code must match EU member state
 * - Each country has specific format validation rules
 * - Greece uses 'EL' prefix instead of 'GR'
 * - Immutable once created
 *
 * Note: This validates format only, not actual VAT number existence.
 * Use VIES service for real-time validation against EU database.
 */
final readonly class VatNumber
{
    // VAT number patterns by country (EU member states)
    private const PATTERNS = [
        'AT' => '/^ATU\d{8}$/',           // Austria
        'BE' => '/^BE0?\d{9,10}$/',        // Belgium
        'BG' => '/^BG\d{9,10}$/',          // Bulgaria
        'HR' => '/^HR\d{11}$/',            // Croatia
        'CY' => '/^CY\d{8}[A-Z]$/',        // Cyprus
        'CZ' => '/^CZ\d{8,10}$/',          // Czech Republic
        'DK' => '/^DK\d{8}$/',             // Denmark
        'EE' => '/^EE\d{9}$/',             // Estonia
        'FI' => '/^FI\d{8}$/',             // Finland
        'FR' => '/^FR[A-Z0-9]{2}\d{9}$/',  // France
        'DE' => '/^DE\d{9}$/',             // Germany
        'GR' => '/^EL\d{9}$/',             // Greece (uses EL)
        'HU' => '/^HU\d{8}$/',             // Hungary
        'IE' => '/^IE\d{7}[A-Z]{1,2}$/',   // Ireland
        'IT' => '/^IT\d{11}$/',            // Italy
        'LV' => '/^LV\d{11}$/',            // Latvia
        'LT' => '/^LT(\d{9}|\d{12})$/',    // Lithuania
        'LU' => '/^LU\d{8}$/',             // Luxembourg
        'MT' => '/^MT\d{8}$/',             // Malta
        'NL' => '/^NL\d{9}B\d{2}$/',       // Netherlands
        'PL' => '/^PL\d{10}$/',            // Poland
        'PT' => '/^PT\d{9}$/',             // Portugal
        'RO' => '/^RO\d{2,10}$/',          // Romania
        'SK' => '/^SK\d{10}$/',            // Slovakia
        'SI' => '/^SI\d{8}$/',             // Slovenia
        'ES' => '/^ES[A-Z0-9]\d{7}[A-Z0-9]$/', // Spain
        'SE' => '/^SE\d{12}$/',            // Sweden
    ];

    private function __construct(
        private string $value,
        private string $countryCode
    ) {}

    public static function fromString(string $vatNumber): self
    {
        $normalized = strtoupper(str_replace([' ', '.', '-'], '', $vatNumber));

        // Extract country code (first 2 chars)
        $countryCode = substr($normalized, 0, 2);

        // Greece uses EL instead of GR
        if ($countryCode === 'EL') {
            $countryCode = 'GR';
        }

        // Validate format
        $pattern = self::PATTERNS[$countryCode] ?? null;
        if ($pattern === null) {
            throw new \InvalidArgumentException(
                sprintf('Unknown VAT number country code: %s', $countryCode)
            );
        }

        if (!preg_match($pattern, $normalized)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid VAT number format for %s: %s', $countryCode, $vatNumber)
            );
        }

        return new self($normalized, $countryCode);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function countryCode(): string
    {
        return $this->countryCode;
    }

    public function jurisdiction(): TaxJurisdiction
    {
        return TaxJurisdiction::fromCountryCode($this->countryCode);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function toString(): string
    {
        return $this->value;
    }
}
