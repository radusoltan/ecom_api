<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Infrastructure\Gateway;

use App\Payment\Domain\Model\PaymentId;
use App\Payment\Domain\Model\PaymentMethod;
use App\Payment\Infrastructure\Gateway\StripeClientFactory;
use App\Payment\Infrastructure\Gateway\StripeGateway;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\CardException;
use Stripe\Exception\InvalidRequestException;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\Service\PaymentIntentService;
use Stripe\Service\RefundService;
use Stripe\StripeClient;

/**
 * Unit tests for Stripe Payment Gateway Adapter.
 *
 * Tests the adapter layer that converts domain operations to Stripe API calls.
 * All Stripe SDK objects are mocked to avoid external dependencies.
 *
 * Coverage:
 * - PaymentIntent creation (success, card errors, API errors)
 * - PaymentIntent confirmation (success, 3DS flow, errors)
 * - PaymentIntent capture (full, partial, errors)
 * - PaymentIntent cancellation (success, errors)
 * - Refund creation (success, with reason mapping, errors)
 * - Error mapping (insufficient funds, card declined, API errors)
 * - Gateway identification
 */
final class StripeGatewayTest extends TestCase
{
    private StripeClient $stripeClient;
    private PaymentIntentService $paymentIntentService;
    private RefundService $refundService;
    private StripeGateway $gateway;

    protected function setUp(): void
    {
        // Mock Stripe services
        $this->stripeClient = $this->createMock(StripeClient::class);
        $this->paymentIntentService = $this->createMock(PaymentIntentService::class);
        $this->refundService = $this->createMock(RefundService::class);

        // Attach services to client
        $this->stripeClient->paymentIntents = $this->paymentIntentService;
        $this->stripeClient->refunds = $this->refundService;

        // Create a test stub factory that returns our mocked client
        // Note: TestStripeClientFactory is not the real StripeClientFactory (which is final)
        // but a duck-typed stub for testing purposes
        $clientFactory = new TestStripeClientFactory($this->stripeClient);

        // Create gateway instance - PHP will accept our stub due to duck typing
        // @phpstan-ignore-next-line (TestStripeClientFactory is not the real type but works for testing)
        $this->gateway = new StripeGateway($clientFactory, 'whsec_test_secret');
    }

    // ========================================
    // PaymentIntent Creation Tests (5 tests)
    // ========================================

    public function test_it_creates_payment_intent_successfully(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $amount = Money::fromScalars(10000, 'EUR');

        $mockPaymentIntent = $this->createMockPaymentIntent(
            id: 'pi_test_123',
            status: 'requires_confirmation',
            amount: 10000,
            currency: 'eur',
            clientSecret: 'pi_test_123_secret_abc'
        );

        $this->paymentIntentService
            ->expects($this->once())
            ->method('create')
            ->with(
                $this->callback(function ($params) use ($paymentId, $amount) {
                    return $params['amount'] === $amount->getAmount()
                        && $params['currency'] === 'eur'
                        && $params['capture_method'] === 'manual'
                        && $params['metadata']['payment_id'] === $paymentId->toString();
                }),
                $this->callback(function ($options) {
                    return isset($options['idempotency_key']);
                })
            )
            ->willReturn($mockPaymentIntent);

        // Act
        $result = $this->gateway->createPaymentIntent(
            paymentId: $paymentId,
            amount: $amount,
            currency: 'EUR',
            idempotencyKey: 'test-idem-key-123'
        );

        // Assert
        $this->assertTrue($result->success);
        $this->assertSame('pi_test_123', $result->gatewayPaymentIntentId);
        $this->assertSame('requires_confirmation', $result->status);
        $this->assertSame('pi_test_123_secret_abc', $result->clientSecret);
        $this->assertTrue($result->amount->equals(Money::fromScalars(10000, 'EUR')));
    }

