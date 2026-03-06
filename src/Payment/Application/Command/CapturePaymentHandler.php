<?php

declare(strict_types=1);

namespace App\Payment\Application\Command;

use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use App\Payment\Domain\Service\PaymentGatewayInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler for CapturePayment command.
 *
 * Captures an authorized payment, transferring funds from customer to merchant.
 * Supports partial capture if amount is provided.
 *
 * Flow:
 * 1. Load Payment aggregate by ID
 * 2. Validate payment is in authorized status
 * 3. Capture payment at gateway (full or partial amount)
 * 4. Update Payment status to captured
 *
 * Status Transitions:
 * - authorized -> captured
 */
#[AsMessageHandler]
final readonly class CapturePaymentHandler
{
    public function __construct(
        private PaymentRepositoryInterface $paymentRepository,
        private PaymentGatewayInterface $gateway,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(CapturePayment $command): void
    {
        // Load payment
        $payment = $this->paymentRepository->findById($command->id);

        if (null === $payment) {
            throw new \InvalidArgumentException(sprintf('Payment not found: %s', $command->id->toString()));
        }

        // Validate payment has gateway transaction ID
        if (null === $payment->gatewayTransactionId()) {
            throw new \InvalidArgumentException(sprintf('Payment %s has not been authorized yet', $command->id->toString()));
        }

        $this->logger->info('Capturing payment via gateway', [
            'payment_id' => $command->id->toString(),
            'gateway' => $payment->gateway()->value(),
            'transaction_id' => $payment->gatewayTransactionId(),
            'full_amount' => $payment->amount()->getAmount(),
            'capture_amount' => $command->amount?->getAmount(),
            'is_partial' => null !== $command->amount,
        ]);

        try {
            // Capture payment at gateway
            $result = $this->gateway->capturePaymentIntent(
                gatewayPaymentIntentId: $payment->gatewayTransactionId(),
                amount: $command->amount
            );

            if (!$result->success) {
                $errorMessage = $result->errorMessage ?? 'Payment capture failed';
                $payment->markAsFailed($errorMessage, $result->errorCode);
                $this->paymentRepository->save($payment);

                $this->logger->error('Payment capture failed', [
                    'payment_id' => $command->id->toString(),
                    'error_code' => $result->errorCode,
                    'error_message' => $result->errorMessage,
                ]);

                throw new \RuntimeException($errorMessage);
            }

            // Update domain model
            $payment->capture();

            // Persist changes
            $this->paymentRepository->save($payment);

            $this->logger->info('Payment capture successful', [
                'payment_id' => $command->id->toString(),
                'transaction_id' => $payment->gatewayTransactionId(),
                'captured_amount' => $result->amount->getAmount(),
                'status' => $result->status,
            ]);
        } catch (\Throwable $e) {
            if (!$e instanceof \RuntimeException) {
                $payment->markAsFailed($e->getMessage());
                $this->paymentRepository->save($payment);
            }

            $this->logger->error('Unexpected error capturing payment', [
                'payment_id' => $command->id->toString(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
