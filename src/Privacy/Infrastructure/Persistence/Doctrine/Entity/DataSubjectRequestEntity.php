<?php

declare(strict_types=1);

namespace App\Privacy\Infrastructure\Persistence\Doctrine\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Privacy\Domain\Model\DataSubjectRequest;
use App\Privacy\Domain\ValueObject\DataSubjectRequestId;
use App\Privacy\Domain\ValueObject\RequestStatus;
use App\Privacy\Domain\ValueObject\RequestType;
use App\Privacy\Presentation\Api\Processor\CompleteDataSubjectRequestProcessor;
use App\Privacy\Presentation\Api\Processor\ExtendRequestDeadlineProcessor;
use App\Privacy\Presentation\Api\Processor\RejectDataSubjectRequestProcessor;
use App\Privacy\Presentation\Api\Processor\ReviewDataSubjectRequestProcessor;
use App\Privacy\Presentation\Api\Processor\SubmitDataSubjectRequestProcessor;
use App\Privacy\Presentation\Api\Provider\DataSubjectRequestCollectionProvider;
use App\Privacy\Presentation\Api\Provider\DataSubjectRequestItemProvider;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'data_subject_requests')]
#[ORM\Index(columns: ['customer_id'], name: 'idx_dsr_customer')]
#[ORM\Index(columns: ['tenant_id'], name: 'idx_dsr_tenant')]
#[ORM\Index(columns: ['status'], name: 'idx_dsr_status')]
#[ORM\Index(columns: ['request_type'], name: 'idx_dsr_type')]
#[ORM\Index(columns: ['deadline', 'status'], name: 'idx_dsr_deadline_status')]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/data-subject-requests',
            provider: DataSubjectRequestCollectionProvider::class
        ),
        new Get(
            uriTemplate: '/data-subject-requests/{id}',
            provider: DataSubjectRequestItemProvider::class
        ),
        new Post(
            uriTemplate: '/data-subject-requests',
            processor: SubmitDataSubjectRequestProcessor::class
        ),
        new Patch(
            uriTemplate: '/data-subject-requests/{id}/review',
            processor: ReviewDataSubjectRequestProcessor::class
        ),
        new Patch(
            uriTemplate: '/data-subject-requests/{id}/complete',
            processor: CompleteDataSubjectRequestProcessor::class
        ),
        new Patch(
            uriTemplate: '/data-subject-requests/{id}/reject',
            processor: RejectDataSubjectRequestProcessor::class
        ),
        new Patch(
            uriTemplate: '/data-subject-requests/{id}/extend',
            processor: ExtendRequestDeadlineProcessor::class
        ),
    ]
)]
class DataSubjectRequestEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'data_subject_request_id', length: 26)]
    private string $id;

    #[ORM\Column(type: 'tenant_id', length: 26)]
    private string $tenantId;

    #[ORM\Column(type: 'customer_id', length: 26)]
    private string $customerId;

    #[ORM\Column(type: 'request_type', length: 50)]
    private string $requestType;

    #[ORM\Column(type: 'request_status', length: 50)]
    private string $status;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $reason;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $reviewNotes;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $rejectionReason;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $exportData;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $submittedAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $completedAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $deadline;

    #[ORM\Column(type: 'boolean')]
    private bool $isExtended;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public static function fromDomainModel(DataSubjectRequest $request): self
    {
        $entity = new self();
        $entity->id = $request->id()->toString();
        $entity->tenantId = $request->tenantId()->toString();
        $entity->customerId = $request->customerId()->toString();
        $entity->requestType = $request->requestType()->value();
        $entity->status = $request->status()->value();
        $entity->reason = $request->reason();
        $entity->reviewNotes = $request->reviewNotes();
        $entity->rejectionReason = $request->rejectionReason();
        $entity->exportData = $request->exportData();
        $entity->submittedAt = $request->submittedAt();
        $entity->completedAt = $request->completedAt();
        $entity->deadline = $request->deadline();
        $entity->isExtended = $request->isExtended();
        $entity->createdAt = $request->createdAt();
        $entity->updatedAt = $request->updatedAt();

        return $entity;
    }

    public function toDomainModel(): DataSubjectRequest
    {
        return DataSubjectRequest::reconstituteFromPersistence(
            DataSubjectRequestId::fromString($this->id),
            TenantId::fromString($this->tenantId),
            CustomerId::fromString($this->customerId),
            RequestType::fromString($this->requestType),
            RequestStatus::fromString($this->status),
            $this->reason,
            $this->reviewNotes,
            $this->rejectionReason,
            $this->exportData,
            $this->submittedAt,
            $this->completedAt,
            $this->deadline,
            $this->isExtended,
            $this->createdAt,
            $this->updatedAt
        );
    }

    public function updateFromDomainModel(DataSubjectRequest $request): void
    {
        $this->status = $request->status()->value();
        $this->reviewNotes = $request->reviewNotes();
        $this->rejectionReason = $request->rejectionReason();
        $this->exportData = $request->exportData();
        $this->completedAt = $request->completedAt();
        $this->deadline = $request->deadline();
        $this->isExtended = $request->isExtended();
        $this->updatedAt = $request->updatedAt();
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

    public function getRequestType(): string
    {
        return $this->requestType;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function getReviewNotes(): ?string
    {
        return $this->reviewNotes;
    }

    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function getExportData(): ?array
    {
        return $this->exportData;
    }

    public function getSubmittedAt(): \DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function getDeadline(): \DateTimeImmutable
    {
        return $this->deadline;
    }

    public function isExtended(): bool
    {
        return $this->isExtended;
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