    public function test_it_creates_payment_intent_with_customer_id(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $amount = Money::fromScalars(5000, 'USD');
        $customerId = 'cus_test_123';

        $mockPaymentIntent = $this->createMockPaymentIntent(
            id: 'pi_test_456',
            status: 'requires_confirmation',
            amount: 5000,
            currency: 'usd',
            customerId: $customerId
        );

        $this->paymentIntentService
            ->expects($this->once())
            ->method('create')
            ->with(
                $this->callback(function ($params) use ($customerId) {
                    return $params['customer'] === $customerId;
                }),
                $this->anything()
            )
            ->willReturn($mockPaymentIntent);

        // Act
        $result = $this->gateway->createPaymentIntent(
            paymentId: $paymentId,
            amount: $amount,
            currency: 'USD',
            idempotencyKey: 'test-idem-key-456',
            customerId: $customerId
        );

        // Assert
        $this->assertTrue($result->success);
        $this->assertSame($customerId, $result->customerId);
    }

    public function test_it_handles_card_declined_error_on_create(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $amount = Money::fromScalars(10000, 'EUR');

        $cardException = $this->createMock(CardException::class);
        $cardException->method('getMessage')->willReturn('Your card was declined');
        $cardException->method('getDeclineCode')->willReturn('generic_decline');
        $cardException->method('getJsonBody')->willReturn(['error' => ['message' => 'Card declined']]);

        $this->paymentIntentService
            ->method('create')
            ->willThrowException($cardException);

        // Act
        $result = $this->gateway->createPaymentIntent(
            paymentId: $paymentId,
            amount: $amount,
            currency: 'EUR',
            idempotencyKey: 'test-idem-key'
        );

        // Assert
        $this->assertFalse($result->success);
        $this->assertSame('card_declined', $result->errorCode);
        $this->assertNotNull($result->errorMessage);
    }

    public function test_it_handles_insufficient_funds_error_on_create(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $amount = Money::fromScalars(100000, 'EUR');

        $cardException = $this->createMock(CardException::class);
        $cardException->method('getMessage')->willReturn('Insufficient funds');
        $cardException->method('getDeclineCode')->willReturn('insufficient_funds');
        $cardException->method('getJsonBody')->willReturn(['error' => ['decline_code' => 'insufficient_funds']]);

        $this->paymentIntentService
            ->method('create')
            ->willThrowException($cardException);

        // Act
        $result = $this->gateway->createPaymentIntent(
            paymentId: $paymentId,
            amount: $amount,
            currency: 'EUR',
            idempotencyKey: 'test-idem-key'
        );

        // Assert
        $this->assertFalse($result->success);
        $this->assertSame('insufficient_funds', $result->errorCode);
    }

    public function test_it_handles_invalid_request_error_on_create(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $amount = Money::fromScalars(-100, 'EUR'); // Invalid amount

        $invalidRequestException = $this->createMock(InvalidRequestException::class);
        $invalidRequestException->method('getMessage')->willReturn('Invalid amount: must be positive');
        $invalidRequestException->method('getJsonBody')->willReturn(['error' => ['message' => 'Invalid amount']]);

        $this->paymentIntentService
            ->method('create')
            ->willThrowException($invalidRequestException);

        // Act
        $result = $this->gateway->createPaymentIntent(
            paymentId: $paymentId,
            amount: $amount,
            currency: 'EUR',
            idempotencyKey: 'test-idem-key'
        );

        // Assert
        $this->assertFalse($result->success);
        $this->assertSame('invalid_request', $result->errorCode);
    }

    // ========================================
    // PaymentIntent Confirmation Tests (3 tests)
    // ========================================

