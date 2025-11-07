<?php

declare(strict_types=1);

namespace App\Customer\Domain\Event;

use App\Customer\Domain\ValueObject\CustomerId;

final readonly class CustomerUpdated
{
    public function __construct(
        private CustomerId $customerId,
        private string $firstName,
        private string $lastName,
        private ?string $phoneNumber
    ) {
    }

    public function customerId(): CustomerId
    {
        return $this->customerId;
    }

    public function firstName(): string
    {
        return $this->firstName;
    }

    public function lastName(): string
    {
        return $this->lastName;
    }

    public function phoneNumber(): ?string
    {
        return $this->phoneNumber;
    }
}
