<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Infrastructure\Gateway;

use App\Payment\Domain\Model\PaymentId;
use App\Payment\Infrastructure\Gateway\StripeClientFactory;
use App\Payment\Infrastructure\Gateway\StripeGateway;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\CardException;
use Stripe\Exception\UnknownApiErrorException;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\Service\PaymentIntentService;
use Stripe\Service\RefundService;
use Stripe\StripeClient;

#[CoversClass(StripeGateway::class)]
final class StripeGatewayAdditionalTest extends TestCase
{
    private StripeClient $stripeClient;
    private PaymentIntentService $paymentIntentService;
    private RefundService $refundService;
    private StripeGateway $gateway;

    protected function setUp(): void
    {
        $this->stripeClient = $this->createMock(StripeClient::class);
        $this->paymentIntentService = $this->createMock(PaymentIntentService::class);
        $this->refundService = $this->createMock(RefundService::class);

        $this->stripeClient->paymentIntents = $this->paymentIntentService;
        $this->stripeClient->refunds = $this->refundService;

        $clientFactory = $this->createMock(StripeClientFactory::class);
        $clientFactory->method('create')->willReturn($this->stripeClient);

        $this->gateway = new StripeGateway($clientFactory, 'whsec_test_webhook_secret');
    }

    // -----------------------------------------------------------------------
    // verifyWebhookSignature
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsFalseWhenWebhookSignatureIsInvalid(): void
    {
        // Stripe::Webhook::constructEvent will throw for invalid payload/signature
        $result = $this->gateway->verifyWebhookSignature(
            payload: 'invalid_payload',
            signature: 'invalid_signature',
            secret: 'whsec_test'
        );

        self::assertFalse($result);
    }

    #[Test]
    public function itReturnsFalseWhenWebhookSecretIsWrong(): void
    {
        $result = $this->gateway->verifyWebhookSignature(
            payload: '{}',
            signature: 't=123,v1=abc',
            secret: 'wrong_secret'
        );

        self::assertFalse($result);
    }

    // -----------------------------------------------------------------------
    // confirmPaymentIntent — ApiErrorException path
    // -----------------------------------------------------------------------

    #[Test]
    public function itHandlesApiErrorOnConfirm(): void
    {
        $gatewayPaymentIntentId = 'pi_test_api_error';

        $apiException = UnknownApiErrorException::factory(
            message: 'Service unavailable',
            httpStatus: 503,
            jsonBody: ['error' => ['message' => 'Service unavailable']]
        );

        $this->paymentIntentService
            ->method('confirm')
            ->willThrowException($apiException);

        $result = $this->gateway->confirmPaymentIntent(
            gatewayPaymentIntentId: $gatewayPaymentIntentId,
            paymentMethodId: 'pm_test'
        );

        self::assertFalse($result->success);
        self::assertSame('gateway_timeout', $result->errorCode);
        self::assertSame($gatewayPaymentIntentId, $result->gatewayPaymentIntentId);
    }

    #[Test]
    public function itHandlesCardErrorOnConfirm(): void
    {
        $gatewayPaymentIntentId = 'pi_test_card_error';

        $cardException = CardException::factory(
            message: 'Your card was declined',
            jsonBody: ['error' => ['decline_code' => 'lost_card']],
            stripeCode: 'card_declined',
            declineCode: 'lost_card'
        );

        $this->paymentIntentService
            ->method('confirm')
            ->willThrowException($cardException);

        $result = $this->gateway->confirmPaymentIntent(
            gatewayPaymentIntentId: $gatewayPaymentIntentId,
            paymentMethodId: 'pm_card'
        );

        self::assertFalse($result->success);
        self::assertSame('card_declined', $result->errorCode);
        // Mapped message for lost_card
        self::assertStringContainsString('lost', strtolower($result->errorMessage ?? ''));
    }

    // -----------------------------------------------------------------------
    // capturePaymentIntent — ApiErrorException path
    // -----------------------------------------------------------------------

    #[Test]
    public function itHandlesApiErrorOnCapture(): void
    {
        $gatewayPaymentIntentId = 'pi_test_capture_api_error';

        $apiException = UnknownApiErrorException::factory(
            message: 'Rate limit exceeded',
            httpStatus: 429,
            jsonBody: ['error' => ['message' => 'Rate limit']]
        );

        $this->paymentIntentService
            ->method('capture')
            ->willThrowException($apiException);

        $result = $this->gateway->capturePaymentIntent(
            gatewayPaymentIntentId: $gatewayPaymentIntentId
        );

        self::assertFalse($result->success);
        self::assertSame('rate_limit', $result->errorCode);
        self::assertSame($gatewayPaymentIntentId, $result->gatewayPaymentIntentId);
    }

    // -----------------------------------------------------------------------
    // cancelPaymentIntent — ApiErrorException path
    // -----------------------------------------------------------------------