    public function test_it_confirms_payment_intent_successfully(): void
    {
        // Arrange
        $gatewayPaymentIntentId = 'pi_test_789';
        $paymentMethodId = 'pm_card_visa';

        $mockPaymentIntent = $this->createMockPaymentIntent(
            id: $gatewayPaymentIntentId,
            status: 'requires_capture',
            amount: 15000,
            currency: 'gbp'
        );

        $this->paymentIntentService
            ->expects($this->once())
            ->method('confirm')
            ->with(
                $gatewayPaymentIntentId,
                $this->callback(function ($params) use ($paymentMethodId) {
                    return $params['payment_method'] === $paymentMethodId;
                })
            )
            ->willReturn($mockPaymentIntent);

        // Act
        $result = $this->gateway->confirmPaymentIntent(
            gatewayPaymentIntentId: $gatewayPaymentIntentId,
            paymentMethodId: $paymentMethodId
        );

        // Assert
        $this->assertTrue($result->success);
        $this->assertSame('requires_capture', $result->status);
        $this->assertTrue($result->isAuthorized());
    }

    public function test_it_confirms_payment_intent_requiring_3ds_action(): void
    {
        // Arrange
        $gatewayPaymentIntentId = 'pi_test_3ds';
        $paymentMethodId = 'pm_card_threeDSecure2Required';

        $mockPaymentIntent = $this->createMockPaymentIntent(
            id: $gatewayPaymentIntentId,
            status: 'requires_action',
            amount: 20000,
            currency: 'eur',
            clientSecret: 'pi_test_3ds_secret_xyz'
        );

        $this->paymentIntentService
            ->method('confirm')
            ->willReturn($mockPaymentIntent);

        // Act
        $result = $this->gateway->confirmPaymentIntent(
            gatewayPaymentIntentId: $gatewayPaymentIntentId,
            paymentMethodId: $paymentMethodId
        );

        // Assert
        $this->assertTrue($result->success);
        $this->assertSame('requires_action', $result->status);
        $this->assertTrue($result->requiresAction());
        $this->assertNotNull($result->clientSecret);
    }

    public function test_it_handles_error_on_confirm(): void
    {
        // Arrange
        $gatewayPaymentIntentId = 'pi_test_invalid';
        $paymentMethodId = 'pm_invalid';

        $invalidRequestException = $this->createMock(InvalidRequestException::class);
        $invalidRequestException->method('getMessage')->willReturn('Payment intent not found');
        $invalidRequestException->method('getJsonBody')->willReturn(['error' => ['message' => 'Not found']]);

        $this->paymentIntentService
            ->method('confirm')
            ->willThrowException($invalidRequestException);

        // Act
        $result = $this->gateway->confirmPaymentIntent(
            gatewayPaymentIntentId: $gatewayPaymentIntentId,
            paymentMethodId: $paymentMethodId
        );

        // Assert
        $this->assertFalse($result->success);
        $this->assertSame('invalid_request', $result->errorCode);
    }

    // ========================================
    // PaymentIntent Capture Tests (3 tests)
    // ========================================

    public function test_it_captures_payment_intent_full_amount(): void
    {
        // Arrange
        $gatewayPaymentIntentId = 'pi_test_capture';

        $mockPaymentIntent = $this->createMockPaymentIntent(
            id: $gatewayPaymentIntentId,
            status: 'succeeded',
            amount: 25000,
            currency: 'usd'
        );

        $this->paymentIntentService
            ->expects($this->once())
            ->method('capture')
            ->with(
                $gatewayPaymentIntentId,
                [] // No amount parameter = full capture
            )
            ->willReturn($mockPaymentIntent);

        // Act
        $result = $this->gateway->capturePaymentIntent(
            gatewayPaymentIntentId: $gatewayPaymentIntentId,
            amount: null // Full capture
        );

        // Assert
        $this->assertTrue($result->success);
        $this->assertSame('succeeded', $result->status);
        $this->assertTrue($result->isCaptured());
    }

