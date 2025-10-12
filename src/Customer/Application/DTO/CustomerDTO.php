<?php

declare(strict_types=1);

namespace App\Customer\Application\DTO;

use App\Customer\Domain\Model\Customer;

final readonly class CustomerDTO
{
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $email,
        public string $firstName,
        public string $lastName,
        public string $fullName,
        public ?string $phoneNumber,
        public string $segment,
        public int $loyaltyPoints,
        public bool $isActive,
        public string $createdAt,
        public string $updatedAt
    ) {
    }

    public static function fromDomain(Customer $customer): self
    {
        return new self(
            id: $customer->id()->toString(),
            tenantId: $customer->tenantId()->toString(),
            email: $customer->email()->toString(),
            firstName: $customer->firstName(),
            lastName: $customer->lastName(),
            fullName: $customer->fullName(),
            phoneNumber: $customer->phoneNumber(),
            segment: $customer->segment()->toString(),
            loyaltyPoints: $customer->loyaltyPoints(),
            isActive: $customer->isActive(),
            createdAt: $customer->createdAt()->format('Y-m-d H:i:s'),
            updatedAt: $customer->updatedAt()->format('Y-m-d H:i:s')
        );
    }
}
