<?php

declare(strict_types=1);

namespace App\Customer\Domain\ValueObject;

/**
 * Address Value Object.
 *
 * Business Rules:
 * - Street, city, postal code, and country are required
 * - State/province is optional
 * - Type can be: billing, shipping
 * - Label is optional (e.g., "Home", "Office", "Apartment")
 */
final readonly class Address
{
    public const TYPE_BILLING = 'billing';
    public const TYPE_SHIPPING = 'shipping';

    private function __construct(
        public string $street,
        public string $city,
        public string $postalCode,
        public string $country,
        public ?string $state,
        public string $type,
        public ?string $label,
    ) {
        $this->validate();
    }

    public static function create(
        string $street,
        string $city,
        string $postalCode,
        string $country,
        ?string $state = null,
        string $type = self::TYPE_SHIPPING,
        ?string $label = null,
    ): self {
        return new self($street, $city, $postalCode, $country, $state, $type, $label);
    }

    private function validate(): void
    {
        if ('' === trim($this->street)) {
            throw new \InvalidArgumentException('Street address is required');
        }

        if ('' === trim($this->city)) {
            throw new \InvalidArgumentException('City is required');
        }

        if ('' === trim($this->postalCode)) {
            throw new \InvalidArgumentException('Postal code is required');
        }

        if ('' === trim($this->country)) {
            throw new \InvalidArgumentException('Country is required');
        }

        if (!in_array($this->type, [self::TYPE_BILLING, self::TYPE_SHIPPING], true)) {
            throw new \InvalidArgumentException(sprintf('Invalid address type: %s. Must be either "billing" or "shipping"', $this->type));
        }
    }

    public function isBilling(): bool
    {
        return self::TYPE_BILLING === $this->type;
    }

    public function isShipping(): bool
    {
        return self::TYPE_SHIPPING === $this->type;
    }

    public function equals(Address $other): bool
    {
        return $this->street === $other->street
            && $this->city === $other->city
            && $this->postalCode === $other->postalCode
            && $this->country === $other->country
            && $this->state === $other->state;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'street' => $this->street,
            'city' => $this->city,
            'postalCode' => $this->postalCode,
            'country' => $this->country,
            'state' => $this->state,
            'type' => $this->type,
            'label' => $this->label,
        ];
    }
}
