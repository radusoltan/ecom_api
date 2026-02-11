<?php

declare(strict_types=1);

namespace App\Payment\Application\Command\ConfirmPayment;

use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use App\Payment\Domain\Service\PaymentGatewayInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler for ConfirmPayment command.
 *
 * Confirms a payment intent at the gateway, completing the authorization.
 * This may require additional customer action (3D Secure, SCA).
 *
 * Flow:
 * 1. Load Payment aggregate by ID
 * 2. Confirm payment intent at gateway with payment method
 * 3. Update Payment status to authorized if successful
 * 4. Handle 3DS/SCA scenarios (requires_action status)
 *
 * Status Transitions:
 * - pending -> authorized (if no 3DS required)
 * - pending -> requires_action (if 3DS required)
 * - pending -> failed (if confirmation fails)
 */
#[AsMessageHandler]
final readonly class ConfirmPaymentHandler
{
    public function __construct(
        private PaymentRepositoryInterface $paymentRepository,
        private PaymentGatewayInterface $gateway,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(ConfirmPaymentCommand $command): void
    {
        // Load payment
        $payment = $this->paymentRepository->findById($command->paymentId);

        if (null === $payment) {
            throw new \InvalidArgumentException(sprintf(
                'Payment not found: %s',
                $command->paymentId->toString()
            ));
        }

        // Check if payment has gateway transaction ID
        if (null === $payment->gatewayTransactionId()) {
            throw new \InvalidArgumentException(sprintf(
                'Payment %s has no gateway transaction ID',
                $command->paymentId->toString()
            ));
        }

        try {
            // Confirm payment intent at gateway
            $result = $this->gateway->confirmPaymentIntent(
                gatewayPaymentIntentId: $payment->gatewayTransactionId(),
                paymentMethodId: $command->paymentMethodId
            );

            if (!$result->success) {
                $errorMessage = $result->errorMessage ?? 'Payment confirmation failed';
                $payment->markAsFailed($errorMessage, $result->errorCode);
                $this->paymentRepository->save($payment);

                $this->logger->error('Payment confirmation failed', [
                    'payment_id' => $command->paymentId->toString(),
                    'error_code' => $result->errorCode,
                    'error_message' => $result->errorMessage,
                ]);

                throw new \RuntimeException($errorMessage);
            }

            // Check if payment requires additional action (3DS)
            if ($result->requiresAction()) {
                $this->logger->info('Payment requires additional action (3DS/SCA)', [
                    'payment_id' => $command->paymentId->toString(),
                    'status' => $result->status,
                ]);

                // Payment status remains pending - frontend will handle 3DS flow
                return;
            }

            // Authorize payment if confirmed successfully
            if ($result->isAuthorized() || $result->isCaptured()) {
                $payment->authorize($payment->gatewayTransactionId());
                $this->paymentRepository->save($payment);

                $this->logger->info('Payment confirmed and authorized', [
                    'payment_id' => $command->paymentId->toString(),
                    'status' => $result->status,
                ]);

                return;
            }

            // Handle other statuses
            $this->logger->warning('Payment confirmation returned unexpected status', [
                'payment_id' => $command->paymentId->toString(),
                'status' => $result->status,
            ]);
        } catch (\Throwable $e) {
            $payment->markAsFailed($e->getMessage());
            $this->paymentRepository->save($payment);

            $this->logger->error('Unexpected error confirming payment', [
                'payment_id' => $command->paymentId->toString(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