    public function test_it_captures_payment_intent_partial_amount(): void
    {
        // Arrange
        $gatewayPaymentIntentId = 'pi_test_partial_capture';
        $partialAmount = Money::fromScalars(5000, 'EUR');

        $mockPaymentIntent = $this->createMockPaymentIntent(
            id: $gatewayPaymentIntentId,
            status: 'succeeded',
            amount: 5000,
            currency: 'eur'
        );

        $this->paymentIntentService
            ->expects($this->once())
            ->method('capture')
            ->with(
                $gatewayPaymentIntentId,
                $this->callback(function ($params) use ($partialAmount) {
                    return $params['amount_to_capture'] === $partialAmount->getAmount();
                })
            )
            ->willReturn($mockPaymentIntent);

        // Act
        $result = $this->gateway->capturePaymentIntent(
            gatewayPaymentIntentId: $gatewayPaymentIntentId,
            amount: $partialAmount
        );

        // Assert
        $this->assertTrue($result->success);
        $this->assertTrue($result->isCaptured());
    }

    public function test_it_handles_error_on_capture(): void
    {
        // Arrange
        $gatewayPaymentIntentId = 'pi_test_capture_failed';

        $invalidRequestException = $this->createMock(InvalidRequestException::class);
        $invalidRequestException->method('getMessage')->willReturn('Cannot capture cancelled payment intent');
        $invalidRequestException->method('getJsonBody')->willReturn(['error' => ['message' => 'Cannot capture']]);

        $this->paymentIntentService
            ->method('capture')
            ->willThrowException($invalidRequestException);

        // Act
        $result = $this->gateway->capturePaymentIntent(
            gatewayPaymentIntentId: $gatewayPaymentIntentId
        );

        // Assert
        $this->assertFalse($result->success);
        $this->assertSame('capture_failed', $result->errorCode);
    }

    // ========================================
    // PaymentIntent Cancellation Tests (2 tests)
    // ========================================

    public function test_it_cancels_payment_intent_successfully(): void
    {
        // Arrange
        $gatewayPaymentIntentId = 'pi_test_cancel';

        $mockPaymentIntent = $this->createMockPaymentIntent(
            id: $gatewayPaymentIntentId,
            status: 'canceled',
            amount: 30000,
            currency: 'eur'
        );

        $this->paymentIntentService
            ->expects($this->once())
            ->method('cancel')
            ->with($gatewayPaymentIntentId)
            ->willReturn($mockPaymentIntent);

        // Act
        $result = $this->gateway->cancelPaymentIntent(
            gatewayPaymentIntentId: $gatewayPaymentIntentId
        );

        // Assert
        $this->assertTrue($result->success);
        $this->assertSame('canceled', $result->status);
        $this->assertTrue($result->isFinal());
    }

    public function test_it_handles_error_on_cancel(): void
    {
        // Arrange
        $gatewayPaymentIntentId = 'pi_test_cancel_failed';

        $invalidRequestException = $this->createMock(InvalidRequestException::class);
        $invalidRequestException->method('getMessage')->willReturn('Cannot cancel succeeded payment intent');
        $invalidRequestException->method('getJsonBody')->willReturn(['error' => ['message' => 'Cannot cancel']]);

        $this->paymentIntentService
            ->method('cancel')
            ->willThrowException($invalidRequestException);

        // Act
        $result = $this->gateway->cancelPaymentIntent(
            gatewayPaymentIntentId: $gatewayPaymentIntentId
        );

        // Assert
        $this->assertFalse($result->success);
        $this->assertSame('cancellation_failed', $result->errorCode);
    }

    // ========================================
    // Refund Tests (3 tests)
    // ========================================

