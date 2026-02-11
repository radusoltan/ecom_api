<?php

declare(strict_types=1);

namespace App\Tax\Domain\ValueObject;

/**
 * VAT Number Value Object.
 *
 * Represents a European Union VAT identification number.
 * Format: 2-letter country code + 2-15 alphanumeric characters.
 * Examples: DE123456789, FR12345678901, GB123456789
 *
 * Business Rules:
 * - Must start with valid EU country code
 * - Number part: 2-15 characters (digits and/or letters)
 * - Used for B2B reverse charge mechanism
 * - Validation via VIES API (external service)
 *
 * Note: This VO only validates format, not actual validity.
 * Use VatValidationServiceInterface for VIES validation.
 */
final readonly class VatNumber
{
    private const EU_COUNTRY_CODES = [
        'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI',
        'FR', 'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT',
        'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK',
    ];

    private function __construct(
        private string $countryCode,
        private string $number
    ) {
        if (!in_array($countryCode, self::EU_COUNTRY_CODES, true)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Invalid VAT country code "%s". Must be an EU country code.',
                    $countryCode
                )
            );
        }

        if (strlen($number) < 2 || strlen($number) > 15) {
            throw new \InvalidArgumentException(
                sprintf(
                    'VAT number must be between 2 and 15 characters, got %d',
                    strlen($number)
                )
            );
        }

        // Only alphanumeric allowed
        if (!ctype_alnum($number)) {
            throw new \InvalidArgumentException(
                sprintf('VAT number must be alphanumeric, got "%s"', $number)
            );
        }
    }

    public static function fromString(string $vatNumber): self
    {
        // Remove spaces, dots, dashes
        $cleaned = preg_replace('/[\s.\-]/', '', $vatNumber);
        if (null === $cleaned) {
            throw new \InvalidArgumentException('Invalid VAT number format');
        }
        $cleaned = strtoupper($cleaned);

        if (strlen($cleaned) < 4) {
            throw new \InvalidArgumentException(
                sprintf('VAT number too short: "%s"', $vatNumber)
            );
        }

        // First 2 characters = country code
        $countryCode = substr($cleaned, 0, 2);
        $number = substr($cleaned, 2);

        return new self($countryCode, $number);
    }

    public static function fromParts(string $countryCode, string $number): self
    {
        return new self(strtoupper($countryCode), strtoupper($number));
    }

    public function getCountryCode(): string
    {
        return $this->countryCode;
    }

    public function getNumber(): string
    {
        return $this->number;
    }

    public function getFull(): string
    {
        return $this->countryCode . $this->number;
    }

    public function equals(self $other): bool
    {
        return $this->countryCode === $other->countryCode
            && $this->number === $other->number;
    }

    public function __toString(): string
    {
        return $this->getFull();
    }
}
