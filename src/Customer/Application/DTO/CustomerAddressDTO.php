<?php

declare(strict_types=1);

namespace App\Customer\Application\DTO;

use App\Customer\Domain\ValueObject\CustomerAddress;
use App\Customer\Infrastructure\Persistence\Doctrine\Entity\CustomerAddressEntity;

/**
 * Data Transfer Object for Customer Address.
 */
final readonly class CustomerAddressDTO
{
    public function __construct(
        public string $id,
        public string $customerId,
        public string $tenantId,
        public string $street,
        public ?string $street2,
        public string $city,
        public ?string $state,
        public string $postalCode,
        public string $country,
        public string $type,
        public bool $isDefaultShipping,
        public bool $isDefaultBilling,
        public ?\DateTimeImmutable $createdAt = null,
        public ?\DateTimeImmutable $updatedAt = null,
    ) {
    }

    public static function fromDomainModel(
        CustomerAddress $address,
        string $customerId,
        string $tenantId,
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $updatedAt = null
    ): self {
        return new self(
            id: $address->id->toString(),
            customerId: $customerId,
            tenantId: $tenantId,
            street: $address->street,
            street2: $address->street2,
            city: $address->city,
            state: $address->state,
            postalCode: $address->postalCode,
            country: $address->country,
            type: $address->type->toString(),
            isDefaultShipping: $address->isDefaultShipping,
            isDefaultBilling: $address->isDefaultBilling,
            createdAt: $createdAt,
            updatedAt: $updatedAt
        );
    }

    public static function fromEntity(CustomerAddressEntity $entity): self
    {
        return new self(
            id: $entity->getId(),
            customerId: $entity->getCustomerId(),
            tenantId: $entity->getTenantId(),
            street: $entity->getStreet(),
            street2: $entity->getStreet2(),
            city: $entity->getCity(),
            state: $entity->getState(),
            postalCode: $entity->getPostalCode(),
            country: $entity->getCountry(),
            type: $entity->getType(),
            isDefaultShipping: $entity->isDefaultShipping(),
            isDefaultBilling: $entity->isDefaultBilling(),
            createdAt: $entity->getCreatedAt(),
            updatedAt: $entity->getUpdatedAt()
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'customerId' => $this->customerId,
            'tenantId' => $this->tenantId,
            'street' => $this->street,
            'street2' => $this->street2,
            'city' => $this->city,
            'state' => $this->state,
            'postalCode' => $this->postalCode,
            'country' => $this->country,
            'type' => $this->type,
            'isDefaultShipping' => $this->isDefaultShipping,
            'isDefaultBilling' => $this->isDefaultBilling,
            'createdAt' => $this->createdAt?->format('c'),
            'updatedAt' => $this->updatedAt?->format('c'),
        ];
    }
}
