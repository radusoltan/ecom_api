<?php

declare(strict_types=1);

namespace App\Notifications\Infrastructure\Persistence\Doctrine\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Notifications\Domain\Model\Notification;
use App\Notifications\Domain\Model\NotificationId;
use App\Notifications\Domain\Model\NotificationStatus;
use App\Notifications\Domain\Model\NotificationType;
use App\Notifications\Infrastructure\ApiPlatform\State\NotificationCollectionProvider;
use App\Notifications\Infrastructure\ApiPlatform\State\NotificationItemProvider;
use App\Notifications\Infrastructure\ApiPlatform\State\RetryNotificationProcessor;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * NotificationEntity - Doctrine ORM adapter for Notification aggregate.
 *
 * Dual-Model Pattern: This entity is the infrastructure adapter,
 * separate from the pure domain model.
 */
#[ORM\Entity]
#[ORM\Table(name: 'notifications')]
#[ORM\Index(name: 'idx_notifications_tenant_id', columns: ['tenant_id'])]
#[ORM\Index(name: 'idx_notifications_status', columns: ['status'])]
#[ORM\Index(name: 'idx_notifications_type', columns: ['type'])]
#[ORM\Index(name: 'idx_notifications_recipient_email', columns: ['recipient_email'])]
#[ORM\Index(name: 'idx_notifications_tenant_status', columns: ['tenant_id', 'status'])]
#[ORM\Index(name: 'idx_notifications_created_at', columns: ['created_at'])]
#[ApiResource(
    shortName: 'Notification',
    operations: [
        new GetCollection(
            uriTemplate: '/notifications',
            provider: NotificationCollectionProvider::class,
            security: "is_granted('ROLE_ADMIN')",
            paginationEnabled: true,
            paginationItemsPerPage: 30,
        ),
        new Get(
            uriTemplate: '/notifications/{id}',
            provider: NotificationItemProvider::class,
            security: "is_granted('ROLE_ADMIN')",
        ),
        new Post(
            uriTemplate: '/notifications/{id}/retry',
            processor: RetryNotificationProcessor::class,
            security: "is_granted('ROLE_ADMIN')",
            read: false,
            deserialize: false,
            validate: false,
        ),
    ],
    normalizationContext: ['groups' => ['notification:read']],
)]
class NotificationEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    #[Groups(['notification:read'])]
    private string $id;

    #[ORM\Column(type: 'string', length: 36, name: 'tenant_id')]
    #[Groups(['notification:read'])]
    private string $tenantId;

    #[ORM\Column(type: 'string', length: 20)]
    #[Groups(['notification:read'])]
    private string $type;

    #[ORM\Column(type: 'string', length: 255, nullable: true, name: 'recipient_email')]
    #[Groups(['notification:read'])]
    private ?string $recipientEmail = null;

    #[ORM\Column(type: 'string', length: 20, nullable: true, name: 'recipient_phone')]
    #[Groups(['notification:read'])]
    private ?string $recipientPhone = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['notification:read'])]
    private string $subject;

    #[ORM\Column(type: 'text')]
    #[Groups(['notification:read'])]
    private string $body;

    #[ORM\Column(type: 'string', length: 20)]
    #[Groups(['notification:read'])]
    private string $status;

    #[ORM\Column(type: 'integer', name: 'attempt_count')]
    #[Groups(['notification:read'])]
    private int $attemptCount = 0;

    #[ORM\Column(type: 'datetime_immutable', nullable: true, name: 'sent_at')]
    #[Groups(['notification:read'])]
    private ?\DateTimeImmutable $sentAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true, name: 'failed_at')]
    #[Groups(['notification:read'])]
    private ?\DateTimeImmutable $failedAt = null;

    #[ORM\Column(type: 'text', nullable: true, name: 'failure_reason')]
    #[Groups(['notification:read'])]
    private ?string $failureReason = null;

    #[ORM\Column(type: 'datetime_immutable', name: 'created_at')]
    #[Groups(['notification:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', name: 'updated_at')]
    #[Groups(['notification:read'])]
    private \DateTimeImmutable $updatedAt;

    /**
     * Convert domain model to entity (for INSERT).
     */
    public static function fromDomainModel(Notification $notification): self
    {
        $entity = new self();
        $entity->id = $notification->id()->toString();
        $entity->tenantId = $notification->tenantId()->toString();
        $entity->type = $notification->type()->value;
        $entity->recipientEmail = $notification->recipientEmail();
        $entity->recipientPhone = $notification->recipientPhone();
        $entity->subject = $notification->subject();
        $entity->body = $notification->body();
        $entity->status = $notification->status()->value;
        $entity->attemptCount = $notification->attemptCount();
        $entity->sentAt = $notification->sentAt();
        $entity->failedAt = $notification->failedAt();
        $entity->failureReason = $notification->failureReason();
        $entity->createdAt = $notification->createdAt();
        $entity->updatedAt = $notification->updatedAt();

        return $entity;
    }

    /**
     * Update entity from domain model (for UPDATE).
     */
    public function updateFromDomainModel(Notification $notification): void
    {
        // Note: id and tenantId should never change
        $this->type = $notification->type()->value;
        $this->recipientEmail = $notification->recipientEmail();
        $this->recipientPhone = $notification->recipientPhone();
        $this->subject = $notification->subject();
        $this->body = $notification->body();
        $this->status = $notification->status()->value;
        $this->attemptCount = $notification->attemptCount();
        $this->sentAt = $notification->sentAt();
        $this->failedAt = $notification->failedAt();
        $this->failureReason = $notification->failureReason();
        $this->updatedAt = $notification->updatedAt();
    }

    /**
     * Convert entity to domain model.
     */
    public function toDomainModel(): Notification
    {
        return Notification::reconstituteFromPersistence(
            id: NotificationId::fromString($this->id),
            tenantId: TenantId::fromString($this->tenantId),
            type: NotificationType::from($this->type),
            recipientEmail: $this->recipientEmail,
            recipientPhone: $this->recipientPhone,
            subject: $this->subject,
            body: $this->body,
            status: NotificationStatus::from($this->status),
            attemptCount: $this->attemptCount,
            sentAt: $this->sentAt,
            failedAt: $this->failedAt,
            failureReason: $this->failureReason,
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt
        );
    }

    // Getters (for Doctrine)
    public function getId(): string
    {
        return $this->id;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getRecipientEmail(): ?string
    {
        return $this->recipientEmail;
    }

    public function getRecipientPhone(): ?string
    {
        return $this->recipientPhone;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getAttemptCount(): int
    {
        return $this->attemptCount;
    }

    public function getSentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function getFailedAt(): ?\DateTimeImmutable
    {
        return $this->failedAt;
    }

    public function getFailureReason(): ?string
    {
        return $this->failureReason;
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
