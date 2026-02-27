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
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * 2Checkout Payment Gateway Adapter.
 *
 * Status: PLANNED — 2Checkout/Verifone integration is scheduled for a future release.
 * All operations throw LogicException until implementation is completed.
 * Use Stripe or PayPal as active alternatives.
 */
final readonly class TwoCheckoutPaymentGateway implements PaymentGatewayInterface
{
    private const API_BASE_URL = 'https://api.2checkout.com';
    private const NOT_AVAILABLE = '2Checkout payment gateway is not yet available. This feature is planned for a future release. Use Stripe or PayPal.';

    public function __construct(
        private string $merchantCode,
        private string $privateKey,
        private string $secretKey,
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private bool $sandbox = true,
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
        throw new \LogicException(self::NOT_AVAILABLE);
    }

    public function confirmPaymentIntent(
        string $gatewayPaymentIntentId,
        string $paymentMethodId,
    ): PaymentIntentResult {
        throw new \LogicException(self::NOT_AVAILABLE);
    }

    public function capturePaymentIntent(
        string $gatewayPaymentIntentId,
        ?Money $amount = null,
    ): PaymentIntentResult {
        throw new \LogicException(self::NOT_AVAILABLE);
    }

    public function cancelPaymentIntent(string $gatewayPaymentIntentId): PaymentIntentResult
    {
        throw new \LogicException(self::NOT_AVAILABLE);
    }

    public function createRefund(
        string $gatewayPaymentIntentId,
        Money $amount,
        string $reason,
        string $idempotencyKey,
    ): RefundResult {
        throw new \LogicException(self::NOT_AVAILABLE);
    }

    public function verifyWebhookSignature(
        string $payload,
        string $signature,
        string $secret,
    ): bool {
        return false;
    }

    public function getGatewayId(): PaymentMethod
    {
        return PaymentMethod::STRIPE; // Placeholder — no 2Checkout enum value exists yet
    }

    public function getName(): string
    {
        return 'twocheckout';
    }
}
