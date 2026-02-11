<?php

declare(strict_types=1);

namespace App\Invoice\Domain\Model;

/**
 * Billing Address Value Object.
 *
 * Business Rules:
 * - Name is required (min 2 chars)
 * - Address line 1 is required (min 5 chars)
 * - City is required (min 2 chars)
 * - Postal code is required (min 3 chars)
 * - Country must be ISO 3166-1 alpha-2 code (e.g., US, FR, DE)
 * - VAT number is optional (for B2B invoices)
 * - Immutable value object
 */
final readonly class BillingAddress
{
    private function __construct(
        private string $name,
        private string $addressLine1,
        private ?string $addressLine2,
        private string $city,
        private string $postalCode,
        private string $country,
        private ?string $vatNumber
    ) {
        $this->validate();
    }

    public static function create(
        string $name,
        string $addressLine1,
        ?string $addressLine2,
        string $city,
        string $postalCode,
        string $country,
        ?string $vatNumber = null
    ): self {
        return new self(
            trim($name),
            trim($addressLine1),
            $addressLine2 ? trim($addressLine2) : null,
            trim($city),
            trim($postalCode),
            strtoupper(trim($country)),
            $vatNumber ? trim($vatNumber) : null
        );
    }

    private function validate(): void
    {
        if (mb_strlen($this->name) < 2) {
            throw new \InvalidArgumentException('Billing name must be at least 2 characters long');
        }

        if (mb_strlen($this->addressLine1) < 5) {
            throw new \InvalidArgumentException('Address line 1 must be at least 5 characters long');
        }

        if (mb_strlen($this->city) < 2) {
            throw new \InvalidArgumentException('City must be at least 2 characters long');
        }

        if (mb_strlen($this->postalCode) < 3) {
            throw new \InvalidArgumentException('Postal code must be at least 3 characters long');
        }

        // Validate ISO 3166-1 alpha-2 country code
        if (!preg_match('/^[A-Z]{2}$/', $this->country)) {
            throw new \InvalidArgumentException(sprintf('Country must be a valid ISO 3166-1 alpha-2 code (2 uppercase letters): "%s"', $this->country));
        }
    }

    public function name(): string
    {
        return $this->name;
    }

    public function addressLine1(): string
    {
        return $this->addressLine1;
    }

    public function addressLine2(): ?string
    {
        return $this->addressLine2;
    }

    public function city(): string
    {
        return $this->city;
    }

    public function postalCode(): string
    {
        return $this->postalCode;
    }

    public function country(): string
    {
        return $this->country;
    }

    public function vatNumber(): ?string
    {
        return $this->vatNumber;
    }

    /**
     * Format address as array.
     *
     * @return array{name: string, addressLine1: string, addressLine2: string|null, city: string, postalCode: string, country: string, vatNumber: string|null}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'addressLine1' => $this->addressLine1,
            'addressLine2' => $this->addressLine2,
            'city' => $this->city,
            'postalCode' => $this->postalCode,
            'country' => $this->country,
            'vatNumber' => $this->vatNumber,
        ];
    }

    /**
     * Format address as multi-line string.
     */
    public function format(): string
    {
        $lines = [
            $this->name,
            $this->addressLine1,
        ];

        if (null !== $this->addressLine2) {
            $lines[] = $this->addressLine2;
        }

        $lines[] = sprintf('%s, %s', $this->postalCode, $this->city);
        $lines[] = $this->country;

        if (null !== $this->vatNumber) {
            $lines[] = sprintf('VAT: %s', $this->vatNumber);
        }

        return implode("\n", $lines);
    }

    public function equals(self $other): bool
    {
        return $this->name === $other->name
            && $this->addressLine1 === $other->addressLine1
            && $this->addressLine2 === $other->addressLine2
            && $this->city === $other->city
            && $this->postalCode === $other->postalCode
            && $this->country === $other->country
            && $this->vatNumber === $other->vatNumber;
    }
}
