<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Schedule\MessageHandler;

use App\Payment\Application\Service\PaymentRetryService;
use App\Payment\Infrastructure\Schedule\Message\ProcessPaymentRetries;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Process Payment Retries Handler.
 *
 * Handles the scheduled message to process payment retries.
 * This handler is invoked every 5 minutes by the Symfony Scheduler.
 *
 * Workflow:
 * 1. Get all payments due for retry from repository
 * 2. Process each payment retry through PaymentRetryService
 * 3. Log results for monitoring
 * 4. Continue processing even if individual retries fail
 *
 * Error Handling:
 * - Individual payment retry failures are logged but don't stop processing
 * - Critical errors are logged and re-thrown
 * - Ensures all due retries are attempted even if some fail
 */
#[AsMessageHandler]
final readonly class ProcessPaymentRetriesHandler
{
    public function __construct(
        private PaymentRetryService $retryService,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(ProcessPaymentRetries $message): void
    {
        try {
            $this->logger->info('Starting scheduled payment retry processing');

            // Get payments due for retry
            $now = new \DateTimeImmutable();
            $payments = $this->retryService->getPaymentsDueForRetry($now);

            if (0 === count($payments)) {
                $this->logger->info('No payments due for retry');

                return;
            }

            $this->logger->info('Found payments due for retry', [
                'count' => count($payments),
            ]);

            // Process each payment
            $successCount = 0;
            $failureCount = 0;

            foreach ($payments as $payment) {
                try {
                    $wasProcessed = $this->retryService->processRetry($payment);

                    if ($wasProcessed) {
                        ++$successCount;
                    } else {
                        ++$failureCount;
                    }
                } catch (\Throwable $e) {
                    ++$failureCount;
                    $this->logger->error('Failed to process payment retry', [
                        'payment_id' => $payment->id()->toString(),
                        'error' => $e->getMessage(),
                    ]);
                    // Continue processing other payments
                }
            }

            $this->logger->info('Completed scheduled payment retry processing', [
                'total' => count($payments),
                'successful' => $successCount,
                'failed' => $failureCount,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Scheduled payment retry processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
