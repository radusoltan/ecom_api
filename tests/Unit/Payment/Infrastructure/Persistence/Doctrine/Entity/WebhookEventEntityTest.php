<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Infrastructure\Persistence\Doctrine\Entity;

use App\Payment\Domain\Model\WebhookEvent;
use App\Payment\Domain\Model\WebhookEventId;
use App\Payment\Infrastructure\Persistence\Doctrine\Entity\WebhookEventEntity;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class WebhookEventEntityTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';
    private const EVENT_UUID = '550e8400-e29b-41d4-a716-446655440000';
    private const VALID_HASH = 'a3f5b2c1d4e6f7890abc123def456789a3f5b2c1d4e6f7890abc123def456789';

    private function makeWebhookEvent(
        ?string $payloadHash = null,
    ): WebhookEvent {
        return WebhookEvent::reconstituteFromPersistence(
            id: WebhookEventId::fromString(self::EVENT_UUID),
            tenantId: TenantId::fromString(self::TENANT_ID),
            gateway: 'stripe',
            externalEventId: 'evt_001_external',
            eventType: 'payment_intent.succeeded',
            processedAt: new \DateTimeImmutable('2024-05-01T10:00:00+00:00'),
            payloadHash: $payloadHash,
        );
    }

    public function testFromDomainModelRoundtrip(): void
    {
        $webhookEvent = $this->makeWebhookEvent(self::VALID_HASH);

        $entity = WebhookEventEntity::fromDomainModel($webhookEvent);

        self::assertSame(self::EVENT_UUID, $entity->getId());
        self::assertSame(self::TENANT_ID, $entity->getTenantId());
        self::assertSame('stripe', $entity->getGateway());
        self::assertSame('evt_001_external', $entity->getExternalEventId());
        self::assertSame('payment_intent.succeeded', $entity->getEventType());
        self::assertSame('2024-05-01T10:00:00+00:00', $entity->getProcessedAt()->format(\DateTimeInterface::ATOM));
        self::assertSame(self::VALID_HASH, $entity->getPayloadHash());
    }

    public function testToDomainModelRestoresAllFields(): void
    {
        $webhookEvent = $this->makeWebhookEvent(self::VALID_HASH);

        $entity = WebhookEventEntity::fromDomainModel($webhookEvent);
        $restored = $entity->toDomainModel();

        self::assertSame(self::EVENT_UUID, $restored->id()->toString());
        self::assertSame(self::TENANT_ID, $restored->tenantId()->toString());
        self::assertSame('stripe', $restored->gateway());
        self::assertSame('evt_001_external', $restored->externalEventId());
        self::assertSame('payment_intent.succeeded', $restored->eventType());
        self::assertSame('2024-05-01T10:00:00+00:00', $restored->processedAt()->format(\DateTimeInterface::ATOM));
        self::assertSame(self::VALID_HASH, $restored->payloadHash());
    }

    public function testFromDomainModelWithNullPayloadHash(): void
    {
        $webhookEvent = $this->makeWebhookEvent(null);

        $entity = WebhookEventEntity::fromDomainModel($webhookEvent);

        self::assertNull($entity->getPayloadHash());
    }

    public function testToDomainModelWithNullPayloadHash(): void
    {
        $webhookEvent = $this->makeWebhookEvent(null);

        $entity = WebhookEventEntity::fromDomainModel($webhookEvent);
        $restored = $entity->toDomainModel();

        self::assertNull($restored->payloadHash());
    }

    public function testFromDomainModelDifferentGateways(): void
    {
        $gateways = ['stripe', 'paypal', '2checkout'];

        foreach ($gateways as $gateway) {
            $webhookEvent = WebhookEvent::reconstituteFromPersistence(
                id: WebhookEventId::fromString(self::EVENT_UUID),
                tenantId: TenantId::fromString(self::TENANT_ID),
                gateway: $gateway,
                externalEventId: 'ext-event-'.$gateway,
                eventType: 'payment.completed',
                processedAt: new \DateTimeImmutable(),
                payloadHash: null,
            );

            $entity = WebhookEventEntity::fromDomainModel($webhookEvent);

            self::assertSame($gateway, $entity->getGateway());
        }
    }

    public function testGettersReturnCorrectTypes(): void
    {
        $webhookEvent = $this->makeWebhookEvent();

        $entity = WebhookEventEntity::fromDomainModel($webhookEvent);

        self::assertIsString($entity->getId());
        self::assertIsString($entity->getTenantId());
        self::assertIsString($entity->getGateway());
        self::assertIsString($entity->getExternalEventId());
        self::assertIsString($entity->getEventType());
        self::assertInstanceOf(\DateTimeImmutable::class, $entity->getProcessedAt());
        self::assertNull($entity->getPayloadHash());
    }

    public function testIdAndTenantGettersMatchDomain(): void
    {
        $webhookEvent = $this->makeWebhookEvent();
        $entity = WebhookEventEntity::fromDomainModel($webhookEvent);

        self::assertSame($webhookEvent->id()->toString(), $entity->getId());
        self::assertSame($webhookEvent->tenantId()->toString(), $entity->getTenantId());
    }

    public function testProcessedAtPreservedExactly(): void
    {
        $processedAt = new \DateTimeImmutable('2025-12-31T23:59:59+00:00');
        $webhookEvent = WebhookEvent::reconstituteFromPersistence(
            id: WebhookEventId::fromString(self::EVENT_UUID),
            tenantId: TenantId::fromString(self::TENANT_ID),
            gateway: 'stripe',
            externalEventId: 'evt_xyz',
            eventType: 'refund.created',
            processedAt: $processedAt,
            payloadHash: null,
        );

        $entity = WebhookEventEntity::fromDomainModel($webhookEvent);

        self::assertSame(
            '2025-12-31T23:59:59+00:00',
            $entity->getProcessedAt()->format(\DateTimeInterface::ATOM)
        );
    }
}
