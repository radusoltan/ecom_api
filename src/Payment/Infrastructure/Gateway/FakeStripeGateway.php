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
            'amount' => $amount->getAmount(),
            'currency' => $currency,
        ]);

        $status = $this->require3DS || ($metadata['force_3ds'] ?? false) ? 'requires_action' : 'requires_payment_method';

        return PaymentIntentResult::success(
            gatewayPaymentIntentId: 'pi_fake_'.bin2hex(random_bytes(12)),
            status: $status,
            amount: $amount,
            clientSecret: 'secret_fake_'.bin2hex(random_bytes(10)),
            customerId: $customerId,
            rawResponse: json_encode(['mock' => true, '3ds_required' => 'requires_action' === $status]) ?: null,
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

        return PaymentIntentResult::success(
            gatewayPaymentIntentId: $gatewayPaymentIntentId,
            status: 'succeeded',
            amount: Money::fromScalars(1000, 'USD'),
            clientSecret: 'secret_confirmed',
            rawResponse: json_encode(['mock' => true]) ?: null,
        );
    }

    public function capturePaymentIntent(
        string $gatewayPaymentIntentId,
        ?Money $amount = null,
    ): PaymentIntentResult {
        $this->logger->info('[FAKE] Stripe: Capturing payment intent', [
            'intent_id' => $gatewayPaymentIntentId,
        ]);

        return PaymentIntentResult::success(
            gatewayPaymentIntentId: $gatewayPaymentIntentId,
            status: 'succeeded',
            amount: $amount ?? Money::fromScalars(1000, 'USD'),
            rawResponse: json_encode(['mock' => true, 'captured' => true]) ?: null,
        );
    }

    public function cancelPaymentIntent(string $gatewayPaymentIntentId): PaymentIntentResult
    {
        $this->logger->info('[FAKE] Stripe: Canceling payment intent', [
            'intent_id' => $gatewayPaymentIntentId,
        ]);

        return PaymentIntentResult::success(
            gatewayPaymentIntentId: $gatewayPaymentIntentId,
            status: 'canceled',
            amount: Money::fromScalars(0, 'USD'),
            rawResponse: json_encode(['mock' => true, 'canceled' => true]) ?: null,
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
            'amount' => $amount->getAmount(),
            'reason' => $reason,
        ]);

        return RefundResult::success(
            gatewayRefundId: 're_fake_'.bin2hex(random_bytes(12)),
            status: 'succeeded',
            amount: $amount,
            rawResponse: json_encode(['mock' => true]) ?: null,
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
