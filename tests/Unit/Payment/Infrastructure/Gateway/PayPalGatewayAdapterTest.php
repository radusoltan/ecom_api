<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Infrastructure\Gateway;

use App\Payment\Domain\Exception\PaymentGatewayException;
use App\Payment\Domain\Model\PaymentId;
use App\Payment\Domain\Model\PaymentMethod;
use App\Payment\Infrastructure\Gateway\PayPalClient;
use App\Payment\Infrastructure\Gateway\PayPalGatewayAdapter;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class PayPalGatewayAdapterTest extends TestCase
{
    private PayPalClient $client;
    private PayPalGatewayAdapter $adapter;

    protected function setUp(): void
    {
        $this->client = $this->createMock(PayPalClient::class);
        $this->adapter = new PayPalGatewayAdapter($this->client, new NullLogger());
    }

    public function testGetName(): void
    {
        self::assertSame('PayPal', $this->adapter->getName());
    }

    public function testGetGatewayId(): void
    {
        self::assertSame(PaymentMethod::PAYPAL, $this->adapter->getGatewayId());
    }

    public function testCreatePaymentIntentSuccess(): void
    {
        $paymentId = PaymentId::generate();
        $amount = Money::fromScalars(5000, 'USD'); // $50.00

        $this->client
            ->expects(self::once())
            ->method('createOrder')
            ->willReturn([
                'id' => 'PAYPAL-ORDER-123',
                'status' => 'CREATED',
                'links' => [
                    ['rel' => 'self', 'href' => 'https://paypal.com/self'],
                    ['rel' => 'approve', 'href' => 'https://paypal.com/approve/123'],
                ],
            ]);

        $result = $this->adapter->createPaymentIntent(
            $paymentId, $amount, 'USD', 'idempotency-key-1'
        );

        self::assertTrue($result->success);
        self::assertSame('PAYPAL-ORDER-123', $result->gatewayPaymentIntentId);
        self::assertSame('requires_payment_method', $result->status);
        self::assertSame('https://paypal.com/approve/123', $result->clientSecret);
    }

    public function testCreatePaymentIntentWithCustomerId(): void
    {
        $paymentId = PaymentId::generate();
        $amount = Money::fromScalars(10000, 'EUR');

        $this->client
            ->method('createOrder')
            ->willReturn([
                'id' => 'ORDER-456',
                'status' => 'CREATED',
                'links' => [],
            ]);

        $result = $this->adapter->createPaymentIntent(
            $paymentId, $amount, 'EUR', 'key-2', 'customer-abc'
        );

        self::assertTrue($result->success);
        self::assertNull($result->clientSecret); // No approve link
    }

    public function testCreatePaymentIntentMissingOrderId(): void
    {
        $paymentId = PaymentId::generate();
        $amount = Money::fromScalars(1000, 'USD');

        $this->client
            ->method('createOrder')
            ->willReturn(['status' => 'CREATED']); // Missing 'id'

        $this->expectException(PaymentGatewayException::class);

        $this->adapter->createPaymentIntent(
            $paymentId, $amount, 'USD', 'key-3'
        );
    }

    public function testCreatePaymentIntentClientThrows(): void
    {
        $paymentId = PaymentId::generate();
        $amount = Money::fromScalars(1000, 'USD');

        $this->client
            ->method('createOrder')
            ->willThrowException(new \RuntimeException('Network error'));

        $this->expectException(PaymentGatewayException::class);

        $this->adapter->createPaymentIntent(
            $paymentId, $amount, 'USD', 'key-4'
        );
    }

    public function testConfirmPaymentIntentSuccess(): void
    {
        $this->client
            ->method('getOrder')
            ->willReturn([
                'id' => 'ORDER-789',
                'status' => 'APPROVED',
                'purchase_units' => [
                    ['amount' => ['value' => '75.00', 'currency_code' => 'USD']],
                ],
            ]);

        $result = $this->adapter->confirmPaymentIntent('ORDER-789', 'pm-method');

        self::assertTrue($result->success);
        self::assertSame('requires_capture', $result->status);
    }

    public function testConfirmPaymentIntentFailure(): void
    {
        $this->client
            ->method('getOrder')
            ->willThrowException(new \RuntimeException('Order not found'));

        $result = $this->adapter->confirmPaymentIntent('ORDER-INVALID', 'pm-method');

        self::assertFalse($result->success);
        self::assertSame('confirmation_failed', $result->errorCode);
    }

    public function testCapturePaymentIntentSuccess(): void
    {
        $this->client
            ->method('captureOrder')
            ->willReturn([
                'id' => 'ORDER-CAPTURED',
                'status' => 'COMPLETED',
                'purchase_units' => [
                    [
                        'amount' => ['value' => '50.00', 'currency_code' => 'USD'],
                        'payments' => [
                            'captures' => [
                                ['id' => 'CAPTURE-001'],
                            ],
                        ],
                    ],
                ],
            ]);

        $result = $this->adapter->capturePaymentIntent('ORDER-CAPTURED');

        self::assertTrue($result->success);
        self::assertSame('succeeded', $result->status);
        self::assertSame('CAPTURE-001', $result->clientSecret);
    }

    public function testCapturePaymentIntentFailure(): void
    {
        $this->client
            ->method('captureOrder')
            ->willThrowException(new \RuntimeException('Capture failed'));

        $this->expectException(PaymentGatewayException::class);

        $this->adapter->capturePaymentIntent('ORDER-FAIL');
    }

    public function testCancelPaymentIntentSuccess(): void
    {
        $this->client
            ->method('getOrder')
            ->willReturn([
                'id' => 'ORDER-CANCEL',
                'status' => 'VOIDED',
                'purchase_units' => [
                    ['amount' => ['value' => '25.00', 'currency_code' => 'USD']],
                ],
            ]);

        $result = $this->adapter->cancelPaymentIntent('ORDER-CANCEL');

        self::assertTrue($result->success);
        self::assertSame('canceled', $result->status);
    }

    public function testCancelPaymentIntentFailure(): void
    {
        $this->client
            ->method('getOrder')
            ->willThrowException(new \RuntimeException('Cancel failed'));

        $this->expectException(PaymentGatewayException::class);

        $this->adapter->cancelPaymentIntent('ORDER-FAIL');
    }

    public function testCreateRefundSuccess(): void
    {
        $amount = Money::fromScalars(2000, 'USD');

        $this->client
            ->method('createRefund')
            ->willReturn([
                'id' => 'REFUND-001',
                'status' => 'COMPLETED',
            ]);

        $result = $this->adapter->createRefund('CAPTURE-001', $amount, 'Customer request', 'refund-key-1');

        self::assertTrue($result->success);
        self::assertSame('REFUND-001', $result->gatewayRefundId);
        self::assertSame('succeeded', $result->status);
    }

    public function testCreateRefundMissingId(): void
    {
        $amount = Money::fromScalars(2000, 'USD');

        $this->client
            ->method('createRefund')
            ->willReturn(['status' => 'PENDING']); // Missing 'id'

        // Missing refund ID causes PaymentGatewayException internally, then caught → failure
        $result = $this->adapter->createRefund('CAPTURE-001', $amount, 'Reason', 'key-2');

        self::assertFalse($result->success);
    }

    public function testCreateRefundPendingStatus(): void
    {
        $amount = Money::fromScalars(1500, 'EUR');

        $this->client
            ->method('createRefund')
            ->willReturn([
                'id' => 'REFUND-002',
                'status' => 'PENDING',
            ]);

        $result = $this->adapter->createRefund('CAPTURE-002', $amount, 'Partial refund', 'key-3');

        self::assertTrue($result->success);
        self::assertSame('pending', $result->status);
    }

    public function testVerifyWebhookSignatureSuccess(): void
    {
        $this->client
            ->method('verifyWebhookSignature')
            ->willReturn(true);

        $result = $this->adapter->verifyWebhookSignature(
            '{"event":"payment.completed"}',
            'transmission-id=abc;timestamp=123;cert-url=https://cert;auth-algo=SHA256',
            'webhook-secret'
        );

        self::assertTrue($result);
    }

    public function testVerifyWebhookSignatureFailure(): void
    {
        $this->client
            ->method('verifyWebhookSignature')
            ->willReturn(false);

        $result = $this->adapter->verifyWebhookSignature(
            '{"event":"hacked"}',
            'transmission-id=bad;timestamp=000;cert-url=fake;auth-algo=NONE',
            'webhook-secret'
        );

        self::assertFalse($result);
    }

    public function testVerifyWebhookSignatureException(): void
    {
        $this->client
            ->method('verifyWebhookSignature')
            ->willThrowException(new \RuntimeException('Verification error'));

        $result = $this->adapter->verifyWebhookSignature('{}', 'sig=val', 'secret');

        self::assertFalse($result);
    }

    public function testStatusMappingAllPaypalStatuses(): void
    {
        // Test all PayPal statuses by creating orders with different statuses
        $statuses = [
            'APPROVED' => 'requires_capture',
            'COMPLETED' => 'succeeded',
            'VOIDED' => 'canceled',
            'PAYER_ACTION_REQUIRED' => 'requires_action',
            'SAVED' => 'requires_payment_method',
        ];

        foreach ($statuses as $paypalStatus => $expectedDomainStatus) {
            $this->client = $this->createMock(PayPalClient::class);
            $this->client
                ->method('getOrder')
                ->willReturn([
                    'id' => 'ORDER-STATUS',
                    'status' => $paypalStatus,
                    'purchase_units' => [
                        ['amount' => ['value' => '10.00', 'currency_code' => 'USD']],
                    ],
                ]);

            $adapter = new PayPalGatewayAdapter($this->client, new NullLogger());
            $result = $adapter->confirmPaymentIntent('ORDER-STATUS', 'pm');

            self::assertSame($expectedDomainStatus, $result->status, "PayPal status {$paypalStatus} should map to {$expectedDomainStatus}");
        }
    }

    public function testExtractAmountEmptyPurchaseUnits(): void
    {
        $this->client
            ->method('getOrder')
            ->willReturn([
                'id' => 'ORDER-EMPTY',
                'status' => 'CREATED',
                'purchase_units' => [],
            ]);

        $result = $this->adapter->confirmPaymentIntent('ORDER-EMPTY', 'pm');

        self::assertTrue($result->success);
        // With empty purchase_units, extractAmountFromOrder returns Money(0, USD)
    }

    public function testCaptureWithoutCapturesInResponse(): void
    {
        $this->client
            ->method('captureOrder')
            ->willReturn([
                'id' => 'ORDER-NO-CAPTURE',
                'status' => 'COMPLETED',
                'purchase_units' => [
                    ['amount' => ['value' => '100.00', 'currency_code' => 'USD']],
                ],
            ]);

        $result = $this->adapter->capturePaymentIntent('ORDER-NO-CAPTURE');

        self::assertTrue($result->success);
        self::assertNull($result->clientSecret); // No capture ID extracted
    }
}
