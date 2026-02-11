<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Schedule\Message;

/**
 * Scheduled Message: Process Payment Retries.
 *
 * This message is dispatched by the Symfony Scheduler every 5 minutes
 * to trigger processing of payments that are due for retry.
 *
 * The message handler will:
 * 1. Find all payments with status=failed AND next_retry_at <= now
 * 2. Attempt to reprocess each payment through the gateway
 * 3. Update payment status based on result
 * 4. Schedule next retry or mark as exhausted
 *
 * This is a trigger message - it carries no data, just signals that
 * the retry processing job should run.
 */
final readonly class ProcessPaymentRetries
{
    public function __construct()
    {
    }
}
