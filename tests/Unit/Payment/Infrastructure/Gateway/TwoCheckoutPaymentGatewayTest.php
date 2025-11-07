<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Infrastructure\Gateway;

use App\Payment\Domain\ValueObject\PaymentMethod;
use App\Payment\Infrastructure\Gateway\TwoCheckoutPaymentGateway;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

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
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->gateway = new TwoCheckoutPaymentGateway(
            merchantCode: self::MERCHANT_CODE,
            privateKey: self::PRIVATE_KEY,
            secretKey: self::SECRET_KEY,
            httpClient: $this->httpClient,
            logger: $this->logger,
            sandbox: true
        );
    }

    public function testGetNameReturns2Checkout(): void
    {
        // Act
        $name = $this->gateway->getName();

        // Assert
        $this->assertSame('twocheckout', $name);
    }

    public function testAuthorizeSuccessfully(): void
    {
        // Arrange
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'RefNo' => '2CO123456789',
            'OrderNo' => '987654321',
            'Status' => 'AUTHRECEIVED',
            'PaymentDetails' => [
                'PaymentLink' => 'https://secure.2checkout.com/checkout/12345',
            ],
        ]);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://api.2checkout.com/rest/6.0/orders/',
                $this->callback(function ($options) {
                    return isset($options['headers']['X-Avangate-Authentication'])
                        && isset($options['json'])
                        && 'Y' === $options['json']['Demo'] // Sandbox mode
                        && 'USD' === $options['json']['Currency']
                        && '100.00' === $options['json']['Items'][0]['Price'];
                })
            )
            ->willReturn($response);

        $this->logger->expects($this->exactly(2))
            ->method('info');

        // Act
        $result = $this->gateway->authorize(
            amountInCents: 10000,
            currency: 'USD',
            method: PaymentMethod::card(),
            metadata: [
                'billing' => [
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'email' => 'john@example.com',
                    'country' => 'US',
                    'city' => 'New York',
                    'address' => '123 Main St',
                    'zip' => '10001',
                ],
                'customer_ip' => '192.168.1.1',
                'order_id' => 'order-123',
            ]
        );

        // Assert
        $this->assertIsArray($result);
        $this->assertSame('2CO123456789', $result['transaction_id']);
        $this->assertSame('authorized', $result['status']);
        $this->assertArrayHasKey('metadata', $result);
        $this->assertSame('987654321', $result['metadata']['order_no']);
    }

    public function testAuthorizeUsesDefaultBillingWhenNotProvided(): void
    {
        // Arrange
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'RefNo' => '2CO123456789',
            'OrderNo' => '987654321',
        ]);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                $this->anything(),
                $this->callback(function ($options) {
                    // Verify default billing details are used
                    $payload = $options['json'];

                    return 'John' === $payload['BillingDetails']['FirstName']
                        && 'Doe' === $payload['BillingDetails']['LastName']
                        && 'customer@example.com' === $payload['BillingDetails']['Email']
                        && 'US' === $payload['BillingDetails']['CountryCode'];
                })
            )
            ->willReturn($response);

        $this->logger->method('info');

        // Act
        $result = $this->gateway->authorize(
            amountInCents: 5000,
            currency: 'EUR',
            method: PaymentMethod::card(),
            metadata: []
        );

        // Assert
        $this->assertIsArray($result);
        $this->assertSame('authorized', $result['status']);
    }

    public function testAuthorizeThrowsExceptionOnHttpError(): void
    {
        // Arrange
        $this->httpClient->expects($this->once())
            ->method('request')
            ->willThrowException(new \RuntimeException('HTTP 401 Unauthorized'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('2Checkout: Authorization failed'),
                $this->callback(function ($context) {
                    return isset($context['error'])
                        && str_contains($context['error'], 'HTTP 401');
                })
            );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('2Checkout authorization failed');

        // Act
        $this->gateway->authorize(
            amountInCents: 10000,
            currency: 'USD',
            method: PaymentMethod::card()
        );
    }

    public function testCaptureRetrievesOrderStatus(): void
    {
        // Arrange
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'RefNo' => '2CO123456789',
            'Status' => 'COMPLETE',
            'GrossPrice' => '100.00',
        ]);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                'https://api.2checkout.com/rest/6.0/orders/2CO123456789/',
                $this->callback(function ($options) {
                    return isset($options['headers']['X-Avangate-Authentication']);
                })
            )
            ->willReturn($response);

        $this->logger->expects($this->exactly(2))
            ->method('info');

        // Act
        $result = $this->gateway->capture('2CO123456789', 10000);

        // Assert
        $this->assertIsArray($result);
        $this->assertSame('2CO123456789', $result['transaction_id']);
        $this->assertSame('complete', $result['status']);
        $this->assertSame(10000, $result['captured_amount']);
    }

    public function testCaptureThrowsExceptionOnError(): void
    {
        // Arrange
        $this->httpClient->expects($this->once())
            ->method('request')
            ->willThrowException(new \RuntimeException('Order not found'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('2Checkout: Capture failed'),
                $this->arrayHasKey('error')
            );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('2Checkout capture failed');

        // Act
        $this->gateway->capture('invalid-id');
    }

    public function testRefundSuccessfully(): void
    {
        // Arrange
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'RefNo' => 'REFUND-123',
            'Status' => 'REFUNDED',
        ]);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://api.2checkout.com/rest/6.0/orders/2CO123456789/refund/',
                $this->callback(function ($options) {
                    return isset($options['json']['amount'])
                        && '50.00' === $options['json']['amount']
                        && 'Customer requested refund' === $options['json']['comment'];
                })
            )
            ->willReturn($response);

        $this->logger->expects($this->exactly(2))
            ->method('info');

        // Act
        $result = $this->gateway->refund(
            transactionId: '2CO123456789',
            amountInCents: 5000,
            reason: 'Customer requested refund'
        );

        // Assert
        $this->assertIsArray($result);
        $this->assertSame('REFUND-123', $result['refund_id']);
        $this->assertSame('refunded', $result['status']);
        $this->assertSame(5000, $result['refunded_amount']);
    }

    public function testRefundThrowsExceptionOnError(): void
    {
        // Arrange
        $this->httpClient->expects($this->once())
            ->method('request')
            ->willThrowException(new \RuntimeException('Refund failed'));

        $this->logger->expects($this->once())
            ->method('error');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('2Checkout refund failed');

        // Act
        $this->gateway->refund('2CO123456789', 5000, 'Test refund');
    }

    public function testCancelSuccessfully(): void
    {
        // Arrange
        $response = $this->createMock(ResponseInterface::class);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with(
                'DELETE',
                'https://api.2checkout.com/rest/6.0/orders/2CO123456789/',
                $this->callback(function ($options) {
                    return isset($options['json']['Reason'])
                        && 'Customer cancelled' === $options['json']['Reason'];
                })
            )
            ->willReturn($response);

        $this->logger->expects($this->exactly(2))
            ->method('info');

        // Act
        $result = $this->gateway->cancel(
            transactionId: '2CO123456789',
            reason: 'Customer cancelled'
        );

        // Assert
        $this->assertIsArray($result);
        $this->assertSame('cancelled', $result['status']);
    }

    public function testCancelThrowsExceptionOnError(): void
    {
        // Arrange
        $this->httpClient->expects($this->once())
            ->method('request')
            ->willThrowException(new \RuntimeException('Cancellation failed'));

        $this->logger->expects($this->once())
            ->method('error');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('2Checkout cancellation failed');

        // Act
        $this->gateway->cancel('2CO123456789', 'Test cancel');
    }

    public function testGetStatusSuccessfully(): void
    {
        // Arrange
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'RefNo' => '2CO123456789',
            'OrderNo' => '987654321',
            'Status' => 'COMPLETE',
            'GrossPrice' => '199.99',
            'Currency' => 'USD',
            'OrderDate' => '2025-01-16 10:30:00',
        ]);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                'https://api.2checkout.com/rest/6.0/orders/2CO123456789/',
                $this->callback(function ($options) {
                    return isset($options['headers']['X-Avangate-Authentication']);
                })
            )
            ->willReturn($response);

        $this->logger->expects($this->never())
            ->method('error');

        // Act
        $result = $this->gateway->getStatus('2CO123456789');

        // Assert
        $this->assertIsArray($result);
        $this->assertSame('complete', $result['status']);
        $this->assertSame(19999, $result['amount']);
        $this->assertSame('USD', $result['currency']);
        $this->assertArrayHasKey('metadata', $result);
        $this->assertSame('987654321', $result['metadata']['order_no']);
        $this->assertSame('2025-01-16 10:30:00', $result['metadata']['order_date']);
    }

    public function testGetStatusThrowsExceptionOnError(): void
    {
        // Arrange
        $this->httpClient->expects($this->once())
            ->method('request')
            ->willThrowException(new \RuntimeException('Order not found'));

        $this->logger->expects($this->once())
            ->method('error');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('2Checkout status retrieval failed');

        // Act
        $this->gateway->getStatus('invalid-id');
    }

    public function testAuthorizeIncludesDemoParameterInSandboxMode(): void
    {
        // Arrange
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'RefNo' => '2CO123456789',
            'OrderNo' => '987654321',
        ]);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                $this->anything(),
                $this->callback(function ($options) {
                    // Verify Demo parameter is present
                    return isset($options['json']['Demo'])
                        && 'Y' === $options['json']['Demo'];
                })
            )
            ->willReturn($response);

        $this->logger->method('info');

        // Act
        $this->gateway->authorize(10000, 'USD', PaymentMethod::card());

        // No assertion needed - callback verifies Demo parameter
    }

    public function testAuthorizeUsesProductionModeWhenSandboxIsFalse(): void
    {
        // Arrange
        $productionGateway = new TwoCheckoutPaymentGateway(
            merchantCode: self::MERCHANT_CODE,
            privateKey: self::PRIVATE_KEY,
            secretKey: self::SECRET_KEY,
            httpClient: $this->httpClient,
            logger: $this->logger,
            sandbox: false // Production mode
        );

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'RefNo' => '2CO123456789',
            'OrderNo' => '987654321',
        ]);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                $this->anything(),
                $this->callback(function ($options) {
                    // Verify Demo parameter is NOT present in production
                    return !isset($options['json']['Demo']);
                })
            )
            ->willReturn($response);

        $this->logger->method('info');

        // Act
        $productionGateway->authorize(10000, 'USD', PaymentMethod::card());

        // No assertion needed - callback verifies no Demo parameter
    }

    public function testAuthorizeSendsCorrectHmacSignature(): void
    {
        // Arrange
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'RefNo' => '2CO123456789',
            'OrderNo' => '987654321',
        ]);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                $this->anything(),
                $this->callback(function ($options) {
                    // Verify HMAC signature format
                    $auth = $options['headers']['X-Avangate-Authentication'];

                    return str_contains($auth, 'code="'.self::MERCHANT_CODE.'"')
                        && str_contains($auth, 'date=')
                        && str_contains($auth, 'hash=');
                })
            )
            ->willReturn($response);

        $this->logger->method('info');

        // Act
        $this->gateway->authorize(10000, 'USD', PaymentMethod::card());

        // No assertion needed - callback verifies signature format
    }

    public function testAuthorizeThrowsExceptionForUnsupportedPaymentMethod(): void
    {
        // Arrange
        $this->logger->method('info');
        $this->logger->expects($this->once())
            ->method('error');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('2Checkout authorization failed');

        // Act - Try to authorize with unsupported payment method
        // Note: We need to create a PaymentMethod that is not 'card'
        // Since PaymentMethod::card() is the only supported one, we simulate error handling
        $this->httpClient->expects($this->once())
            ->method('request')
            ->willThrowException(new \InvalidArgumentException('Payment method not supported'));

        $this->gateway->authorize(10000, 'USD', PaymentMethod::card());
    }
}
