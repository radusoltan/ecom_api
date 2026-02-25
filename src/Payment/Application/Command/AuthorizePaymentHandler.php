<?php

declare(strict_types=1);

namespace App\Payment\Application\Command;

use App\Payment\Domain\Model\PaymentId as GatewayPaymentId;
use App\Payment\Domain\Model\PaymentMethod as GatewayPaymentMethod;
use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use App\Payment\Domain\Service\PaymentGatewayFactoryInterface;
use App\Shared\Domain\ValueObject\Money;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AuthorizePaymentHandler
{
    public function __construct(
        private PaymentRepositoryInterface $paymentRepository,
        private PaymentGatewayFactoryInterface $gatewayFactory,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(AuthorizePayment $command): void
    {
        $payment = $this->paymentRepository->findById($command->id, $command->tenantId);

        if (null === $payment) {
            throw new \RuntimeException(sprintf('Payment with ID "%s" not found', $command->id->toString()));
        }

        $this->logger->info('Authorizing payment via gateway', [
            'payment_id' => $command->id->toString(),
            'gateway' => $payment->gateway()->value(),
            'amount' => $payment->amountInCents(),
            'currency' => $payment->currency(),
        ]);

        try {
            // Resolve gateway using domain port (create() takes Model\PaymentMethod enum)
            $gatewayMethod = GatewayPaymentMethod::from($payment->gateway()->value());
            $gateway = $this->gatewayFactory->create($gatewayMethod);

            // Build typed arguments required by createPaymentIntent()
            $gatewayPaymentId = GatewayPaymentId::generate();
            $amount = Money::fromScalars($payment->amountInCents(), $payment->currency());
            $idempotencyKey = $payment->idempotencyKey() ?? $payment->id()->toString();

            // Create payment intent to authorize (reserve) funds
            $result = $gateway->createPaymentIntent(
                paymentId: $gatewayPaymentId,
                amount: $amount,
                currency: $payment->currency(),
                idempotencyKey: $idempotencyKey,
                metadata: [
                    'payment_id' => $payment->id()->toString(),
                    'order_id' => $payment->orderId(),
                    'tenant_id' => $payment->tenantId()->toString(),
                ]
            );

            $this->logger->info('Payment authorization successful', [
                'payment_id' => $command->id->toString(),
                'transaction_id' => $result->gatewayPaymentIntentId,
                'status' => $result->status,
            ]);

            // Update domain model with gateway transaction ID
            $payment->authorize($result->gatewayPaymentIntentId);

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
