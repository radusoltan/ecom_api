<?php

declare(strict_types=1);

namespace App\Payment\Domain\Repository;

use App\Payment\Domain\Model\WebhookEvent;

/**
 * Webhook Event Repository Interface (Port).
 *
 * Persistence operations for webhook event deduplication records.
 * Used to check if a webhook has already been processed before
 * allowing duplicate processing from gateway retries.
 */
interface WebhookEventRepositoryInterface
{
    /**
     * Check if a webhook event has already been processed.
     *
     * @param string $gateway         The payment gateway (e.g. 'stripe', 'paypal')
     * @param string $externalEventId The gateway's unique event identifier
     *
     * @return bool True if the event was already processed
     */
    public function existsByGatewayAndExternalEventId(string $gateway, string $externalEventId): bool;

    /**
     * Save a new webhook event record.
     */
    public function save(WebhookEvent $webhookEvent): void;

    /**
     * Delete webhook events older than the given date.
     *
     * Used by the cleanup job to prevent unbounded table growth.
     *
     * @param \DateTimeImmutable $before Delete events processed before this timestamp
     *
     * @return int Number of records deleted
     */
    public function deleteOlderThan(\DateTimeImmutable $before): int;
}
