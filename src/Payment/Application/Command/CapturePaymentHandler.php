<?php

declare(strict_types=1);

namespace App\Payment\Application\Command;

use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use App\Payment\Infrastructure\Gateway\PaymentGatewayFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CapturePaymentHandler
{
    public function __construct(
        private PaymentRepositoryInterface $paymentRepository,
        private PaymentGatewayFactory $gatewayFactory,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(CapturePayment $command): void
    {
        $payment = $this->paymentRepository->findById($command->id, $command->tenantId);

        if (null === $payment) {
            throw new \RuntimeException(sprintf('Payment with ID "%s" not found', $command->id->toString()));
        }

        if (null === $payment->gatewayTransactionId()) {
            throw new \RuntimeException(sprintf('Payment "%s" has not been authorized yet', $command->id->toString()));
        }

        $this->logger->info('Capturing payment via gateway', [
            'payment_id' => $command->id->toString(),
            'gateway' => $payment->gateway()->value(),
            'transaction_id' => $payment->gatewayTransactionId(),
            'amount' => $payment->amountInCents(),
        ]);

        try {
            // Get gateway instance
            $gateway = $this->gatewayFactory->getGateway($payment->gateway());

            // Call gateway to capture payment
            $result = $gateway->capture(
                transactionId: $payment->gatewayTransactionId(),
                amountInCents: null // Capture full authorized amount
            );

            $this->logger->info('Payment capture successful', [
                'payment_id' => $command->id->toString(),
                'transaction_id' => $result['transaction_id'],
                'captured_amount' => $result['captured_amount'],
                'status' => $result['status'],
            ]);

            // Update domain model
            $payment->capture();

            // Persist changes
            $this->paymentRepository->save($payment);
        } catch (\Exception $e) {
            $this->logger->error('Payment capture failed', [
                'payment_id' => $command->id->toString(),
                'gateway' => $payment->gateway()->value(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
