<?php

declare(strict_types=1);

namespace App\Privacy\Infrastructure\Persistence\Doctrine\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Privacy\Domain\Model\Consent;
use App\Privacy\Domain\ValueObject\ConsentId;
use App\Privacy\Domain\ValueObject\ConsentPurpose;
use App\Privacy\Presentation\Api\Processor\GrantConsentProcessor;
use App\Privacy\Presentation\Api\Processor\WithdrawConsentProcessor;
use App\Privacy\Presentation\Api\Provider\ConsentCollectionProvider;
use App\Privacy\Presentation\Api\Provider\ConsentItemProvider;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'consents')]
#[ORM\Index(columns: ['customer_id'], name: 'idx_consent_customer')]
#[ORM\Index(columns: ['tenant_id'], name: 'idx_consent_tenant')]
#[ORM\Index(columns: ['customer_id', 'purpose', 'is_granted'], name: 'idx_consent_customer_purpose_granted')]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/consents',
            provider: ConsentCollectionProvider::class
        ),
        new Get(
            uriTemplate: '/consents/{id}',
            provider: ConsentItemProvider::class
        ),
        new Post(
            uriTemplate: '/consents',
            processor: GrantConsentProcessor::class
        ),
        new Patch(
            uriTemplate: '/consents/{id}/withdraw',
            processor: WithdrawConsentProcessor::class
        ),
    ]
)]
class ConsentEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'consent_id', length: 26)]
    private string $id;

    #[ORM\Column(type: 'tenant_id', length: 26)]
    private string $tenantId;

    #[ORM\Column(type: 'customer_id', length: 26)]
    private string $customerId;

    #[ORM\Column(type: 'consent_purpose', length: 50)]
    private string $purpose;

    #[ORM\Column(type: 'boolean')]
    private bool $isGranted;

    #[ORM\Column(type: 'encrypted_string')]
    private string $ipAddress;

    #[ORM\Column(type: 'encrypted_string')]
    private string $userAgent;

    #[ORM\Column(type: 'text')]
    private string $consentText;

    #[ORM\Column(type: 'string', length: 20)]
    private string $consentVersion;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $grantedAt;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $withdrawnAt;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $updatedAt;

    public static function fromDomainModel(Consent $consent): self
    {
        $entity = new self();
        $entity->id = $consent->id()->toString();
        $entity->tenantId = $consent->tenantId()->toString();
        $entity->customerId = $consent->customerId()->toString();
        $entity->purpose = $consent->purpose()->value();
        $entity->isGranted = $consent->isGranted();
        $entity->ipAddress = $consent->ipAddress();
        $entity->userAgent = $consent->userAgent();
        $entity->consentText = $consent->consentText();
        $entity->consentVersion = $consent->consentVersion();
        $entity->grantedAt = $consent->grantedAt();
        $entity->withdrawnAt = $consent->withdrawnAt();
        $entity->createdAt = $consent->createdAt();
        $entity->updatedAt = $consent->updatedAt();

        return $entity;
    }

    public function toDomainModel(): Consent
    {
        return Consent::reconstituteFromPersistence(
            ConsentId::fromString($this->id),
            TenantId::fromString($this->tenantId),
            CustomerId::fromString($this->customerId),
            ConsentPurpose::fromString($this->purpose),
            $this->isGranted,
            $this->ipAddress,
            $this->userAgent,
            $this->consentText,
            $this->consentVersion,
            $this->grantedAt,
            $this->withdrawnAt,
            $this->createdAt,
            $this->updatedAt
        );
    }

    public function updateFromDomainModel(Consent $consent): void
    {
        $this->isGranted = $consent->isGranted();
        $this->withdrawnAt = $consent->withdrawnAt();
        $this->updatedAt = $consent->updatedAt();
    }

    // Getters for API Platform serialization
    public function getId(): string
    {
        return $this->id;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function getCustomerId(): string
    {
        return $this->customerId;
    }

    public function getPurpose(): string
    {
        return $this->purpose;
    }

    public function isGranted(): bool
    {
        return $this->isGranted;
    }

    public function getIpAddress(): string
    {
        return $this->ipAddress;
    }

    public function getUserAgent(): string
    {
        return $this->userAgent;
    }

    public function getConsentText(): string
    {
        return $this->consentText;
    }

    public function getConsentVersion(): string
    {
        return $this->consentVersion;
    }

    public function getGrantedAt(): ?\DateTimeImmutable
    {
        return $this->grantedAt;
    }

    public function getWithdrawnAt(): ?\DateTimeImmutable
    {
        return $this->withdrawnAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