    #[Test]
    public function itHandlesApiErrorOnCancel(): void
    {
        $gatewayPaymentIntentId = 'pi_test_cancel_api_error';

        $apiException = UnknownApiErrorException::factory(
            message: 'Server error',
            httpStatus: 500,
            jsonBody: ['error' => ['message' => 'Internal server error']]
        );

        $this->paymentIntentService
            ->method('cancel')
            ->willThrowException($apiException);

        $result = $this->gateway->cancelPaymentIntent(
            gatewayPaymentIntentId: $gatewayPaymentIntentId
        );

        self::assertFalse($result->success);
        self::assertSame('gateway_timeout', $result->errorCode);
        self::assertSame($gatewayPaymentIntentId, $result->gatewayPaymentIntentId);
    }

    // -----------------------------------------------------------------------
    // createRefund — ApiErrorException path
    // -----------------------------------------------------------------------

    #[Test]
    public function itHandlesApiErrorOnRefund(): void
    {
        $gatewayPaymentIntentId = 'pi_test_refund_api_error';
        $refundAmount = Money::fromScalars(5000, 'USD');

        $apiException = UnknownApiErrorException::factory(
            message: 'Stripe API error',
            httpStatus: 500,
            jsonBody: ['error' => ['message' => 'Internal error']]
        );

        $this->refundService
            ->method('create')
            ->willThrowException($apiException);

        $result = $this->gateway->createRefund(
            gatewayPaymentIntentId: $gatewayPaymentIntentId,
            amount: $refundAmount,
            reason: 'customer_request',
            idempotencyKey: 'refund-api-error-key'
        );

        self::assertFalse($result->success);
        self::assertSame('api_error', $result->errorCode);
    }

    // -----------------------------------------------------------------------
    // mapRefundReason — duplicate mapping
    // -----------------------------------------------------------------------

    #[Test]
    public function itMapsDuplicateReasonToStripeEnum(): void
    {
        $mockRefund = Refund::constructFrom([
            'id' => 're_dup',
            'status' => 'succeeded',
            'amount' => 1000,
            'currency' => 'usd',
        ]);

        $this->refundService
            ->expects(self::once())
            ->method('create')
            ->with(
                self::callback(fn ($p) => 'duplicate' === $p['reason']),
                self::anything()
            )
            ->willReturn($mockRefund);

        $result = $this->gateway->createRefund(
            gatewayPaymentIntentId: 'pi_dup',
            amount: Money::fromScalars(1000, 'USD'),
            reason: 'duplicate_charge',
            idempotencyKey: 'dup-key'
        );

        self::assertTrue($result->success);
    }

    #[Test]
    public function itMapsFraudulentReasonToStripeEnum(): void
    {
        $mockRefund = Refund::constructFrom([
            'id' => 're_fraud',
            'status' => 'succeeded',
            'amount' => 2000,
            'currency' => 'eur',
        ]);

        $this->refundService
            ->expects(self::once())
            ->method('create')
            ->with(
                self::callback(fn ($p) => 'fraudulent' === $p['reason']),
                self::anything()
            )
            ->willReturn($mockRefund);

        $result = $this->gateway->createRefund(
            gatewayPaymentIntentId: 'pi_fraud',
            amount: Money::fromScalars(2000, 'EUR'),
            reason: 'fraudulent',
            idempotencyKey: 'fraud-key'
        );

        self::assertTrue($result->success);
    }

    // -----------------------------------------------------------------------
    // mapStripeDeclineCode — specific codes
    // -----------------------------------------------------------------------

    #[Test]
    public function itMapsLostCardDeclineCode(): void
    {
        $cardException = CardException::factory(
            message: 'Card is lost',
            jsonBody: ['error' => ['decline_code' => 'lost_card']],
            declineCode: 'lost_card'
        );

        $this->paymentIntentService->method('create')->willThrowException($cardException);

        $result = $this->gateway->createPaymentIntent(
            paymentId: PaymentId::generate(),
            amount: Money::fromScalars(1000, 'USD'),
            currency: 'USD',
            idempotencyKey: 'key'
        );

        self::assertFalse($result->success);
        self::assertSame('card_declined', $result->errorCode);
        self::assertSame('Card reported lost', $result->errorMessage);
    }

    #[Test]
    public function itMapsStolenCardDeclineCode(): void
    {
        $cardException = CardException::factory(
            message: 'Card is stolen',
            jsonBody: ['error' => ['decline_code' => 'stolen_card']],
            declineCode: 'stolen_card'
        );

        $this->paymentIntentService->method('create')->willThrowException($cardException);

        $result = $this->gateway->createPaymentIntent(
            paymentId: PaymentId::generate(),
            amount: Money::fromScalars(1000, 'USD'),
            currency: 'USD',
            idempotencyKey: 'key'
        );

        self::assertSame('Card reported stolen', $result->errorMessage);
    }