    public function test_it_creates_refund_successfully(): void
    {
        // Arrange
        $gatewayPaymentIntentId = 'pi_test_refund';
        $refundAmount = Money::fromScalars(8000, 'EUR');

        $mockRefund = $this->createMockRefund(
            id: 're_test_123',
            status: 'succeeded',
            amount: 8000,
            currency: 'eur'
        );

        $this->refundService
            ->expects($this->once())
            ->method('create')
            ->with(
                $this->callback(function ($params) use ($gatewayPaymentIntentId, $refundAmount) {
                    return $params['payment_intent'] === $gatewayPaymentIntentId
                        && $params['amount'] === $refundAmount->getAmount()
                        && $params['reason'] === 'requested_by_customer';
                }),
                $this->callback(function ($options) {
                    return isset($options['idempotency_key']);
                })
            )
            ->willReturn($mockRefund);

        // Act
        $result = $this->gateway->createRefund(
            gatewayPaymentIntentId: $gatewayPaymentIntentId,
            amount: $refundAmount,
            reason: 'customer_request',
            idempotencyKey: 'refund-idem-key-123'
        );

        // Assert
        $this->assertTrue($result->success);
        $this->assertSame('re_test_123', $result->gatewayRefundId);
        $this->assertSame('succeeded', $result->status);
        $this->assertTrue($result->amount->equals(Money::fromScalars(8000, 'EUR')));
    }

    public function test_it_creates_refund_with_reason_mapping(): void
    {
        // Arrange
        $gatewayPaymentIntentId = 'pi_test_refund_fraud';
        $refundAmount = Money::fromScalars(5000, 'USD');

        $mockRefund = $this->createMockRefund(
            id: 're_test_fraud',
            status: 'pending',
            amount: 5000,
            currency: 'usd'
        );

        $this->refundService
            ->expects($this->once())
            ->method('create')
            ->with(
                $this->callback(function ($params) {
                    return $params['reason'] === 'fraudulent';
                }),
                $this->anything()
            )
            ->willReturn($mockRefund);

        // Act
        $result = $this->gateway->createRefund(
            gatewayPaymentIntentId: $gatewayPaymentIntentId,
            amount: $refundAmount,
            reason: 'fraud', // Maps to 'fraudulent' in Stripe
            idempotencyKey: 'refund-idem-key-fraud'
        );

        // Assert
        $this->assertTrue($result->success);
        $this->assertTrue($result->isPending());
    }

    public function test_it_handles_error_on_refund(): void
    {
        // Arrange
        $gatewayPaymentIntentId = 'pi_test_refund_failed';
        $refundAmount = Money::fromScalars(10000, 'EUR');

        $invalidRequestException = $this->createMock(InvalidRequestException::class);
        $invalidRequestException->method('getMessage')->willReturn('Charge already refunded');
        $invalidRequestException->method('getJsonBody')->willReturn(['error' => ['message' => 'Already refunded']]);

        $this->refundService
            ->method('create')
            ->willThrowException($invalidRequestException);

        // Act
        $result = $this->gateway->createRefund(
            gatewayPaymentIntentId: $gatewayPaymentIntentId,
            amount: $refundAmount,
            reason: 'duplicate',
            idempotencyKey: 'refund-idem-key-duplicate'
        );

        // Assert
        $this->assertFalse($result->success);
        $this->assertSame('refund_failed', $result->errorCode);
    }

    // ========================================
    // Error Handling Tests (3 tests)
    // ========================================

    public function test_it_maps_api_error_with_rate_limit(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $amount = Money::fromScalars(10000, 'EUR');

        $apiException = $this->createMock(ApiErrorException::class);
        $apiException->method('getMessage')->willReturn('Rate limit exceeded');
        $apiException->method('getHttpStatus')->willReturn(429);
        $apiException->method('getJsonBody')->willReturn(['error' => ['message' => 'Rate limit']]);

        $this->paymentIntentService
            ->method('create')
            ->willThrowException($apiException);

        // Act
        $result = $this->gateway->createPaymentIntent(
            paymentId: $paymentId,
            amount: $amount,
            currency: 'EUR',
            idempotencyKey: 'test-idem-key'
        );

        // Assert
        $this->assertFalse($result->success);
        $this->assertSame('rate_limit', $result->errorCode);
    }

