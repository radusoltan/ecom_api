<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Gateway;

use App\Payment\Domain\Model\PaymentId;
use App\Payment\Domain\Model\PaymentMethod;
use App\Payment\Domain\Service\PaymentGatewayInterface;
use App\Payment\Domain\Service\PaymentIntentResult;
use App\Payment\Domain\Service\RefundResult;
use App\Shared\Domain\ValueObject\Money;
use Psr\Log\LoggerInterface;

/**
 * Fake PayPal Payment Gateway for Testing.
 *
 * Simulates PayPal API responses without making real HTTP calls.
 */
final readonly class FakePayPalGateway implements PaymentGatewayInterface
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function createPaymentIntent(
        PaymentId $paymentId,
        Money $amount,
        string $currency,
        string $idempotencyKey,
        ?string $customerId = null,
        array $metadata = [],
    ): PaymentIntentResult {
        $this->logger->info('[FAKE] PayPal: Creating payment intent', [
            'payment_id' => $paymentId->toString(),
            'amount' => $amount->amount(),
            'currency' => $currency,
        ]);

        return new PaymentIntentResult(
            gatewayPaymentIntentId: 'PAYID-'.strtoupper(bin2hex(random_bytes(16))),
            clientSecret: 'secret_'.bin2hex(random_bytes(10)),
            status: 'requires_payment_method',
            amount: $amount->amount(),
            currency: $currency,
            rawData: ['mock' => true]
        );
    }

    public function confirmPaymentIntent(
        string $gatewayPaymentIntentId,
        string $paymentMethodId,
    ): PaymentIntentResult {
        $this->logger->info('[FAKE] PayPal: Confirming payment intent', [
            'intent_id' => $gatewayPaymentIntentId,
            'method_id' => $paymentMethodId,
        ]);

        return new PaymentIntentResult(
            gatewayPaymentIntentId: $gatewayPaymentIntentId,
            clientSecret: 'secret_confirmed',
            status: 'succeeded', // Assume immediate success for fake
            amount: 1000, // Dummy amount
            currency: 'USD',
            rawData: ['mock' => true]
        );
    }

    public function capturePaymentIntent(
        string $gatewayPaymentIntentId,
        ?Money $amount = null,
    ): PaymentIntentResult {
        $this->logger->info('[FAKE] PayPal: Capturing payment intent', [
            'intent_id' => $gatewayPaymentIntentId,
        ]);

        return new PaymentIntentResult(
            gatewayPaymentIntentId: $gatewayPaymentIntentId,
            clientSecret: null,
            status: 'succeeded',
            amount: $amount ? $amount->amount() : 1000,
            currency: 'USD',
            rawData: ['mock' => true, 'captured' => true]
        );
    }

    public function cancelPaymentIntent(string $gatewayPaymentIntentId): PaymentIntentResult
    {
        $this->logger->info('[FAKE] PayPal: Canceling payment intent', [
            'intent_id' => $gatewayPaymentIntentId,
        ]);

        return new PaymentIntentResult(
            gatewayPaymentIntentId: $gatewayPaymentIntentId,
            clientSecret: null,
            status: 'canceled',
            amount: 0,
            currency: 'USD',
            rawData: ['mock' => true, 'canceled' => true]
        );
    }

    public function createRefund(
        string $gatewayPaymentIntentId,
        Money $amount,
        string $reason,
        string $idempotencyKey,
    ): RefundResult {
        $this->logger->info('[FAKE] PayPal: Creating refund', [
            'intent_id' => $gatewayPaymentIntentId,
            'amount' => $amount->amount(),
            'reason' => $reason,
        ]);

        return new RefundResult(
            gatewayRefundId: 'REFUND-'.strtoupper(bin2hex(random_bytes(12))),
            status: 'succeeded',
            amount: $amount->amount(),
            currency: 'USD',
            rawData: ['mock' => true]
        );
    }

    public function verifyWebhookSignature(
        string $payload,
        string $signature,
        string $secret,
    ): bool {
        return 'valid_signature' === $signature;
    }

    public function getGatewayId(): PaymentMethod
    {
        return PaymentMethod::PAYPAL;
    }

    public function getName(): string
    {
        return 'PayPal Sandbox';
    }
}
