<?php

declare(strict_types=1);

namespace App\Payment\Application\Service;

use App\Payment\Domain\Model\Payment;
use App\Payment\Domain\Model\PaymentId as ModelPaymentId;
use App\Payment\Domain\Model\PaymentMethod as PaymentMethodEnum;
use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use App\Payment\Domain\Service\PaymentGatewayFactoryInterface;
use App\Payment\Domain\ValueObject\RetryPolicy;
use Psr\Log\LoggerInterface;

/**
 * Payment Retry Service.
 *
 * Orchestrates payment retry logic:
 * - Determines if a failed payment should be retried
 * - Schedules retry attempts with exponential backoff
 * - Calls the actual payment gateway to retry authorization
 * - Tracks retry count and next retry time
 * - Marks retries as exhausted when max attempts reached
 *
 * Business Rules:
 * - Maximum 3 retry attempts (configurable via RetryPolicy)
 * - Exponential backoff: 1h, 4h, 24h
 * - Only retry transient errors (card_declined, insufficient_funds, etc.)
 * - Never retry permanent errors (expired_card, fraudulent, etc.)
 * - Idempotency keys prevent duplicate charges on retry
 *
 * Domain Events Emitted:
 * - PaymentRetryAttempted: On every retry attempt (success or failure)
 * - PaymentRetryScheduled: When next retry is scheduled
 * - PaymentRetryExhausted: When all retry attempts have been exhausted
 * - PaymentAuthorized: When a retry succeeds and payment is authorized
 */
final readonly class PaymentRetryService
{
    private RetryPolicy $retryPolicy;

    public function __construct(
        private PaymentRepositoryInterface $paymentRepository,
        private PaymentGatewayFactoryInterface $gatewayFactory,
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
     * Calls the actual payment gateway to retry authorization:
     * 1. Validates payment is due for retry and still in failed status
     * 2. Resolves the correct gateway adapter (Stripe, PayPal)
     * 3. Creates a new payment intent with retry-specific idempotency key
     * 4. On success: authorizes payment with new gateway transaction ID
     * 5. On failure: records error, schedules next retry or marks exhausted
     *
     * @param Payment $payment Payment to retry
     *
     * @return bool True if retry was attempted (regardless of success)
     */
    public function processRetry(Payment $payment): bool
    {
        try {
            // Guard: payment must still be in failed status
            if (!$payment->status()->isFailed()) {
                $this->logger->info('Payment no longer in failed status, skipping retry', [
                    'payment_id' => $payment->id()->toString(),
                    'current_status' => $payment->status()->value(),
                ]);

                return false;
            }

            // Guard: payment must be due for retry
            if (!$payment->isDueForRetry()) {
                $this->logger->warning('Payment not due for retry yet', [
                    'payment_id' => $payment->id()->toString(),
                    'next_retry_at' => $payment->nextRetryAt()?->format('Y-m-d H:i:s'),
                ]);

                return false;
            }

            $attemptNumber = $payment->retryCount() + 1;
            $this->logger->info('Processing payment retry', [
                'payment_id' => $payment->id()->toString(),
                'attempt_number' => $attemptNumber,
                'gateway' => $payment->gateway()->value(),
            ]);

            // Resolve the correct gateway adapter
            $gatewayMethod = PaymentMethodEnum::from($payment->gateway()->value());
            $gateway = $this->gatewayFactory->create($gatewayMethod);

            // Build retry-specific idempotency key to prevent duplicate charges
            $idempotencyKey = sprintf('retry_%s_%d', $payment->id()->toString(), $attemptNumber);

            $amount = $payment->amount();

            // Call the gateway to create a new payment intent
            $gatewayResult = $gateway->createPaymentIntent(
                paymentId: ModelPaymentId::generate(),
                amount: $amount,
                currency: $amount->getCurrency()->getCurrencyCode(),
                idempotencyKey: $idempotencyKey,
                metadata: [
                    'tenant_id' => $payment->tenantId()->toString(),
                    'order_id' => $payment->orderId(),
                    'retry_attempt' => $attemptNumber,
                    'original_payment_id' => $payment->id()->toString(),
                ]
            );

            if ($gatewayResult->success) {
                // Retry succeeded — record attempt and authorize payment
                $payment->recordRetryAttempt(wasSuccessful: true);
                $payment->authorize($gatewayResult->gatewayPaymentIntentId);

                $this->logger->info('Payment retry successful', [
                    'payment_id' => $payment->id()->toString(),
                    'attempt_number' => $payment->retryCount(),
                    'gateway_transaction_id' => $gatewayResult->gatewayPaymentIntentId,
                ]);
            } else {
                // Retry failed — record the gateway error
                $payment->recordRetryAttempt(
                    wasSuccessful: false,
                    errorCode: $gatewayResult->errorCode,
                    errorMessage: $gatewayResult->errorMessage
                );

                $this->logger->warning('Payment retry failed at gateway', [
                    'payment_id' => $payment->id()->toString(),
                    'attempt_number' => $payment->retryCount(),
                    'error_code' => $gatewayResult->errorCode,
                    'error_message' => $gatewayResult->errorMessage,
                ]);

                if ($payment->retryCount() >= $this->retryPolicy->maxAttempts()) {
                    // All retries exhausted
                    $payment->markRetryExhausted($this->retryPolicy);

                    $this->logger->warning('Payment retry exhausted', [
                        'payment_id' => $payment->id()->toString(),
                        'total_attempts' => $payment->retryCount(),
                    ]);
                } else {
                    // Schedule next retry with exponential backoff
                    $payment->scheduleRetry($this->retryPolicy);

                    $this->logger->info('Next retry scheduled', [
                        'payment_id' => $payment->id()->toString(),
                        'next_retry_at' => $payment->nextRetryAt()?->format('Y-m-d H:i:s'),
                    ]);
                }
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

        return $this->paymentRepository->findPaymentsForRetry($now);
    }
}
