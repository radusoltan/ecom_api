<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Gateway;

use App\Payment\Domain\Exception\PaymentGatewayException;
use App\Payment\Domain\Model\PaymentId;
use App\Payment\Domain\Model\PaymentMethod;
use App\Payment\Domain\Service\PaymentGatewayInterface;
use App\Payment\Domain\Service\PaymentIntentResult;
use App\Payment\Domain\Service\RefundResult;
use App\Shared\Domain\ValueObject\Money;

/**
 * Mock Payment Gateway for Testing.
 *
 * Returns successful responses without making actual API calls.
 * Used in test environment to avoid external dependencies.
 */
final class MockPaymentGateway implements PaymentGatewayInterface
{
    public function createPaymentIntent(
        PaymentId $paymentId,
        Money $amount,
        string $currency,
        string $idempotencyKey,
        ?string $customerId = null,
        array $metadata = []
    ): PaymentIntentResult {
        return new PaymentIntentResult(
            gatewayPaymentIntentId: 'mock_pi_' . bin2hex(random_bytes(12)),
            clientSecret: 'mock_secret_' . bin2hex(random_bytes(10)),
            status: 'requires_payment_method',
            amount: $amount->amount(),
            currency: $currency,
            rawData: ['mock' => true]
        );
    }

    public function confirmPaymentIntent(
        string $gatewayPaymentIntentId,
        string $paymentMethodId
    ): PaymentIntentResult {
        return new PaymentIntentResult(
            gatewayPaymentIntentId: $gatewayPaymentIntentId,
            clientSecret: 'mock_secret_confirmed',
            status: 'succeeded',
            amount: 1000,
            currency: 'USD',
            rawData: ['mock' => true]
        );
    }

    public function capturePaymentIntent(
        string $gatewayPaymentIntentId,
        ?Money $amount = null
    ): PaymentIntentResult {
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
        string $idempotencyKey
    ): RefundResult {
        return new RefundResult(
            gatewayRefundId: 'mock_re_' . bin2hex(random_bytes(12)),
            status: 'succeeded',
            amount: $amount->amount(),
            currency: 'USD',
            rawData: ['mock' => true]
        );
    }

    public function verifyWebhookSignature(
        string $payload,
        string $signature,
        string $secret
    ): bool {
        return true;
    }

    public function getGatewayId(): PaymentMethod
    {
        return PaymentMethod::STRIPE; // Default mock behavior
    }

    public function getName(): string
    {
        return 'mock';
    }
}
