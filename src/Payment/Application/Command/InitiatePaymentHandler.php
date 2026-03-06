<?php

declare(strict_types=1);

namespace App\Payment\Application\Command;

use App\Payment\Domain\Model\Payment;
use App\Payment\Domain\Model\PaymentId as ModelPaymentId;
use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use App\Payment\Domain\Service\PaymentGatewayInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler for InitiatePayment command.
 *
 * Creates a Payment entity and initiates payment with the gateway.
 * For Stripe, this creates a PaymentIntent and authorizes the payment.
 */
#[AsMessageHandler]
final readonly class InitiatePaymentHandler
{
    public function __construct(
        private PaymentRepositoryInterface $paymentRepository,
        private PaymentGatewayInterface $paymentGateway,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{paymentId: string, paymentIntentId: string, clientSecret: string|null, status: string}
     */
    public function __invoke(InitiatePayment $command): array
    {
        // Create the payment domain entity
        $payment = Payment::create(
            id: $command->paymentId,
            tenantId: $command->tenantId,
            orderId: $command->orderId,
            amount: $command->amount,
            method: $command->method,
            gateway: $command->gateway
        );

        // Save payment first to establish the record
        $this->paymentRepository->save($payment);

        try {
            // Create payment intent at gateway
            $gatewayResult = $this->paymentGateway->createPaymentIntent(
                paymentId: ModelPaymentId::generate(),
                amount: $command->amount,
                currency: $command->amount->getCurrency()->getCurrencyCode(),
                idempotencyKey: sprintf('initiate_%s', $command->paymentId->toString()),
                metadata: [
                    'tenant_id' => $command->tenantId->toString(),
                    'payment_id' => $command->paymentId->toString(),
                    'order_id' => $command->orderId,
                    'customer_email' => $command->customerEmail,
                ]
            );

            if (!$gatewayResult->success) {
                throw new \RuntimeException($gatewayResult->errorMessage ?? 'Payment gateway failed');
            }

            // Authorize payment with the gateway transaction ID
            $payment->authorize($gatewayResult->gatewayPaymentIntentId);
            $this->paymentRepository->save($payment);

            $this->logger->info('Payment initiated successfully', [
                'payment_id' => $command->paymentId->toString(),
                'order_id' => $command->orderId,
                'gateway_transaction_id' => $gatewayResult->gatewayPaymentIntentId,
            ]);

            // Return data needed for frontend
            return [
                'paymentId' => $command->paymentId->toString(),
                'paymentIntentId' => $gatewayResult->gatewayPaymentIntentId,
                'clientSecret' => $gatewayResult->clientSecret,
                'status' => $gatewayResult->status,
            ];
        } catch (\Exception $e) {
            // Mark payment as failed
            $payment->markAsFailed($e->getMessage());
            $this->paymentRepository->save($payment);

            $this->logger->error('Payment initiation failed', [
                'payment_id' => $command->paymentId->toString(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
