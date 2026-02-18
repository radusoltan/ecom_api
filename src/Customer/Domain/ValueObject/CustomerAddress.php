<?php

declare(strict_types=1);

namespace App\Customer\Domain\ValueObject;

/**
 * Customer Address Value Object.
 *
 * Business Rules:
 * - street: required, max 255 characters
 * - street2: optional, max 255 characters
 * - city: required, max 100 characters
 * - state: optional, max 100 characters
 * - postalCode: required, max 20 characters
 * - country: required, ISO 2-letter code (uppercase)
 * - type: shipping, billing, or both
 * - isDefaultShipping: only one address can be default shipping
 * - isDefaultBilling: only one address can be default billing
 */
final readonly class CustomerAddress
{
    private function __construct(
        public CustomerAddressId $id,
        public string $street,
        public ?string $street2,
        public string $city,
        public ?string $state,
        public string $postalCode,
        public string $country,
        public AddressType $type,
        public bool $isDefaultShipping,
        public bool $isDefaultBilling,
    ) {
        $this->validate();
    }

    public static function create(
        CustomerAddressId $id,
        string $street,
        ?string $street2,
        string $city,
        ?string $state,
        string $postalCode,
        string $country,
        AddressType $type,
        bool $isDefaultShipping = false,
        bool $isDefaultBilling = false,
    ): self {
        return new self(
            $id,
            trim($street),
            $street2 ? trim($street2) : null,
            trim($city),
            $state ? trim($state) : null,
            trim($postalCode),
            strtoupper(trim($country)),
            $type,
            $isDefaultShipping,
            $isDefaultBilling
        );
    }

    public function withDefaultShipping(bool $isDefault): self
    {
        return new self(
            $this->id,
            $this->street,
            $this->street2,
            $this->city,
            $this->state,
            $this->postalCode,
            $this->country,
            $this->type,
            $isDefault,
            $this->isDefaultBilling
        );
    }

    public function withDefaultBilling(bool $isDefault): self
    {
        return new self(
            $this->id,
            $this->street,
            $this->street2,
            $this->city,
            $this->state,
            $this->postalCode,
            $this->country,
            $this->type,
            $this->isDefaultShipping,
            $isDefault
        );
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
            'street' => $this->street,
            'street2' => $this->street2,
            'city' => $this->city,
            'state' => $this->state,
            'postalCode' => $this->postalCode,
            'country' => $this->country,
            'type' => $this->type->toString(),
            'isDefaultShipping' => $this->isDefaultShipping,
            'isDefaultBilling' => $this->isDefaultBilling,
        ];
    }

    private function validate(): void
    {
        // Validate street
        if ('' === $this->street) {
            throw new \InvalidArgumentException('Address street is required');
        }
        if (mb_strlen($this->street) > 255) {
            throw new \InvalidArgumentException(sprintf('Address street cannot exceed 255 characters. Got: %d', mb_strlen($this->street)));
        }

        // Validate street2 (optional)
        if (null !== $this->street2 && mb_strlen($this->street2) > 255) {
            throw new \InvalidArgumentException(sprintf('Address street2 cannot exceed 255 characters. Got: %d', mb_strlen($this->street2)));
        }

        // Validate city
        if ('' === $this->city) {
            throw new \InvalidArgumentException('Address city is required');
        }
        if (mb_strlen($this->city) > 100) {
            throw new \InvalidArgumentException(sprintf('Address city cannot exceed 100 characters. Got: %d', mb_strlen($this->city)));
        }

        // Validate state (optional)
        if (null !== $this->state && mb_strlen($this->state) > 100) {
            throw new \InvalidArgumentException(sprintf('Address state cannot exceed 100 characters. Got: %d', mb_strlen($this->state)));
        }

        // Validate postal code
        if ('' === $this->postalCode) {
            throw new \InvalidArgumentException('Address postal code is required');
        }
        if (mb_strlen($this->postalCode) > 20) {
            throw new \InvalidArgumentException(sprintf('Address postal code cannot exceed 20 characters. Got: %d', mb_strlen($this->postalCode)));
        }

        // Validate country (ISO 2-letter code)
        if ('' === $this->country) {
            throw new \InvalidArgumentException('Address country is required');
        }
        if (2 !== mb_strlen($this->country)) {
            throw new \InvalidArgumentException(sprintf('Address country must be a 2-letter ISO code. Got: "%s"', $this->country));
        }
        if (!preg_match('/^[A-Z]{2}$/', $this->country)) {
            throw new \InvalidArgumentException(sprintf('Address country must contain only uppercase letters. Got: "%s"', $this->country));
        }
    }
}
