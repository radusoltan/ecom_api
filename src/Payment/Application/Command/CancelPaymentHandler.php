<?php

declare(strict_types=1);

namespace App\Payment\Application\Command;

use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use App\Payment\Domain\Service\PaymentGatewayInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler for CancelPayment command.
 *
 * Cancels/voids an authorized payment, releasing reserved funds.
 * Only works for payments that have NOT been captured yet.
 *
 * Flow:
 * 1. Load Payment aggregate by ID
 * 2. Validate payment can be cancelled (not captured)
 * 3. Cancel payment intent at gateway (if authorized)
 * 4. Update Payment status to cancelled
 *
 * Status Transitions:
 * - pending -> cancelled
 * - authorized -> cancelled
 */
#[AsMessageHandler]
final readonly class CancelPaymentHandler
{
    public function __construct(
        private PaymentRepositoryInterface $paymentRepository,
        private PaymentGatewayInterface $gateway,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(CancelPayment $command): void
    {
        // Load payment
        $payment = $this->paymentRepository->findById($command->id);

        if (null === $payment) {
            throw new \InvalidArgumentException(sprintf('Payment not found: %s', $command->id->toString()));
        }

        // Only cancel via gateway if payment was authorized (has transaction ID)
        if (null !== $payment->gatewayTransactionId()) {
            $this->logger->info('Cancelling payment via gateway', [
                'payment_id' => $command->id->toString(),
                'gateway' => $payment->gateway()->value(),
                'transaction_id' => $payment->gatewayTransactionId(),
                'reason' => $command->reason,
            ]);

            try {
                // Cancel payment intent at gateway
                $result = $this->gateway->cancelPaymentIntent(
                    gatewayPaymentIntentId: $payment->gatewayTransactionId()
                );

                if (!$result->success) {
                    $errorMessage = $result->errorMessage ?? 'Payment cancellation failed';

                    $this->logger->error('Payment cancellation failed', [
                        'payment_id' => $command->id->toString(),
                        'error_code' => $result->errorCode,
                        'error_message' => $result->errorMessage,
                    ]);

                    throw new \RuntimeException($errorMessage);
                }

                $this->logger->info('Payment cancellation successful at gateway', [
                    'payment_id' => $command->id->toString(),
                    'status' => $result->status,
                ]);
            } catch (\Throwable $e) {
                $this->logger->error('Unexpected error cancelling payment', [
                    'payment_id' => $command->id->toString(),
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }
        } else {
            $this->logger->info('Cancelling payment (not yet authorized - no gateway call needed)', [
                'payment_id' => $command->id->toString(),
                'reason' => $command->reason,
            ]);
        }

        // Update domain model
        $payment->cancel($command->reason);

        // Persist changes
        $this->paymentRepository->save($payment);
    }
}
