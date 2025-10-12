<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use InvalidArgumentException;

final readonly class Address
{
    private function __construct(
        public string $street,
        public string $city,
        public string $state,
        public string $postalCode,
        public string $country
    ) {
        if (trim($street) === '') {
            throw new InvalidArgumentException('Address street cannot be empty');
        }

        if (trim($city) === '') {
            throw new InvalidArgumentException('Address city cannot be empty');
        }

        if (trim($postalCode) === '') {
            throw new InvalidArgumentException('Address postal code cannot be empty');
        }

        if (trim($country) === '') {
            throw new InvalidArgumentException('Address country cannot be empty');
        }
    }

    public static function create(
        string $street,
        string $city,
        string $state,
        string $postalCode,
        string $country
    ): self {
        return new self($street, $city, $state, $postalCode, $country);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['street'] ?? '',
            $data['city'] ?? '',
            $data['state'] ?? '',
            $data['postalCode'] ?? '',
            $data['country'] ?? ''
        );
    }

    public function street(): string
    {
        return $this->street;
    }

    public function city(): string
    {
        return $this->city;
    }

    public function state(): string
    {
        return $this->state;
    }

    public function postalCode(): string
    {
        return $this->postalCode;
    }

    public function country(): string
    {
        return $this->country;
    }

    public function equals(Address $other): bool
    {
        return $this->street === $other->street
            && $this->city === $other->city
            && $this->state === $other->state
            && $this->postalCode === $other->postalCode
            && $this->country === $other->country;
    }

    public function toString(): string
    {
        return sprintf(
            '%s, %s, %s %s, %s',
            $this->street,
            $this->city,
            $this->state,
            $this->postalCode,
            $this->country
        );
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
