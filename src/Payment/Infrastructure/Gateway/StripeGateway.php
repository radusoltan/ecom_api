<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Gateway;

use App\Payment\Domain\Model\PaymentId;
use App\Payment\Domain\Model\PaymentMethod;
use App\Payment\Domain\Service\PaymentGatewayInterface;
use App\Payment\Domain\Service\PaymentIntentResult;
use App\Payment\Domain\Service\RefundResult;
use App\Shared\Domain\ValueObject\Money;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\CardException;
use Stripe\Exception\InvalidRequestException;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\Webhook;

/**
 * Stripe Payment Gateway Adapter.
 *
 * Implements the PaymentGatewayInterface port using Stripe API.
 * Handles authorization, capture, cancellation, and refunds.
 *
 * Payment Flow:
 * 1. createPaymentIntent() - Authorize funds (manual capture mode)
 * 2. confirmPaymentIntent() - Complete authorization with payment method
 * 3. capturePaymentIntent() - Capture authorized funds (on shipment)
 * 4. cancelPaymentIntent() - Release authorization (if order cancelled)
 * 5. createRefund() - Refund captured payment (after capture)
 *
 * Features:
 * - Manual capture (authorize-then-capture) for physical goods
 * - Idempotency keys to prevent duplicate charges
 * - 3D Secure (SCA) support
 * - Webhook signature verification
 * - Rich exception mapping to domain exceptions
 *
 * Stripe API Reference: https://stripe.com/docs/api/payment_intents
 */
final class StripeGateway implements PaymentGatewayInterface
{
    public function __construct(
        private readonly StripeClientFactory $clientFactory,
        private readonly string $webhookSecret
    ) {
    }

    public function createPaymentIntent(
        PaymentId $paymentId,
        Money $amount,
        string $currency,
        string $idempotencyKey,
        ?string $customerId = null,
        array $metadata = []
    ): PaymentIntentResult {
        try {
            $client = $this->clientFactory->create();

            $params = [
                'amount' => $amount->getAmount(),  // Minor units (cents)
                'currency' => strtolower($currency),
                'capture_method' => 'manual',  // Authorize only, capture later
                'metadata' => array_merge($metadata, [
                    'payment_id' => $paymentId->toString(),
                ]),
            ];

            if (null !== $customerId) {
                $params['customer'] = $customerId;
            }

            $paymentIntent = $client->paymentIntents->create(
                $params,
                ['idempotency_key' => $idempotencyKey]
            );

            return $this->createSuccessResult($paymentIntent);
        } catch (CardException $e) {
            return $this->handleCardException($e);
        } catch (InvalidRequestException $e) {
            return PaymentIntentResult::failure(
                errorCode: 'invalid_request',
                errorMessage: $e->getMessage(),
                rawResponse: $this->safeJsonEncode($e->getJsonBody())
            );
        } catch (ApiErrorException $e) {
            return $this->handleApiException($e);
        }
    }

    public function confirmPaymentIntent(
        string $gatewayPaymentIntentId,
        string $paymentMethodId
    ): PaymentIntentResult {
        try {
            $client = $this->clientFactory->create();

            $paymentIntent = $client->paymentIntents->confirm(
                $gatewayPaymentIntentId,
                ['payment_method' => $paymentMethodId]
            );

            return $this->createSuccessResult($paymentIntent);
        } catch (CardException $e) {
            return $this->handleCardException($e);
        } catch (InvalidRequestException $e) {
            return PaymentIntentResult::failure(
                errorCode: 'invalid_request',
                errorMessage: $e->getMessage(),
                gatewayPaymentIntentId: $gatewayPaymentIntentId,
                rawResponse: $this->safeJsonEncode($e->getJsonBody())
            );
        } catch (ApiErrorException $e) {
            return $this->handleApiException($e, $gatewayPaymentIntentId);
        }
    }