    #[Test]
    public function itMapsExpiredCardDeclineCode(): void
    {
        $cardException = CardException::factory(
            message: 'Card expired',
            jsonBody: ['error' => ['decline_code' => 'expired_card']],
            declineCode: 'expired_card'
        );

        $this->paymentIntentService->method('create')->willThrowException($cardException);

        $result = $this->gateway->createPaymentIntent(
            paymentId: PaymentId::generate(),
            amount: Money::fromScalars(1000, 'USD'),
            currency: 'USD',
            idempotencyKey: 'key'
        );

        self::assertSame('Card has expired', $result->errorMessage);
    }

    #[Test]
    public function itMapsUnknownDeclineCodeWithDefaultMessage(): void
    {
        $cardException = CardException::factory(
            message: 'Unknown decline',
            jsonBody: ['error' => ['decline_code' => 'some_unknown_code']],
            declineCode: 'some_unknown_code'
        );

        $this->paymentIntentService->method('create')->willThrowException($cardException);

        $result = $this->gateway->createPaymentIntent(
            paymentId: PaymentId::generate(),
            amount: Money::fromScalars(1000, 'USD'),
            currency: 'USD',
            idempotencyKey: 'key'
        );

        self::assertSame('card_declined', $result->errorCode);
        self::assertStringContainsString('some_unknown_code', $result->errorMessage ?? '');
    }

    // -----------------------------------------------------------------------
    // createSuccessResult — customer as Customer object (not string)
    // -----------------------------------------------------------------------

    #[Test]
    public function itHandlesCustomerAsStringInSuccessResult(): void
    {
        $customerId = 'cus_string_123';

        $mockPaymentIntent = PaymentIntent::constructFrom([
            'id' => 'pi_test_cus',
            'status' => 'requires_confirmation',
            'amount' => 5000,
            'currency' => 'usd',
            'client_secret' => 'pi_test_cus_secret',
            'customer' => $customerId,
        ]);

        $this->paymentIntentService->method('create')->willReturn($mockPaymentIntent);

        $result = $this->gateway->createPaymentIntent(
            paymentId: PaymentId::generate(),
            amount: Money::fromScalars(5000, 'USD'),
            currency: 'USD',
            idempotencyKey: 'key'
        );

        self::assertTrue($result->success);
        self::assertSame($customerId, $result->customerId);
    }

    // -----------------------------------------------------------------------
    // createPaymentIntent — with metadata
    // -----------------------------------------------------------------------

    #[Test]
    public function itMergesCustomMetadataWithPaymentId(): void
    {
        $paymentId = PaymentId::generate();
        $mockPaymentIntent = PaymentIntent::constructFrom([
            'id' => 'pi_meta',
            'status' => 'requires_confirmation',
            'amount' => 1000,
            'currency' => 'usd',
            'client_secret' => null,
            'customer' => null,
        ]);

        $this->paymentIntentService
            ->expects(self::once())
            ->method('create')
            ->with(
                self::callback(function ($params) use ($paymentId): bool {
                    return isset($params['metadata']['order_id'])
                        && 'order-123' === $params['metadata']['order_id']
                        && $params['metadata']['payment_id'] === $paymentId->toString();
                }),
                self::anything()
            )
            ->willReturn($mockPaymentIntent);

        $result = $this->gateway->createPaymentIntent(
            paymentId: $paymentId,
            amount: Money::fromScalars(1000, 'USD'),
            currency: 'USD',
            idempotencyKey: 'key',
            metadata: ['order_id' => 'order-123']
        );

        self::assertTrue($result->success);
    }

    // -----------------------------------------------------------------------
    // HTTP 502 / 504 gateway timeout
    // -----------------------------------------------------------------------

    #[Test]
    public function itMaps502ToGatewayTimeout(): void
    {
        $apiException = UnknownApiErrorException::factory(
            message: 'Bad gateway',
            httpStatus: 502,
            jsonBody: ['error' => ['message' => 'Bad gateway']]
        );

        $this->paymentIntentService->method('create')->willThrowException($apiException);

        $result = $this->gateway->createPaymentIntent(
            paymentId: PaymentId::generate(),
            amount: Money::fromScalars(1000, 'USD'),
            currency: 'USD',
            idempotencyKey: 'key'
        );

        self::assertSame('gateway_timeout', $result->errorCode);
    }

    #[Test]
    public function itMaps504ToGatewayTimeout(): void
    {
        $apiException = UnknownApiErrorException::factory(
            message: 'Gateway timeout',
            httpStatus: 504,
            jsonBody: ['error' => ['message' => 'Gateway timeout']]
        );

        $this->paymentIntentService->method('create')->willThrowException($apiException);

        $result = $this->gateway->createPaymentIntent(
            paymentId: PaymentId::generate(),
            amount: Money::fromScalars(1000, 'USD'),
            currency: 'USD',
            idempotencyKey: 'key'
        );

        self::assertSame('gateway_timeout', $result->errorCode);
    }
}
