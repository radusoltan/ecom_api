<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Application\Service;

use App\Payment\Application\Service\WebhookDeduplicationService;
use App\Payment\Domain\Model\WebhookEvent;
use App\Payment\Domain\Repository\WebhookEventRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class WebhookDeduplicationServiceTest extends TestCase
{
    private WebhookDeduplicationService $service;
    private WebhookEventRepositoryInterface&MockObject $repository;
    private LoggerInterface&MockObject $logger;

    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(WebhookEventRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $this->service = new WebhookDeduplicationService(
            $this->repository,
            $this->logger,
        );
    }

    #[Test]
    public function newWebhookIsProcessedAndRecordCreated(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('existsByGatewayAndExternalEventId')
            ->with('stripe', 'evt_123abc')
            ->willReturn(false);

        $this->repository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(fn (WebhookEvent $event) => 'stripe' === $event->gateway()
                    && 'evt_123abc' === $event->externalEventId()
                    && 'payment_intent.succeeded' === $event->eventType()
            ));

        $result = $this->service->isDuplicate(
            gateway: 'stripe',
            externalEventId: 'evt_123abc',
            eventType: 'payment_intent.succeeded',
            tenantId: $this->tenantId,
            payload: '{"id":"evt_123abc"}',
        );

        $this->assertFalse($result);
    }

    #[Test]
    public function duplicateWebhookIsAcknowledgedWithoutProcessing(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('existsByGatewayAndExternalEventId')
            ->with('stripe', 'evt_already_processed')
            ->willReturn(true);

        // save() must NOT be called for a duplicate
        $this->repository
            ->expects($this->never())
            ->method('save');

        $result = $this->service->isDuplicate(
            gateway: 'stripe',
            externalEventId: 'evt_already_processed',
            eventType: 'payment_intent.succeeded',
            tenantId: $this->tenantId,
        );

        $this->assertTrue($result);
    }

    #[Test]
    public function sameExternalIdDifferentGatewayIsProcessed(): void
    {
        // Stripe event with evt_shared_id was already processed
        $this->repository
            ->expects($this->once())
            ->method('existsByGatewayAndExternalEventId')
            ->with('paypal', 'evt_shared_id')
            ->willReturn(false);

        $this->repository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(fn (WebhookEvent $event) => 'paypal' === $event->gateway()
                    && 'evt_shared_id' === $event->externalEventId()
            ));

        $result = $this->service->isDuplicate(
            gateway: 'paypal',
            externalEventId: 'evt_shared_id',
            eventType: 'PAYMENT.CAPTURE.COMPLETED',
            tenantId: $this->tenantId,
        );

        $this->assertFalse($result);
    }

    #[Test]
    public function payloadHashIsComputedWhenPayloadProvided(): void
    {
        $payload = '{"id":"evt_hash_test","type":"payment_intent.succeeded"}';
        $expectedHash = hash('sha256', $payload);

        $this->repository
            ->method('existsByGatewayAndExternalEventId')
            ->willReturn(false);

        $this->repository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(fn (WebhookEvent $event) => $expectedHash === $event->payloadHash()
            ));

        $this->service->isDuplicate(
            gateway: 'stripe',
            externalEventId: 'evt_hash_test',
            eventType: 'payment_intent.succeeded',
            tenantId: $this->tenantId,
            payload: $payload,
        );
    }

    #[Test]
    public function payloadHashIsNullWhenNoPayloadProvided(): void
    {
        $this->repository
            ->method('existsByGatewayAndExternalEventId')
            ->willReturn(false);

        $this->repository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(fn (WebhookEvent $event) => null === $event->payloadHash()
            ));

        $this->service->isDuplicate(
            gateway: 'stripe',
            externalEventId: 'evt_no_payload',
            eventType: 'payment_intent.succeeded',
            tenantId: $this->tenantId,
        );
    }
}