    public function capturePaymentIntent(
        string $gatewayPaymentIntentId,
        ?Money $amount = null
    ): PaymentIntentResult {
        try {
            $client = $this->clientFactory->create();

            $params = [];
            if (null !== $amount) {
                // Partial capture
                $params['amount_to_capture'] = $amount->getAmount();
            }

            $paymentIntent = $client->paymentIntents->capture(
                $gatewayPaymentIntentId,
                $params
            );

            return $this->createSuccessResult($paymentIntent);
        } catch (InvalidRequestException $e) {
            return PaymentIntentResult::failure(
                errorCode: 'capture_failed',
                errorMessage: $e->getMessage(),
                gatewayPaymentIntentId: $gatewayPaymentIntentId,
                rawResponse: $this->safeJsonEncode($e->getJsonBody())
            );
        } catch (ApiErrorException $e) {
            return $this->handleApiException($e, $gatewayPaymentIntentId);
        }
    }

    public function cancelPaymentIntent(
        string $gatewayPaymentIntentId
    ): PaymentIntentResult {
        try {
            $client = $this->clientFactory->create();

            $paymentIntent = $client->paymentIntents->cancel(
                $gatewayPaymentIntentId
            );

            return $this->createSuccessResult($paymentIntent);
        } catch (InvalidRequestException $e) {
            return PaymentIntentResult::failure(
                errorCode: 'cancellation_failed',
                errorMessage: $e->getMessage(),
                gatewayPaymentIntentId: $gatewayPaymentIntentId,
                rawResponse: $this->safeJsonEncode($e->getJsonBody())
            );
        } catch (ApiErrorException $e) {
            return $this->handleApiException($e, $gatewayPaymentIntentId);
        }
    }

    public function createRefund(
        string $gatewayPaymentIntentId,
        Money $amount,
        string $reason,
        string $idempotencyKey
    ): RefundResult {
        try {
            $client = $this->clientFactory->create();

            $refund = $client->refunds->create(
                [
                    'payment_intent' => $gatewayPaymentIntentId,
                    'amount' => $amount->getAmount(),
                    'reason' => $this->mapRefundReason($reason),
                ],
                ['idempotency_key' => $idempotencyKey]
            );

            return $this->createRefundSuccessResult($refund);
        } catch (InvalidRequestException $e) {
            return RefundResult::failure(
                errorCode: 'refund_failed',
                errorMessage: $e->getMessage(),
                rawResponse: $this->safeJsonEncode($e->getJsonBody())
            );
        } catch (ApiErrorException $e) {
            return RefundResult::failure(
                errorCode: 'api_error',
                errorMessage: $e->getMessage(),
                rawResponse: $this->safeJsonEncode($e->getJsonBody())
            );
        }
    }

