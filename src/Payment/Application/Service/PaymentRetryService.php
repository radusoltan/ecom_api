<?php

declare(strict_types=1);

namespace App\Payment\Application\Service;

use App\Payment\Domain\Model\Payment;
use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use App\Payment\Domain\ValueObject\RetryPolicy;
use Psr\Log\LoggerInterface;

/**
 * Payment Retry Service.
 *
 * Orchestrates payment retry logic:
 * - Determines if a failed payment should be retried
 * - Schedules retry attempts with exponential backoff
 * - Tracks retry count and next retry time
 * - Marks retries as exhausted when max attempts reached
 *
 * Business Rules:
 * - Maximum 3 retry attempts
 * - Exponential backoff: 1h, 4h, 24h
 * - Only retry transient errors
 * - Stop retrying after max attempts
 */
final readonly class PaymentRetryService
{
    private RetryPolicy $retryPolicy;

    public function __construct(
        private PaymentRepositoryInterface $paymentRepository,
        private LoggerInterface $logger,
    ) {
        $this->retryPolicy = RetryPolicy::default();
    }

    /**
     * Schedule a retry for a failed payment.
     *
     * @param Payment $payment Failed payment to retry
     *
     * @return \DateTimeImmutable|null Next retry time, or null if retry not scheduled
     */
    public function scheduleRetry(Payment $payment): ?\DateTimeImmutable
    {
        // Check if payment should be retried
        if (!$this->shouldRetry($payment)) {
            $this->logger->info('Payment not eligible for retry', [
                'payment_id' => $payment->id()->toString(),
                'retry_count' => $payment->retryCount(),
                'error_code' => $payment->errorCode(),
            ]);

            return null;
        }

        try {
            // Schedule the retry
            $payment->scheduleRetry($this->retryPolicy);
            $this->paymentRepository->save($payment);

            $nextRetryAt = $payment->nextRetryAt();

            $this->logger->info('Payment retry scheduled', [
                'payment_id' => $payment->id()->toString(),
                'retry_count' => $payment->retryCount(),
                'next_retry_at' => $nextRetryAt?->format('Y-m-d H:i:s'),
            ]);

            return $nextRetryAt;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to schedule payment retry', [
                'payment_id' => $payment->id()->toString(),
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Determine if a payment should be retried.
     *
     * @param Payment $payment Payment to check
     *
     * @return bool True if payment should be retried
     */
    public function shouldRetry(Payment $payment): bool
    {
        // Payment must be in failed status
        if (!$payment->status()->isFailed()) {
            return false;
        }

        // Check if error code is retryable
        $errorCode = $payment->errorCode();
        if (null === $errorCode || !$this->retryPolicy->isRetryable($errorCode)) {
            $this->logger->info('Error not retryable', [
                'payment_id' => $payment->id()->toString(),
                'error_code' => $errorCode,
            ]);

            return false;
        }

        // Check if max attempts reached
        if ($payment->retryCount() >= $this->retryPolicy->maxAttempts()) {
            $this->logger->info('Max retry attempts reached', [
                'payment_id' => $payment->id()->toString(),
                'retry_count' => $payment->retryCount(),
                'max_attempts' => $this->retryPolicy->maxAttempts(),
            ]);

            return false;
        }

        return true;
    }

    /**
     * Process a retry for a payment.
     *
     * This method attempts to reprocess the payment through the gateway.
     * It should be called by the console command when a retry is due.
     *
     * @param Payment $payment Payment to retry
     *
     * @return bool True if retry was attempted (regardless of success)
     */
    public function processRetry(Payment $payment): bool
    {
        try {
            // Check if payment is due for retry
            if (!$payment->isDueForRetry()) {
                $this->logger->warning('Payment not due for retry yet', [
                    'payment_id' => $payment->id()->toString(),
                    'next_retry_at' => $payment->nextRetryAt()?->format('Y-m-d H:i:s'),
                ]);

                return false;
            }

            $this->logger->info('Processing payment retry', [
                'payment_id' => $payment->id()->toString(),
                'attempt_number' => $payment->retryCount() + 1,
            ]);

            // TODO: Actual payment gateway retry logic would go here
            // For now, this is a placeholder that simulates retry
            // In real implementation:
            // 1. Call payment gateway to retry authorization/capture
            // 2. Handle gateway response
            // 3. Update payment based on result

            // Simulate retry result (for demonstration)
            $wasSuccessful = false; // This would come from gateway response
            $errorCode = $payment->errorCode(); // This would come from gateway
            $errorMessage = $payment->errorMessage(); // This would come from gateway

            // Record the retry attempt
            $payment->recordRetryAttempt($wasSuccessful, $errorCode, $errorMessage);

            // If retry failed and max attempts reached, mark as exhausted
            // @phpstan-ignore-next-line booleanNot.alwaysTrue (placeholder until gateway integration)
            if (!$wasSuccessful && $payment->retryCount() >= $this->retryPolicy->maxAttempts()) {
                $payment->markRetryExhausted($this->retryPolicy);

                $this->logger->warning('Payment retry exhausted', [
                    'payment_id' => $payment->id()->toString(),
                    'total_attempts' => $payment->retryCount(),
                ]);
            // @phpstan-ignore-next-line booleanNot.alwaysTrue (placeholder until gateway integration)
            } elseif (!$wasSuccessful) {
                // Schedule next retry
                $this->scheduleRetry($payment);
            } else {
                // Retry succeeded - update payment status
                // TODO: Transition payment to authorized or captured based on gateway response
                $this->logger->info('Payment retry successful', [
                    'payment_id' => $payment->id()->toString(),
                    'attempt_number' => $payment->retryCount(),
                ]);
            }

            $this->paymentRepository->save($payment);

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to process payment retry', [
                'payment_id' => $payment->id()->toString(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Get all payments that are due for retry.
     *
     * @param \DateTimeImmutable|null $now Current time (for testing)
     *
     * @return array<Payment> Payments due for retry
     */
    public function getPaymentsDueForRetry(?\DateTimeImmutable $now = null): array
    {
        $now = $now ?? new \DateTimeImmutable();

        // TODO: This should use a repository method to efficiently query payments
        // For now, we'll document the expected query:
        // SELECT * FROM payments
        // WHERE status = 'failed'
        //   AND next_retry_at IS NOT NULL
        //   AND next_retry_at <= :now
        //   AND retry_count < :max_attempts

        return $this->paymentRepository->findPaymentsForRetry($now);
    }
}
