<?php

declare(strict_types=1);

namespace App\Payment\Application\Command;

use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * MarkPaymentAsFailed Command Handler.
 *
 * Marks a payment as failed in the system.
 * This is typically called by:
 * - Webhook handlers when payment_intent.payment_failed is received
 * - Retry exhaustion when max retry attempts are reached
 * - Payment gateway errors
 *
 * Business Rules:
 * - Payment must be in a state that allows failure transition
 * - Error message is required for audit trail
 * - Error code is optional but recommended for retry logic
 * - Domain event PaymentFailed is dispatched for subscribers
 */
#[AsMessageHandler]
final readonly class MarkPaymentAsFailedHandler
{
    public function __construct(
        private PaymentRepositoryInterface $paymentRepository,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(MarkPaymentAsFailed $command): void
    {
        $this->logger->info('Marking payment as failed', [
            'payment_id' => $command->id->toString(),
            'error_message' => $command->errorMessage,
            'error_code' => $command->errorCode,
        ]);

        // Find payment
        $payment = $this->paymentRepository->findById($command->id);

        if (!$payment) {
            $this->logger->warning('Payment not found for marking as failed', [
                'payment_id' => $command->id->toString(),
            ]);

            throw new \RuntimeException(sprintf('Payment not found: %s', $command->id->toString()));
        }

        // Check if payment is already failed (idempotency)
        if ($payment->status()->isFailed()) {
            $this->logger->info('Payment is already failed, skipping', [
                'payment_id' => $command->id->toString(),
                'current_status' => $payment->status()->value(),
            ]);

            return;
        }

        // Mark payment as failed (will throw if transition is invalid)
        try {
            $payment->markAsFailed($command->errorMessage, $command->errorCode);

            // Save payment (will dispatch PaymentFailed event)
            $this->paymentRepository->save($payment);

            $this->logger->info('Payment marked as failed successfully', [
                'payment_id' => $command->id->toString(),
                'error_code' => $command->errorCode,
            ]);
        } catch (\InvalidArgumentException $e) {
            $this->logger->error('Failed to mark payment as failed', [
                'payment_id' => $command->id->toString(),
                'current_status' => $payment->status()->value(),
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException(sprintf('Cannot mark payment %s as failed: %s', $command->id->toString(), $e->getMessage()), 0, $e);
        }
    }
}
