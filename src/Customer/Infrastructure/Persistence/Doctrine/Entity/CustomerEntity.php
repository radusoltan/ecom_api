<?php

declare(strict_types=1);

namespace App\Customer\Infrastructure\Persistence\Doctrine\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Customer\Domain\Model\Customer;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\CustomerSegment;
use App\Customer\Presentation\Api\Processor\ActivateCustomerProcessor;
use App\Customer\Presentation\Api\Processor\ChangeSegmentProcessor;
use App\Customer\Presentation\Api\Processor\DeactivateCustomerProcessor;
use App\Customer\Presentation\Api\Processor\RegisterCustomerProcessor;
use App\Customer\Presentation\Api\Processor\UpdateCustomerProcessor;
use App\Customer\Presentation\Api\Provider\CustomerCollectionProvider;
use App\Customer\Presentation\Api\Provider\CustomerItemProvider;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'customers')]
#[ORM\Index(columns: ['tenant_id'])]
#[ORM\Index(columns: ['email'])]
#[ORM\Index(columns: ['segment'])]
#[ORM\Index(columns: ['is_active'])]
#[ORM\UniqueConstraint(name: 'unique_email_per_tenant', columns: ['tenant_id', 'email'])]
#[ApiResource(
    shortName: 'Customer',
    operations: [
        new Get(provider: CustomerItemProvider::class),
        new GetCollection(provider: CustomerCollectionProvider::class),
        new Post(processor: RegisterCustomerProcessor::class),
        new Put(processor: UpdateCustomerProcessor::class),
        new Patch(
            uriTemplate: '/customers/{id}/activate',
            processor: ActivateCustomerProcessor::class
        ),
        new Patch(
            uriTemplate: '/customers/{id}/deactivate',
            processor: DeactivateCustomerProcessor::class
        ),
        new Patch(
            uriTemplate: '/customers/{id}/segment',
            processor: ChangeSegmentProcessor::class
        ),
        new Delete(),
    ]
)]
class CustomerEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id = '';

    #[ORM\Column(type: 'string', length: 36, nullable: false)]
    private string $tenantId = '';

    #[ORM\Column(type: 'string', length: 255, nullable: false)]
    private string $email = '';

    #[ORM\Column(type: 'string', length: 50, nullable: false)]
    private string $firstName = '';

    #[ORM\Column(type: 'string', length: 50, nullable: false)]
    private string $lastName = '';

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $phoneNumber = null;

    #[ORM\Column(type: 'string', length: 20, nullable: false)]
    private string $segment = '';

    #[ORM\Column(type: 'integer', nullable: false)]
    private int $loyaltyPoints = 0;

    #[ORM\Column(type: 'boolean', nullable: false)]
    private bool $isActive = true;

    #[ORM\Column(type: 'datetime_immutable', nullable: false)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: false)]
    private DateTimeImmutable $updatedAt;

    /**
     * Temporary field to accept password during registration (not persisted in DB)
     * Used only for User creation
     */
    private ?string $plainPassword = null;

    public static function fromDomainModel(Customer $customer): self
    {
        $entity = new self();
        $entity->id = $customer->id()->toString();
        $entity->tenantId = $customer->tenantId()->toString();
        $entity->email = $customer->email()->toString();
        $entity->firstName = $customer->firstName();
        $entity->lastName = $customer->lastName();
        $entity->phoneNumber = $customer->phoneNumber();
        $entity->segment = $customer->segment()->toString();
        $entity->loyaltyPoints = $customer->loyaltyPoints();
        $entity->isActive = $customer->isActive();
        $entity->createdAt = $customer->createdAt();
        $entity->updatedAt = $customer->updatedAt();

        return $entity;
    }

    public function updateFromDomainModel(Customer $customer): void
    {
        // Note: id, tenantId, email should never change
        $this->firstName = $customer->firstName();
        $this->lastName = $customer->lastName();
        $this->phoneNumber = $customer->phoneNumber();
        $this->segment = $customer->segment()->toString();
        $this->loyaltyPoints = $customer->loyaltyPoints();
        $this->isActive = $customer->isActive();
        $this->updatedAt = $customer->updatedAt();
    }

    public function toDomainModel(): Customer
    {
        return Customer::reconstituteFromPersistence(
            CustomerId::fromString($this->id),
            TenantId::fromString($this->tenantId),
            Email::fromString($this->email),
            $this->firstName,
            $this->lastName,
            $this->phoneNumber,
            CustomerSegment::fromString($this->segment),
            $this->loyaltyPoints,
            $this->isActive,
            $this->createdAt,
            $this->updatedAt
        );
    }

    // Getters
    public function getId(): string
    {
        return $this->id;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function getSegment(): string
    {
        return $this->segment;
    }

    public function getLoyaltyPoints(): int
    {
        return $this->loyaltyPoints;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getFullName(): string
    {
        return $this->firstName . ' ' . $this->lastName;
    }

    // Setters (for API Platform deserialization)
    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function setTenantId(string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function setFirstName(string $firstName): void
    {
        $this->firstName = $firstName;
    }

    public function setLastName(string $lastName): void
    {
        $this->lastName = $lastName;
    }

    public function setPhoneNumber(?string $phoneNumber): void
    {
        $this->phoneNumber = $phoneNumber;
    }

    public function setSegment(string $segment): void
    {
        $this->segment = $segment;
    }

    public function setLoyaltyPoints(int $loyaltyPoints): void
    {
        $this->loyaltyPoints = $loyaltyPoints;
    }

    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $plainPassword): void
    {
        $this->plainPassword = $plainPassword;
    }
}
