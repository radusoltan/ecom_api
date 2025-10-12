<?php

declare(strict_types=1);

namespace App\Payment\Application\Command;

use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use App\Payment\Infrastructure\Gateway\PaymentGatewayFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RefundPaymentHandler
{
    public function __construct(
        private PaymentRepositoryInterface $paymentRepository,
        private PaymentGatewayFactory $gatewayFactory,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(RefundPayment $command): void
    {
        $payment = $this->paymentRepository->findById($command->id, $command->tenantId);

        if ($payment === null) {
            throw new \RuntimeException(
                sprintf('Payment with ID "%s" not found', $command->id->toString())
            );
        }

        if ($payment->gatewayTransactionId() === null) {
            throw new \RuntimeException(
                sprintf('Payment "%s" has not been captured yet', $command->id->toString())
            );
        }

        $this->logger->info('Refunding payment via gateway', [
            'payment_id' => $command->id->toString(),
            'gateway' => $payment->gateway()->value(),
            'transaction_id' => $payment->gatewayTransactionId(),
            'refund_amount' => $command->refundAmountInCents,
            'reason' => $command->reason,
        ]);

        try {
            // Get gateway instance
            $gateway = $this->gatewayFactory->getGateway($payment->gateway());

            // Call gateway to refund payment
            $result = $gateway->refund(
                transactionId: $payment->gatewayTransactionId(),
                amountInCents: $command->refundAmountInCents,
                reason: $command->reason
            );

            $this->logger->info('Payment refund successful', [
                'payment_id' => $command->id->toString(),
                'refund_id' => $result['refund_id'],
                'refunded_amount' => $result['refunded_amount'],
                'status' => $result['status'],
            ]);

            // Update domain model
            $payment->refund($command->refundAmountInCents, $command->reason);

            // Persist changes
            $this->paymentRepository->save($payment);
        } catch (\Exception $e) {
            $this->logger->error('Payment refund failed', [
                'payment_id' => $command->id->toString(),
                'gateway' => $payment->gateway()->value(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
