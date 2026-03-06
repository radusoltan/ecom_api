<?php

declare(strict_types=1);

namespace App\Payment\Application\Service;

use App\Payment\Domain\Model\WebhookEvent;
use App\Payment\Domain\Repository\WebhookEventRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use Psr\Log\LoggerInterface;

/**
 * Webhook Deduplication Service.
 *
 * Prevents duplicate processing of webhook events from payment gateways.
 * When a gateway retries a webhook (e.g. Stripe timeout), this service
 * detects the duplicate and skips processing.
 *
 * Usage: Call isDuplicate() BEFORE processing. If false, the event is
 * automatically recorded so subsequent calls return true.
 */
final readonly class WebhookDeduplicationService
{
    public function __construct(
        private WebhookEventRepositoryInterface $webhookEventRepository,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Check if a webhook event is a duplicate and record it if not.
     *
     * @param string      $gateway         The payment gateway identifier (e.g. 'stripe', 'paypal')
     * @param string      $externalEventId The gateway's unique event identifier
     * @param string      $eventType       The type of webhook event (e.g. 'payment_intent.succeeded')
     * @param TenantId    $tenantId        The tenant context
     * @param string|null $payload         Raw webhook payload for hash computation
     *
     * @return bool True if this is a duplicate (already processed), false if new
     */
    public function isDuplicate(
        string $gateway,
        string $externalEventId,
        string $eventType,
        TenantId $tenantId,
        ?string $payload = null,
    ): bool {
        // Check if already processed
        if ($this->webhookEventRepository->existsByGatewayAndExternalEventId($gateway, $externalEventId)) {
            $this->logger->info('Webhook event deduplicated (skipping)', [
                'gateway' => $gateway,
                'external_event_id' => $externalEventId,
                'event_type' => $eventType,
            ]);

            return true;
        }

        // Record the event so future duplicates are caught
        $payloadHash = null !== $payload && '' !== $payload ? hash('sha256', $payload) : null;

        $webhookEvent = WebhookEvent::create(
            tenantId: $tenantId,
            gateway: $gateway,
            externalEventId: $externalEventId,
            eventType: $eventType,
            payloadHash: $payloadHash,
        );

        $this->webhookEventRepository->save($webhookEvent);

        $this->logger->info('Webhook event recorded for deduplication', [
            'gateway' => $gateway,
            'external_event_id' => $externalEventId,
            'event_type' => $eventType,
        ]);

        return false;
    }
}