    public function test_it_maps_api_error_with_gateway_timeout(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $amount = Money::fromScalars(10000, 'EUR');

        $apiException = $this->createMock(ApiErrorException::class);
        $apiException->method('getMessage')->willReturn('Gateway timeout');
        $apiException->method('getHttpStatus')->willReturn(503);
        $apiException->method('getJsonBody')->willReturn(['error' => ['message' => 'Service unavailable']]);

        $this->paymentIntentService
            ->method('create')
            ->willThrowException($apiException);

        // Act
        $result = $this->gateway->createPaymentIntent(
            paymentId: $paymentId,
            amount: $amount,
            currency: 'EUR',
            idempotencyKey: 'test-idem-key'
        );

        // Assert
        $this->assertFalse($result->success);
        $this->assertSame('gateway_timeout', $result->errorCode);
    }

    public function test_it_maps_api_error_with_processing_error(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $amount = Money::fromScalars(10000, 'EUR');

        $apiException = $this->createMock(ApiErrorException::class);
        $apiException->method('getMessage')->willReturn('Processing error');
        $apiException->method('getHttpStatus')->willReturn(400);
        $apiException->method('getJsonBody')->willReturn(['error' => ['message' => 'Processing error']]);

        $this->paymentIntentService
            ->method('create')
            ->willThrowException($apiException);

        // Act
        $result = $this->gateway->createPaymentIntent(
            paymentId: $paymentId,
            amount: $amount,
            currency: 'EUR',
            idempotencyKey: 'test-idem-key'
        );

        // Assert
        $this->assertFalse($result->success);
        $this->assertSame('processing_error', $result->errorCode);
    }

    // ========================================
    // Gateway Identification Tests (3 tests)
    // ========================================

    public function test_it_returns_correct_gateway_id(): void
    {
        // Act & Assert
        $this->assertTrue($this->gateway->getGatewayId()->equals(PaymentMethod::STRIPE));
    }

    public function test_it_returns_correct_gateway_name(): void
    {
        // Act & Assert
        $this->assertSame('Stripe', $this->gateway->getName());
    }

    public function test_it_returns_webhook_secret(): void
    {
        // Act & Assert
        $this->assertSame('whsec_test_secret', $this->gateway->getWebhookSecret());
    }

    // ========================================
    // Helper Methods
    // ========================================

    /**
     * Create a mock Stripe PaymentIntent with required properties.
     */
    private function createMockPaymentIntent(
        string $id,
        string $status,
        int $amount,
        string $currency,
        ?string $clientSecret = null,
        ?string $customerId = null
    ): PaymentIntent {
        $mockPaymentIntent = $this->createMock(PaymentIntent::class);
        $mockPaymentIntent->id = $id;
        $mockPaymentIntent->status = $status;
        $mockPaymentIntent->amount = $amount;
        $mockPaymentIntent->currency = $currency;
        $mockPaymentIntent->client_secret = $clientSecret;
        $mockPaymentIntent->customer = $customerId;

        $mockPaymentIntent->method('toArray')->willReturn([
            'id' => $id,
            'status' => $status,
            'amount' => $amount,
            'currency' => $currency,
            'client_secret' => $clientSecret,
            'customer' => $customerId,
        ]);

        return $mockPaymentIntent;
    }

    /**
     * Create a mock Stripe Refund with required properties.
     */
    private function createMockRefund(
        string $id,
        string $status,
        int $amount,
        string $currency
    ): Refund {
        $mockRefund = $this->createMock(Refund::class);
        $mockRefund->id = $id;
        $mockRefund->status = $status;
        $mockRefund->amount = $amount;
        $mockRefund->currency = $currency;

        $mockRefund->method('toArray')->willReturn([
            'id' => $id,
            'status' => $status,
            'amount' => $amount,
            'currency' => $currency,
        ]);

        return $mockRefund;
    }
}

/**
 * Test stub for StripeClientFactory that returns a mocked StripeClient.
 * This is necessary because StripeClientFactory is declared final and cannot be mocked or extended.
 * We create a simple stub that doesn't inherit from the final class.
 */
final class TestStripeClientFactory
{
    public function __construct(private readonly StripeClient $mockClient)
    {
    }

    public function create(): StripeClient
    {
        return $this->mockClient;
    }
}
