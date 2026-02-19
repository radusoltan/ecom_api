<?php

declare(strict_types=1);

namespace App\Payment\Domain\Model;

use App\Shared\Domain\ValueObject\TenantId;

/**
 * Webhook Event record for deduplication.
 *
 * Tracks processed webhook events from payment gateways (Stripe, PayPal, 2Checkout)
 * to prevent duplicate processing when gateways retry on timeout.
 *
 * This is NOT an aggregate root - it's a simple persistence record used
 * for idempotency checking. No domain events are raised.
 */
final readonly class WebhookEvent
{
    private function __construct(
        private WebhookEventId $id,
        private TenantId $tenantId,
        private string $gateway,
        private string $externalEventId,
        private string $eventType,
        private \DateTimeImmutable $processedAt,
        private ?string $payloadHash,
    ) {
    }

    public static function create(
        TenantId $tenantId,
        string $gateway,
        string $externalEventId,
        string $eventType,
        ?string $payloadHash = null,
    ): self {
        if ('' === $gateway) {
            throw new \InvalidArgumentException('Gateway cannot be empty.');
        }

        if ('' === $externalEventId) {
            throw new \InvalidArgumentException('External event ID cannot be empty.');
        }

        if ('' === $eventType) {
            throw new \InvalidArgumentException('Event type cannot be empty.');
        }

        if (null !== $payloadHash && 64 !== strlen($payloadHash)) {
            throw new \InvalidArgumentException('Payload hash must be a 64-character SHA-256 hex string.');
        }

        return new self(
            id: WebhookEventId::generate(),
            tenantId: $tenantId,
            gateway: strtolower($gateway),
            externalEventId: $externalEventId,
            eventType: $eventType,
            processedAt: new \DateTimeImmutable(),
            payloadHash: $payloadHash,
        );
    }

    public static function reconstituteFromPersistence(
        WebhookEventId $id,
        TenantId $tenantId,
        string $gateway,
        string $externalEventId,
        string $eventType,
        \DateTimeImmutable $processedAt,
        ?string $payloadHash,
    ): self {
        return new self(
            id: $id,
            tenantId: $tenantId,
            gateway: $gateway,
            externalEventId: $externalEventId,
            eventType: $eventType,
            processedAt: $processedAt,
            payloadHash: $payloadHash,
        );
    }

    public function id(): WebhookEventId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function gateway(): string
    {
        return $this->gateway;
    }

    public function externalEventId(): string
    {
        return $this->externalEventId;
    }

    public function eventType(): string
    {
        return $this->eventType;
    }

    public function processedAt(): \DateTimeImmutable
    {
        return $this->processedAt;
    }

    public function payloadHash(): ?string
    {
        return $this->payloadHash;
    }
}
