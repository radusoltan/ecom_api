<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Persistence\Doctrine\Entity;

use App\Payment\Domain\Model\WebhookEvent;
use App\Payment\Domain\Model\WebhookEventId;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'payment_webhook_events')]
#[ORM\UniqueConstraint(name: 'idx_payment_webhook_events_dedup', columns: ['gateway', 'external_event_id'])]
#[ORM\Index(name: 'idx_payment_webhook_events_tenant_id', columns: ['tenant_id'])]
#[ORM\Index(name: 'idx_payment_webhook_events_processed_at', columns: ['processed_at'])]
class WebhookEventEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private string $id;

    #[ORM\Column(type: 'uuid', nullable: false, name: 'tenant_id')]
    private string $tenantId;

    #[ORM\Column(type: 'string', length: 50, nullable: false)]
    private string $gateway;

    #[ORM\Column(type: 'string', length: 255, nullable: false, name: 'external_event_id')]
    private string $externalEventId;

    #[ORM\Column(type: 'string', length: 100, nullable: false, name: 'event_type')]
    private string $eventType;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: false, name: 'processed_at')]
    private \DateTimeImmutable $processedAt;

    #[ORM\Column(type: 'string', length: 64, nullable: true, name: 'payload_hash')]
    private ?string $payloadHash = null;

    public static function fromDomainModel(WebhookEvent $webhookEvent): self
    {
        $entity = new self();
        $entity->id = $webhookEvent->id()->toString();
        $entity->tenantId = $webhookEvent->tenantId()->toString();
        $entity->gateway = $webhookEvent->gateway();
        $entity->externalEventId = $webhookEvent->externalEventId();
        $entity->eventType = $webhookEvent->eventType();
        $entity->processedAt = $webhookEvent->processedAt();
        $entity->payloadHash = $webhookEvent->payloadHash();

        return $entity;
    }

    public function toDomainModel(): WebhookEvent
    {
        return WebhookEvent::reconstituteFromPersistence(
            id: WebhookEventId::fromString($this->id),
            tenantId: TenantId::fromString($this->tenantId),
            gateway: $this->gateway,
            externalEventId: $this->externalEventId,
            eventType: $this->eventType,
            processedAt: $this->processedAt,
            payloadHash: $this->payloadHash,
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function getGateway(): string
    {
        return $this->gateway;
    }

    public function getExternalEventId(): string
    {
        return $this->externalEventId;
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    public function getProcessedAt(): \DateTimeImmutable
    {
        return $this->processedAt;
    }

    public function getPayloadHash(): ?string
    {
        return $this->payloadHash;
    }
}
