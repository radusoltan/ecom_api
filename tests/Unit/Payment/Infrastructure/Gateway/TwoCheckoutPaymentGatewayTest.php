<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Infrastructure\Gateway;

use App\Payment\Domain\Model\PaymentId;
use App\Payment\Domain\Model\PaymentMethod;
use App\Payment\Infrastructure\Gateway\TwoCheckoutPaymentGateway;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class TwoCheckoutPaymentGatewayTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private TwoCheckoutPaymentGateway $gateway;

    private const MERCHANT_CODE = '255734682895';
    private const PRIVATE_KEY = 'test-private-key';
    private const SECRET_KEY = 'test-secret-key';

    protected function setUp(): void
    {
        $this->httpClient = $this->createStub(HttpClientInterface::class);
        $this->logger = $this->createStub(LoggerInterface::class);

        $this->gateway = new TwoCheckoutPaymentGateway(
            merchantCode: self::MERCHANT_CODE,
            privateKey: self::PRIVATE_KEY,
            secretKey: self::SECRET_KEY,
            httpClient: $this->httpClient,
            logger: $this->logger,
            sandbox: true
        );
    }

    public function testGetNameReturnsTwocheckout(): void
    {
        $name = $this->gateway->getName();

        $this->assertSame('twocheckout', $name);
    }

    public function testGetGatewayIdReturnsStripeAsTemporaryPlaceholder(): void
    {
        $gatewayId = $this->gateway->getGatewayId();

        $this->assertSame(PaymentMethod::STRIPE, $gatewayId);
    }

    public function testCreatePaymentIntentThrowsRuntimeException(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('2Checkout payment gateway is not yet available.');

        $this->gateway->createPaymentIntent(
            paymentId: PaymentId::generate(),
            amount: Money::fromScalars(10000, 'USD'),
            currency: 'USD',
            idempotencyKey: 'idempotency-key-123',
        );
    }

    public function testCreatePaymentIntentThrowsWithOptionalArguments(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('2Checkout payment gateway is not yet available.');

        $this->gateway->createPaymentIntent(
            paymentId: PaymentId::generate(),
            amount: Money::fromScalars(5000, 'EUR'),
            currency: 'EUR',
            idempotencyKey: 'idempotency-key-456',
            customerId: 'customer-abc',
            metadata: ['order_id' => 'order-123', 'tenant_id' => 'tenant-xyz'],
        );
    }

    public function testConfirmPaymentIntentThrowsRuntimeException(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('2Checkout payment gateway is not yet available.');

        $this->gateway->confirmPaymentIntent(
            gatewayPaymentIntentId: 'pi_test_123',
            paymentMethodId: 'pm_test_456',
        );
    }

    public function testCapturePaymentIntentThrowsRuntimeException(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('2Checkout payment gateway is not yet available.');

        $this->gateway->capturePaymentIntent(
            gatewayPaymentIntentId: 'pi_test_123',
        );
    }

    public function testCapturePaymentIntentThrowsWithPartialAmount(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('2Checkout payment gateway is not yet available.');

        $this->gateway->capturePaymentIntent(
            gatewayPaymentIntentId: 'pi_test_123',
            amount: Money::fromScalars(5000, 'USD'),
        );
    }

    public function testCancelPaymentIntentThrowsRuntimeException(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('2Checkout payment gateway is not yet available.');

        $this->gateway->cancelPaymentIntent(
            gatewayPaymentIntentId: 'pi_test_123',
        );
    }

    public function testCreateRefundThrowsRuntimeException(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('2Checkout payment gateway is not yet available.');

        $this->gateway->createRefund(
            gatewayPaymentIntentId: 'pi_test_123',
            amount: Money::fromScalars(5000, 'USD'),
            reason: 'Customer requested refund',
            idempotencyKey: 'refund-idempotency-key-789',
        );
    }

    public function testCreateRefundThrowsWithDifferentCurrency(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('2Checkout payment gateway is not yet available.');

        $this->gateway->createRefund(
            gatewayPaymentIntentId: 'pi_test_456',
            amount: Money::fromScalars(2000, 'EUR'),
            reason: 'Duplicate order',
            idempotencyKey: 'refund-idempotency-key-abc',
        );
    }

    public function testVerifyWebhookSignatureReturnsFalse(): void
    {
        $result = $this->gateway->verifyWebhookSignature(
            payload: '{"event":"payment.completed","data":{}}',
            signature: 'sha256=abc123def456',
            secret: self::SECRET_KEY,
        );

        $this->assertFalse($result);
    }

    public function testVerifyWebhookSignatureAlwaysReturnsFalseRegardlessOfInput(): void
    {
        $result = $this->gateway->verifyWebhookSignature(
            payload: '',
            signature: '',
            secret: '',
        );

        $this->assertFalse($result);
    }

    public function testGatewayCanBeInstantiatedWithSandboxTrue(): void
    {
        $gateway = new TwoCheckoutPaymentGateway(
            merchantCode: self::MERCHANT_CODE,
            privateKey: self::PRIVATE_KEY,
            secretKey: self::SECRET_KEY,
            httpClient: $this->createStub(HttpClientInterface::class),
            logger: $this->createStub(LoggerInterface::class),
            sandbox: true,
        );

        $this->assertSame('twocheckout', $gateway->getName());
    }

    public function testGatewayCanBeInstantiatedWithSandboxFalse(): void
    {
        $gateway = new TwoCheckoutPaymentGateway(
            merchantCode: self::MERCHANT_CODE,
            privateKey: self::PRIVATE_KEY,
            secretKey: self::SECRET_KEY,
            httpClient: $this->createStub(HttpClientInterface::class),
            logger: $this->createStub(LoggerInterface::class),
            sandbox: false,
        );

        $this->assertSame('twocheckout', $gateway->getName());
    }

    public function testHttpClientIsNotCalledForAnyMethod(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->never())->method('request');

        $gateway = new TwoCheckoutPaymentGateway(
            merchantCode: self::MERCHANT_CODE,
            privateKey: self::PRIVATE_KEY,
            secretKey: self::SECRET_KEY,
            httpClient: $httpClient,
            logger: $this->createStub(LoggerInterface::class),
            sandbox: true,
        );

        $methods = [
            fn () => $gateway->createPaymentIntent(
                PaymentId::generate(),
                Money::fromScalars(100, 'USD'),
                'USD',
                'key',
            ),
            fn () => $gateway->confirmPaymentIntent('pi_123', 'pm_456'),
            fn () => $gateway->capturePaymentIntent('pi_123'),
            fn () => $gateway->cancelPaymentIntent('pi_123'),
            fn () => $gateway->createRefund('pi_123', Money::fromScalars(100, 'USD'), 'reason', 'key'),
        ];

        foreach ($methods as $method) {
            try {
                $method();
            } catch (\LogicException) {
                // Expected: the gateway is not yet implemented
            }
        }
    }
}
