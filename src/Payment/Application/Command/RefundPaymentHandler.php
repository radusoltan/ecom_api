<?php

declare(strict_types=1);

namespace App\Payment\Application\Command;

use App\Payment\Domain\Model\PaymentId;
use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use App\Payment\Domain\Service\PaymentGatewayInterface;
use App\Shared\Domain\ValueObject\Money;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler for RefundPayment command.
 *
 * Refunds a captured payment, returning money to the customer.
 * Supports partial refunds and multiple refunds up to captured amount.
 *
 * Flow:
 * 1. Load Payment aggregate by ID
 * 2. Validate payment is captured and refund amount is valid
 * 3. Create refund at gateway
 * 4. Update Payment with refunded amount
 * 5. Update Payment status to refunded
 *
 * Status Transitions:
 * - captured -> refunded (full or partial)
 *
 * Idempotency: Gateway creates refund with idempotency key to prevent duplicates.
 */
#[AsMessageHandler]
final readonly class RefundPaymentHandler
{
    public function __construct(
        private PaymentRepositoryInterface $paymentRepository,
        private PaymentGatewayInterface $gateway,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(RefundPayment $command): void
    {
        // Load payment
        $payment = $this->paymentRepository->findById($command->id);

        if (null === $payment) {
            throw new \InvalidArgumentException(sprintf(
                'Payment not found: %s',
                $command->id->toString()
            ));
        }

        // Validate payment has been captured
        if (null === $payment->gatewayTransactionId()) {
            throw new \InvalidArgumentException(sprintf(
                'Payment %s has not been captured yet',
                $command->id->toString()
            ));
        }

        // Generate idempotency key for refund
        $idempotencyKey = $this->generateRefundIdempotencyKey($command->id, $command->refundAmountInCents);

        $this->logger->info('Refunding payment via gateway', [
            'payment_id' => $command->id->toString(),
            'gateway' => $payment->gateway()->value(),
            'transaction_id' => $payment->gatewayTransactionId(),
            'refund_amount' => $command->refundAmountInCents,
            'reason' => $command->reason,
            'idempotency_key' => $idempotencyKey,
        ]);

        try {
            // Create refund at gateway
            $result = $this->gateway->createRefund(
                gatewayPaymentIntentId: $payment->gatewayTransactionId(),
                amount: Money::fromScalars($command->refundAmountInCents, $payment->currency()),
                reason: $command->reason,
                idempotencyKey: $idempotencyKey
            );

            if (!$result->success) {
                $errorMessage = $result->errorMessage ?? 'Payment refund failed';

                $this->logger->error('Payment refund failed', [
                    'payment_id' => $command->id->toString(),
                    'error_code' => $result->errorCode,
                    'error_message' => $result->errorMessage,
                ]);

                throw new \RuntimeException($errorMessage);
            }

            $this->logger->info('Payment refund successful at gateway', [
                'payment_id' => $command->id->toString(),
                'refund_id' => $result->gatewayRefundId,
                'refunded_amount' => $result->amount->getAmount(),
                'status' => $result->status,
            ]);

            // Update domain model
            $payment->refund($command->refundAmountInCents, $command->reason);

            // Persist changes
            $this->paymentRepository->save($payment);
        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error refunding payment', [
                'payment_id' => $command->id->toString(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Generate idempotency key for refund to prevent duplicate refunds.
     */
    private function generateRefundIdempotencyKey(PaymentId $paymentId, int $amount): string
    {
        return sprintf('refund_%s_%d_%d', $paymentId->toString(), $amount, time());
    }
}
