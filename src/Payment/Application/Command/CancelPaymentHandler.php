<?php

declare(strict_types=1);

namespace App\Payment\Application\Command;

use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use App\Payment\Infrastructure\Gateway\PaymentGatewayFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CancelPaymentHandler
{
    public function __construct(
        private PaymentRepositoryInterface $paymentRepository,
        private PaymentGatewayFactory $gatewayFactory,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(CancelPayment $command): void
    {
        $payment = $this->paymentRepository->findById($command->id, $command->tenantId);

        if (null === $payment) {
            throw new \RuntimeException(sprintf('Payment with ID "%s" not found', $command->id->toString()));
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
                // Get gateway instance
                $gateway = $this->gatewayFactory->getGateway($payment->gateway());

                // Call gateway to cancel payment
                $result = $gateway->cancel(
                    transactionId: $payment->gatewayTransactionId(),
                    reason: $command->reason
                );

                $this->logger->info('Payment cancellation successful', [
                    'payment_id' => $command->id->toString(),
                    'status' => $result['status'],
                ]);
            } catch (\Exception $e) {
                $this->logger->error('Payment cancellation failed', [
                    'payment_id' => $command->id->toString(),
                    'gateway' => $payment->gateway()->value(),
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
