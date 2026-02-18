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
 * Fake Stripe Payment Gateway for Testing.
 *
 * Simulates Stripe API responses without making real HTTP calls.
 */
final readonly class FakeStripeGateway implements PaymentGatewayInterface
{
    public function __construct(
        private LoggerInterface $logger,
        private bool $require3DS = false, // Simulate 3DS requirement for testing
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
        $this->logger->info('[FAKE] Stripe: Creating payment intent', [
            'payment_id' => $paymentId->toString(),
            'amount' => $amount->amount(),
            'currency' => $currency,
        ]);

        $status = $this->require3DS || ($metadata['force_3ds'] ?? false) ? 'requires_action' : 'requires_payment_method';

        return new PaymentIntentResult(
            gatewayPaymentIntentId: 'pi_fake_'.bin2hex(random_bytes(12)),
            clientSecret: 'secret_fake_'.bin2hex(random_bytes(10)),
            status: $status,
            amount: $amount->amount(),
            currency: $currency,
            rawData: ['mock' => true, '3ds_required' => 'requires_action' === $status]
        );
    }

    public function confirmPaymentIntent(
        string $gatewayPaymentIntentId,
        string $paymentMethodId,
    ): PaymentIntentResult {
        $this->logger->info('[FAKE] Stripe: Confirming payment intent', [
            'intent_id' => $gatewayPaymentIntentId,
            'method_id' => $paymentMethodId,
        ]);

        return new PaymentIntentResult(
            gatewayPaymentIntentId: $gatewayPaymentIntentId,
            clientSecret: 'secret_confirmed',
            status: 'succeeded',
            amount: 1000,
            currency: 'USD',
            rawData: ['mock' => true]
        );
    }

    public function capturePaymentIntent(
        string $gatewayPaymentIntentId,
        ?Money $amount = null,
    ): PaymentIntentResult {
        $this->logger->info('[FAKE] Stripe: Capturing payment intent', [
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
        $this->logger->info('[FAKE] Stripe: Canceling payment intent', [
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
        $this->logger->info('[FAKE] Stripe: Creating refund', [
            'intent_id' => $gatewayPaymentIntentId,
            'amount' => $amount->amount(),
            'reason' => $reason,
        ]);

        return new RefundResult(
            gatewayRefundId: 're_fake_'.bin2hex(random_bytes(12)),
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
        return PaymentMethod::STRIPE;
    }

    public function getName(): string
    {
        return 'Stripe Sandbox';
    }
}
