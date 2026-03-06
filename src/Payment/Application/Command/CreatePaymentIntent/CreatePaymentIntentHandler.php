<?php

declare(strict_types=1);

namespace App\Payment\Application\Command\CreatePaymentIntent;

use App\Payment\Domain\Model\Payment;
use App\Payment\Domain\Model\PaymentId as ModelPaymentId;
use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use App\Payment\Domain\Service\PaymentGatewayInterface;
use App\Payment\Domain\ValueObject\PaymentGateway;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Payment\Domain\ValueObject\PaymentMethod;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler for CreatePaymentIntent command.
 *
 * Creates a Payment aggregate and a payment intent at the gateway.
 * The payment intent reserves funds but does not capture them yet.
 *
 * Flow:
 * 1. Check for existing payment with same idempotency key (prevent duplicates)
 * 2. Create domain Payment aggregate
 * 3. Create payment intent at gateway
 * 4. Store gateway payment ID in Payment aggregate
 * 5. Return result with client secret for frontend
 *
 * Idempotency: If payment with same idempotency key exists, returns existing payment.
 */
#[AsMessageHandler]
final readonly class CreatePaymentIntentHandler
{
    public function __construct(
        private PaymentRepositoryInterface $paymentRepository,
        private PaymentGatewayInterface $gateway,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(CreatePaymentIntentCommand $command): CreatePaymentIntentResult
    {
        // Check for existing payment with same idempotency key
        $existing = $this->paymentRepository->findByIdempotencyKey(
            $command->idempotencyKey,
            $command->tenantId
        );

        if (null !== $existing) {
            $this->logger->info('Payment intent already exists (idempotency)', [
                'payment_id' => $existing->id()->toString(),
                'idempotency_key' => $command->idempotencyKey,
            ]);

            return CreatePaymentIntentResult::alreadyExists(
                $existing->id(),
                $existing->gatewayTransactionId()
            );
        }

        // Generate two IDs:
        // - domainPaymentId: ULID-based, used for the domain Payment aggregate
        // - modelPaymentId: UUID-based, used for the gateway call and CreatePaymentIntentResult
        $domainPaymentId = PaymentId::generate();
        $modelPaymentId = ModelPaymentId::generate();

        try {
            // Create payment intent at gateway first (uses UUID-based ModelPaymentId for idempotency)
            $gatewayResult = $this->gateway->createPaymentIntent(
                paymentId: $modelPaymentId,
                amount: $command->amount,
                currency: $command->amount->getCurrency()->getCurrencyCode(),
                idempotencyKey: $command->idempotencyKey,
                customerId: $command->customerId,
                metadata: [
                    'tenant_id' => $command->tenantId->toString(),
                    'order_id' => $command->orderId,
                    'customer_email' => $command->customerEmail,
                ]
            );

            if (!$gatewayResult->success) {
                $this->logger->error('Payment intent creation failed at gateway', [
                    'payment_id' => $domainPaymentId->toString(),
                    'error_code' => $gatewayResult->errorCode,
                    'error_message' => $gatewayResult->errorMessage,
                ]);

                return CreatePaymentIntentResult::failed(
                    $gatewayResult->errorCode,
                    $gatewayResult->errorMessage
                );
            }

            // Create domain Payment aggregate using ULID-based PaymentId
            $payment = Payment::create(
                id: $domainPaymentId,
                tenantId: $command->tenantId,
                orderId: $command->orderId,
                amount: $command->amount,
                method: PaymentMethod::fromString($command->paymentMethod),
                gateway: $this->resolveGateway($command->paymentMethod),
                idempotencyKey: $command->idempotencyKey
            );

            // Store gateway payment intent ID
            // Note: In "create intent" flow, payment is not yet authorized
            // We just store the intent ID for later confirmation
            if ('' !== $gatewayResult->gatewayPaymentIntentId) {
                // Store gateway reference without changing status
                // The payment will be authorized after confirmation
                $payment = Payment::reconstituteFromPersistence(
                    id: $payment->id(),
                    tenantId: $payment->tenantId(),
                    orderId: $payment->orderId(),
                    amount: $payment->amount(),
                    method: $payment->method(),
                    gateway: $payment->gateway(),
                    status: $payment->status(),
                    gatewayTransactionId: $gatewayResult->gatewayPaymentIntentId,
                    errorMessage: null,
                    refundedAmount: $payment->refundedAmount(),
                    createdAt: $payment->createdAt(),
                    updatedAt: new \DateTimeImmutable(),
                    idempotencyKey: $payment->idempotencyKey()
                );
            }

            // Save payment
            $this->paymentRepository->save($payment);

            $this->logger->info('Payment intent created successfully', [
                'payment_id' => $domainPaymentId->toString(),
                'gateway_payment_id' => $gatewayResult->gatewayPaymentIntentId,
                'amount' => $command->amount->getAmount(),
                'currency' => $command->amount->getCurrency()->getCurrencyCode(),
            ]);

            return CreatePaymentIntentResult::success(
                paymentId: $domainPaymentId,
                clientSecret: $gatewayResult->clientSecret ?? '',
                gatewayPaymentId: $gatewayResult->gatewayPaymentIntentId
            );
        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error creating payment intent', [
                'payment_id' => $domainPaymentId->toString(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return CreatePaymentIntentResult::failed(
                'internal_error',
                $e->getMessage()
            );
        }
    }

    /**
     * Resolve gateway from payment method.
     */
    private function resolveGateway(string $paymentMethod): PaymentGateway
    {
        // Map payment method to gateway
        // For now, we support stripe and paypal
        return match (strtolower($paymentMethod)) {
            'stripe', 'card', 'credit_card' => PaymentGateway::stripe(),
            'paypal' => PaymentGateway::paypal(),
            default => PaymentGateway::stripe(), // Default to Stripe
        };
    }
}
