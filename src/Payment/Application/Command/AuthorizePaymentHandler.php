<?php

declare(strict_types=1);

namespace App\Payment\Application\Command;

use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use App\Payment\Infrastructure\Gateway\PaymentGatewayFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AuthorizePaymentHandler
{
    public function __construct(
        private PaymentRepositoryInterface $paymentRepository,
        private PaymentGatewayFactory $gatewayFactory,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(AuthorizePayment $command): void
    {
        $payment = $this->paymentRepository->findById($command->id, $command->tenantId);

        if ($payment === null) {
            throw new \RuntimeException(
                sprintf('Payment with ID "%s" not found', $command->id->toString())
            );
        }

        $this->logger->info('Authorizing payment via gateway', [
            'payment_id' => $command->id->toString(),
            'gateway' => $payment->gateway()->value(),
            'amount' => $payment->amountInCents(),
            'currency' => $payment->currency(),
        ]);

        try {
            // Get gateway instance
            $gateway = $this->gatewayFactory->getGateway($payment->gateway());

            // Call gateway to authorize payment
            $result = $gateway->authorize(
                amountInCents: $payment->amountInCents(),
                currency: $payment->currency(),
                method: $payment->method(),
                metadata: [
                    'payment_id' => $payment->id()->toString(),
                    'order_id' => $payment->orderId(),
                    'tenant_id' => $payment->tenantId()->toString(),
                ]
            );

            $this->logger->info('Payment authorization successful', [
                'payment_id' => $command->id->toString(),
                'transaction_id' => $result['transaction_id'],
                'status' => $result['status'],
            ]);

            // Update domain model with gateway transaction ID
            $payment->authorize($result['transaction_id']);

            // Persist changes
            $this->paymentRepository->save($payment);
        } catch (\Exception $e) {
            $this->logger->error('Payment authorization failed', [
                'payment_id' => $command->id->toString(),
                'gateway' => $payment->gateway()->value(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
