<?php

declare(strict_types=1);

namespace App\Customer\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * CustomerAddress Doctrine Entity.
 *
 * Infrastructure layer entity with ORM attributes.
 * Maps to customer_addresses table.
 * Converts to/from CustomerAddress domain value object.
 *
 * Note: API operations are defined in CustomerAddressResource (Presentation layer).
 */
#[ORM\Entity]
#[ORM\Table(name: 'customer_addresses')]
#[ORM\Index(columns: ['customer_id'])]
#[ORM\Index(columns: ['tenant_id'])]
#[ORM\Index(columns: ['type'])]
#[ORM\Index(columns: ['is_default_shipping'])]
#[ORM\Index(columns: ['is_default_billing'])]
#[ORM\Index(columns: ['is_deleted'])]
class CustomerAddressEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id = '';

    #[ORM\Column(type: 'string', length: 36, nullable: false)]
    private string $customerId = '';

    #[ORM\Column(type: 'string', length: 36, nullable: false)]
    private string $tenantId = '';

    #[ORM\ManyToOne(targetEntity: CustomerEntity::class, inversedBy: 'addressEntities')]
    #[ORM\JoinColumn(name: 'customer_id', referencedColumnName: 'id', nullable: false)]
    private ?CustomerEntity $customer = null;

    #[ORM\Column(type: 'string', length: 255, nullable: false)]
    private string $street = '';

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $street2 = null;

    #[ORM\Column(type: 'string', length: 100, nullable: false)]
    private string $city = '';

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $state = null;

    #[ORM\Column(type: 'string', length: 20, nullable: false)]
    private string $postalCode = '';

    #[ORM\Column(type: 'string', length: 2, nullable: false)]
    private string $country = '';

    #[ORM\Column(type: 'string', length: 20, nullable: false)]
    private string $type = '';

    #[ORM\Column(type: 'boolean', nullable: false, options: ['default' => false])]
    private bool $isDefaultShipping = false;

    #[ORM\Column(type: 'boolean', nullable: false, options: ['default' => false])]
    private bool $isDefaultBilling = false;

    #[ORM\Column(type: 'boolean', nullable: false, options: ['default' => false])]
    private bool $isDeleted = false;

    #[ORM\Column(type: 'datetime_immutable', nullable: false)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: false)]
    private \DateTimeImmutable $updatedAt;

    /**
     * Create entity from domain model.
     *
     * Note: Since CustomerAddress is a value object (based on the existing Address pattern),
     * we need customerId and tenantId passed separately.
     *
     * @param array<string, mixed> $addressData Array containing address data with keys:
     *                                          id, street, street2, city, state, postalCode,
     *                                          country, type, isDefaultShipping, isDefaultBilling
     */
    public static function fromDomainModel(
        array $addressData,
        string $customerId,
        string $tenantId
    ): self {
        $entity = new self();
        $entity->id = $addressData['id'] ?? '';
        $entity->customerId = $customerId;
        $entity->tenantId = $tenantId;
        $entity->street = $addressData['street'] ?? '';
        $entity->street2 = $addressData['street2'] ?? null;
        $entity->city = $addressData['city'] ?? '';
        $entity->state = $addressData['state'] ?? null;
        $entity->postalCode = $addressData['postalCode'] ?? '';
        $entity->country = $addressData['country'] ?? '';
        $entity->type = $addressData['type'] ?? '';
        $entity->isDefaultShipping = $addressData['isDefaultShipping'] ?? false;
        $entity->isDefaultBilling = $addressData['isDefaultBilling'] ?? false;
        $entity->isDeleted = $addressData['isDeleted'] ?? false;
        $entity->createdAt = $addressData['createdAt'] ?? new \DateTimeImmutable();
        $entity->updatedAt = $addressData['updatedAt'] ?? new \DateTimeImmutable();

        return $entity;
    }

    /**
     * Convert entity to domain model data.
     *
     * @return array<string, mixed>
     */
    public function toDomainModel(): array
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
            'isDeleted' => $this->isDeleted,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }

    /**
     * Update entity from domain model data.
     *
     * @param array<string, mixed> $addressData
     */
    public function updateFromDomainModel(array $addressData): void
    {
        // Note: id, customerId, tenantId should never change
        $this->street = $addressData['street'] ?? $this->street;
        $this->street2 = $addressData['street2'] ?? $this->street2;
        $this->city = $addressData['city'] ?? $this->city;
        $this->state = $addressData['state'] ?? $this->state;
        $this->postalCode = $addressData['postalCode'] ?? $this->postalCode;
        $this->country = $addressData['country'] ?? $this->country;
        $this->type = $addressData['type'] ?? $this->type;
        $this->isDefaultShipping = $addressData['isDefaultShipping'] ?? $this->isDefaultShipping;
        $this->isDefaultBilling = $addressData['isDefaultBilling'] ?? $this->isDefaultBilling;
        $this->isDeleted = $addressData['isDeleted'] ?? $this->isDeleted;
        $this->updatedAt = new \DateTimeImmutable();
    }

    // Getters
    public function getId(): string
    {
        return $this->id;
    }

    public function getCustomerId(): string
    {
        return $this->customerId;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function getCustomer(): ?CustomerEntity
    {
        return $this->customer;
    }

    public function getStreet(): string
    {
        return $this->street;
    }

    public function getStreet2(): ?string
    {
        return $this->street2;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function getState(): ?string
    {
        return $this->state;
    }

    public function getPostalCode(): string
    {
        return $this->postalCode;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function isDefaultShipping(): bool
    {
        return $this->isDefaultShipping;
    }

    public function isDefaultBilling(): bool
    {
        return $this->isDefaultBilling;
    }

    public function isDeleted(): bool
    {
        return $this->isDeleted;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    // Setters (for API Platform deserialization)
    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function setCustomerId(string $customerId): void
    {
        $this->customerId = $customerId;
    }

    public function setTenantId(string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function setCustomer(?CustomerEntity $customer): void
    {
        $this->customer = $customer;
    }

    public function setStreet(string $street): void
    {
        $this->street = $street;
    }

    public function setStreet2(?string $street2): void
    {
        $this->street2 = $street2;
    }

    public function setCity(string $city): void
    {
        $this->city = $city;
    }

    public function setState(?string $state): void
    {
        $this->state = $state;
    }

    public function setPostalCode(string $postalCode): void
    {
        $this->postalCode = $postalCode;
    }

    public function setCountry(string $country): void
    {
        $this->country = $country;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function setIsDefaultShipping(bool $isDefaultShipping): void
    {
        $this->isDefaultShipping = $isDefaultShipping;
    }

    public function setIsDefaultBilling(bool $isDefaultBilling): void
    {
        $this->isDefaultBilling = $isDefaultBilling;
    }

    public function setIsDeleted(bool $isDeleted): void
    {
        $this->isDeleted = $isDeleted;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    /**
     * Soft delete the address.
     */
    public function softDelete(): void
    {
        $this->isDeleted = true;
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Get full address as formatted string.
     */
    public function getFormattedAddress(): string
    {
        $parts = [$this->street];

        if (null !== $this->street2) {
            $parts[] = $this->street2;
        }

        $parts[] = $this->city;

        if (null !== $this->state) {
            $parts[] = $this->state;
        }

        $parts[] = $this->postalCode;
        $parts[] = $this->country;

        return implode(', ', $parts);
    }
}