    public function verifyWebhookSignature(
        string $payload,
        string $signature,
        string $secret
    ): bool {
        try {
            // Stripe will throw exception if signature is invalid
            Webhook::constructEvent($payload, $signature, $secret);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getGatewayId(): PaymentMethod
    {
        return PaymentMethod::STRIPE;
    }

    public function getName(): string
    {
        return 'Stripe';
    }

    /**
     * Get the configured webhook secret for this gateway.
     * Used by webhook handlers to verify incoming webhook signatures.
     */
    public function getWebhookSecret(): string
    {
        return $this->webhookSecret;
    }

    /**
     * Create success result from Stripe PaymentIntent.
     */
    private function createSuccessResult(PaymentIntent $paymentIntent): PaymentIntentResult
    {
        $customerId = $paymentIntent->customer;
        if ($customerId instanceof \Stripe\Customer) {
            $customerId = $customerId->id;
        }

        return PaymentIntentResult::success(
            gatewayPaymentIntentId: $paymentIntent->id,
            status: $paymentIntent->status,
            amount: Money::fromScalars((int) $paymentIntent->amount, strtoupper($paymentIntent->currency)),
            clientSecret: $paymentIntent->client_secret,
            customerId: $customerId,
            rawResponse: $this->safeJsonEncode($paymentIntent->toArray())
        );
    }

    /**
     * Create success result from Stripe Refund.
     */
    private function createRefundSuccessResult(Refund $refund): RefundResult
    {
        return RefundResult::success(
            gatewayRefundId: $refund->id,
            status: $refund->status ?? 'pending',
            amount: Money::fromScalars((int) $refund->amount, strtoupper($refund->currency)),
            rawResponse: $this->safeJsonEncode($refund->toArray())
        );
    }

    /**
     * Handle Stripe CardException and map to domain exceptions.
     *
     * CardException is thrown when card payment fails (declined, insufficient funds, etc.)
     */
    private function handleCardException(CardException $e): PaymentIntentResult
    {
        $declineCode = $e->getDeclineCode();

        // Insufficient funds - use specialized exception
        if ('insufficient_funds' === $declineCode) {
            return PaymentIntentResult::failure(
                errorCode: 'insufficient_funds',
                errorMessage: $e->getMessage(),
                rawResponse: $this->safeJsonEncode($e->getJsonBody())
            );
        }

        // Generic card declined
        return PaymentIntentResult::failure(
            errorCode: 'card_declined',
            errorMessage: $this->mapStripeDeclineCode($declineCode ?? 'generic_decline'),
            rawResponse: $this->safeJsonEncode($e->getJsonBody())
        );
    }

    /**
     * Handle Stripe ApiErrorException (gateway errors, timeouts, etc.).
     */
    private function handleApiException(ApiErrorException $e, ?string $paymentIntentId = null): PaymentIntentResult
    {
        $errorCode = match ($e->getHttpStatus()) {
            429 => 'rate_limit',
            500, 502, 503, 504 => 'gateway_timeout',
            default => 'processing_error',
        };

        return PaymentIntentResult::failure(
            errorCode: $errorCode,
            errorMessage: $e->getMessage(),
            gatewayPaymentIntentId: $paymentIntentId,
            rawResponse: $this->safeJsonEncode($e->getJsonBody())
        );
    }

    /**
     * Map Stripe decline codes to user-friendly messages.
     *
     * Stripe Decline Codes: https://stripe.com/docs/declines/codes
     */
    private function mapStripeDeclineCode(string $declineCode): string
    {
        return match ($declineCode) {
            'insufficient_funds' => 'Insufficient funds in account',
            'lost_card' => 'Card reported lost',
            'stolen_card' => 'Card reported stolen',
            'expired_card' => 'Card has expired',
            'incorrect_cvc' => 'Incorrect security code (CVC/CVV)',
            'processing_error' => 'Issuer processing error',
            'card_velocity_exceeded' => 'Too many transactions in short period',
            'withdrawal_count_limit_exceeded' => 'Daily transaction limit exceeded',
            'do_not_honor' => 'Card issuer declined transaction',
            'fraudulent' => 'Transaction flagged as potentially fraudulent',
            'invalid_account' => 'Invalid card account',
            'new_account_information_available' => 'Card has been replaced, please update card details',
            'generic_decline' => 'Card declined by issuer',
            default => sprintf('Card declined: %s', $declineCode),
        };
    }

    /**
     * Map domain refund reason to Stripe refund reason.
     *
     * Stripe Refund Reasons:
     * - duplicate: Duplicate charge
     * - fraudulent: Fraudulent transaction
     * - requested_by_customer: Customer requested refund
     */
    private function mapRefundReason(string $reason): string
    {
        return match (strtolower($reason)) {
            'duplicate', 'duplicate_charge' => 'duplicate',
            'fraud', 'fraudulent' => 'fraudulent',
            default => 'requested_by_customer',
        };
    }

    /**
     * Safely encode data to JSON, returning null if encoding fails.
     */
    private function safeJsonEncode($data): ?string
    {
        $encoded = json_encode($data);

        return false !== $encoded ? $encoded : null;
    }
}
