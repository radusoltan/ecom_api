<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Gateway;

use App\Payment\Domain\Service\PaymentGatewayInterface;
use App\Payment\Domain\ValueObject\PaymentMethod;
use Psr\Log\LoggerInterface;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\StripeClient;

/**
 * Stripe Payment Gateway Adapter.
 *
 * Implements payment operations using Stripe API.
 * Supports card payments with authorize + capture flow.
 */
final readonly class StripePaymentGateway implements PaymentGatewayInterface
{
    private StripeClient $stripe;

    public function __construct(
        string $stripeSecretKey,
        private LoggerInterface $logger
    ) {
        $this->stripe = new StripeClient($stripeSecretKey);
    }

    public function authorize(
        int $amountInCents,
        string $currency,
        PaymentMethod $method,
        array $metadata = []
    ): array {
        try {
            $this->logger->info('Stripe: Authorizing payment', [
                'amount' => $amountInCents,
                'currency' => $currency,
                'method' => $method->value(),
                'metadata' => $metadata,
            ]);

            // Create PaymentIntent with manual capture
            $paymentIntent = $this->stripe->paymentIntents->create([
                'amount' => $amountInCents,
                'currency' => strtolower($currency),
                'capture_method' => 'manual', // Authorize only, capture later
                'payment_method_types' => [$this->mapPaymentMethod($method)],
                'metadata' => $metadata,
            ]);

            $this->logger->info('Stripe: Payment authorized successfully', [
                'transaction_id' => $paymentIntent->id,
                'status' => $paymentIntent->status,
            ]);

            return [
                'transaction_id' => $paymentIntent->id,
                'status' => $paymentIntent->status,
                'metadata' => [
                    'client_secret' => $paymentIntent->client_secret,
                    'amount' => $paymentIntent->amount,
                    'currency' => $paymentIntent->currency,
                ],
            ];
        } catch (ApiErrorException $e) {
            $this->logger->error('Stripe: Authorization failed', [
                'error' => $e->getMessage(),
                'code' => $e->getStripeCode(),
            ]);

            throw new \RuntimeException(sprintf('Stripe authorization failed: %s', $e->getMessage()), previous: $e);
        }
    }

    public function capture(string $transactionId, ?int $amountInCents = null): array
    {
        try {
            $this->logger->info('Stripe: Capturing payment', [
                'transaction_id' => $transactionId,
                'amount' => $amountInCents,
            ]);

            $params = [];
            if (null !== $amountInCents) {
                $params['amount_to_capture'] = $amountInCents;
            }

            $paymentIntent = $this->stripe->paymentIntents->capture($transactionId, $params);

            $this->logger->info('Stripe: Payment captured successfully', [
                'transaction_id' => $paymentIntent->id,
                'captured_amount' => $paymentIntent->amount_received,
            ]);

            return [
                'transaction_id' => $paymentIntent->id,
                'status' => $paymentIntent->status,
                'captured_amount' => $paymentIntent->amount_received,
            ];
        } catch (ApiErrorException $e) {
            $this->logger->error('Stripe: Capture failed', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException(sprintf('Stripe capture failed: %s', $e->getMessage()), previous: $e);
        }
    }

    public function refund(string $transactionId, int $amountInCents, string $reason): array
    {
        try {
            $this->logger->info('Stripe: Processing refund', [
                'transaction_id' => $transactionId,
                'amount' => $amountInCents,
                'reason' => $reason,
            ]);

            $refund = $this->stripe->refunds->create([
                'payment_intent' => $transactionId,
                'amount' => $amountInCents,
                'reason' => $this->mapRefundReason($reason),
                'metadata' => [
                    'reason_description' => $reason,
                ],
            ]);

            $this->logger->info('Stripe: Refund processed successfully', [
                'refund_id' => $refund->id,
                'status' => $refund->status,
                'amount' => $refund->amount,
            ]);

            return [
                'refund_id' => $refund->id,
                'status' => $refund->status,
                'refunded_amount' => $refund->amount,
            ];
        } catch (ApiErrorException $e) {
            $this->logger->error('Stripe: Refund failed', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException(sprintf('Stripe refund failed: %s', $e->getMessage()), previous: $e);
        }
    }

    public function cancel(string $transactionId, string $reason): array
    {
        try {
            $this->logger->info('Stripe: Cancelling payment', [
                'transaction_id' => $transactionId,
                'reason' => $reason,
            ]);

            $paymentIntent = $this->stripe->paymentIntents->cancel($transactionId, [
                'cancellation_reason' => $this->mapCancellationReason($reason),
            ]);

            $this->logger->info('Stripe: Payment cancelled successfully', [
                'transaction_id' => $paymentIntent->id,
                'status' => $paymentIntent->status,
            ]);

            return [
                'status' => $paymentIntent->status,
            ];
        } catch (ApiErrorException $e) {
            $this->logger->error('Stripe: Cancellation failed', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException(sprintf('Stripe cancellation failed: %s', $e->getMessage()), previous: $e);
        }
    }

    public function getStatus(string $transactionId): array
    {
        try {
            $paymentIntent = $this->stripe->paymentIntents->retrieve($transactionId);

            return [
                'status' => $paymentIntent->status,
                'amount' => $paymentIntent->amount,
                'currency' => strtoupper($paymentIntent->currency),
                'metadata' => [
                    'amount_received' => $paymentIntent->amount_received,
                    'amount_capturable' => $paymentIntent->amount_capturable,
                    'created' => $paymentIntent->created,
                ],
            ];
        } catch (ApiErrorException $e) {
            $this->logger->error('Stripe: Status retrieval failed', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException(sprintf('Stripe status retrieval failed: %s', $e->getMessage()), previous: $e);
        }
    }

    public function getName(): string
    {
        return 'stripe';
    }

    /**
     * Map PaymentMethod to Stripe payment method type.
     */
    private function mapPaymentMethod(PaymentMethod $method): string
    {
        return match (true) {
            $method->isCard() => 'card',
            default => throw new \InvalidArgumentException(sprintf('Payment method "%s" is not supported by Stripe gateway', $method->value())),
        };
    }

    /**
     * Map refund reason to Stripe refund reason.
     */
    private function mapRefundReason(string $reason): string
    {
        // Stripe supports: duplicate, fraudulent, requested_by_customer
        if (str_contains(strtolower($reason), 'fraud')) {
            return 'fraudulent';
        }
        if (str_contains(strtolower($reason), 'duplicate')) {
            return 'duplicate';
        }

        return 'requested_by_customer';
    }

    /**
     * Map cancellation reason to Stripe cancellation reason.
     */
    private function mapCancellationReason(string $reason): string
    {
        // Stripe supports: duplicate, fraudulent, requested_by_customer, abandoned
        if (str_contains(strtolower($reason), 'fraud')) {
            return 'fraudulent';
        }
        if (str_contains(strtolower($reason), 'duplicate')) {
            return 'duplicate';
        }
        if (str_contains(strtolower($reason), 'abandon')) {
            return 'abandoned';
        }

        return 'requested_by_customer';
    }
}
