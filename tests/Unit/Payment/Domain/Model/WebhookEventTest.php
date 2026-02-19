<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Domain\Model;

use App\Payment\Domain\Model\WebhookEvent;
use App\Payment\Domain\Model\WebhookEventId;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WebhookEventTest extends TestCase
{
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
    }

    #[Test]
    public function createSetsAllProperties(): void
    {
        $event = WebhookEvent::create(
            tenantId: $this->tenantId,
            gateway: 'stripe',
            externalEventId: 'evt_test_123',
            eventType: 'payment_intent.succeeded',
            payloadHash: hash('sha256', 'test'),
        );

        $this->assertInstanceOf(WebhookEventId::class, $event->id());
        $this->assertTrue($this->tenantId->equals($event->tenantId()));
        $this->assertSame('stripe', $event->gateway());
        $this->assertSame('evt_test_123', $event->externalEventId());
        $this->assertSame('payment_intent.succeeded', $event->eventType());
        $this->assertNotNull($event->processedAt());
        $this->assertSame(hash('sha256', 'test'), $event->payloadHash());
    }

    #[Test]
    public function createNormalizesGatewayToLowercase(): void
    {
        $event = WebhookEvent::create(
            tenantId: $this->tenantId,
            gateway: 'STRIPE',
            externalEventId: 'evt_1',
            eventType: 'test',
        );

        $this->assertSame('stripe', $event->gateway());
    }

    #[Test]
    public function createThrowsOnEmptyGateway(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Gateway cannot be empty');

        WebhookEvent::create(
            tenantId: $this->tenantId,
            gateway: '',
            externalEventId: 'evt_1',
            eventType: 'test',
        );
    }

    #[Test]
    public function createThrowsOnEmptyExternalEventId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('External event ID cannot be empty');

        WebhookEvent::create(
            tenantId: $this->tenantId,
            gateway: 'stripe',
            externalEventId: '',
            eventType: 'test',
        );
    }

    #[Test]
    public function createThrowsOnEmptyEventType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Event type cannot be empty');

        WebhookEvent::create(
            tenantId: $this->tenantId,
            gateway: 'stripe',
            externalEventId: 'evt_1',
            eventType: '',
        );
    }

    #[Test]
    public function createThrowsOnInvalidPayloadHash(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Payload hash must be a 64-character SHA-256');

        WebhookEvent::create(
            tenantId: $this->tenantId,
            gateway: 'stripe',
            externalEventId: 'evt_1',
            eventType: 'test',
            payloadHash: 'not-a-valid-hash',
        );
    }

    #[Test]
    public function createAllowsNullPayloadHash(): void
    {
        $event = WebhookEvent::create(
            tenantId: $this->tenantId,
            gateway: 'stripe',
            externalEventId: 'evt_1',
            eventType: 'test',
        );

        $this->assertNull($event->payloadHash());
    }

    #[Test]
    public function reconstituteFromPersistencePreservesAllData(): void
    {
        $id = WebhookEventId::generate();
        $processedAt = new \DateTimeImmutable('2026-01-15 10:30:00');
        $hash = hash('sha256', 'test-payload');

        $event = WebhookEvent::reconstituteFromPersistence(
            id: $id,
            tenantId: $this->tenantId,
            gateway: 'paypal',
            externalEventId: 'WH-12345',
            eventType: 'PAYMENT.CAPTURE.COMPLETED',
            processedAt: $processedAt,
            payloadHash: $hash,
        );

        $this->assertTrue($id->equals($event->id()));
        $this->assertSame('paypal', $event->gateway());
        $this->assertSame('WH-12345', $event->externalEventId());
        $this->assertSame('PAYMENT.CAPTURE.COMPLETED', $event->eventType());
        $this->assertSame($processedAt, $event->processedAt());
        $this->assertSame($hash, $event->payloadHash());
    }
}
