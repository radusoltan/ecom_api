<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Infrastructure\Gateway;

use App\Payment\Domain\Model\PaymentId;
use App\Payment\Domain\Model\PaymentMethod;
use App\Payment\Infrastructure\Gateway\StripeClientFactory;
use App\Payment\Infrastructure\Gateway\StripeGateway;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;
use Stripe\Exception\CardException;
use Stripe\Exception\InvalidRequestException;
use Stripe\Exception\UnknownApiErrorException;
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

        // StripeClientFactory is declared final. dg/bypass-finals (enabled in tests/bootstrap.php)
        // strips the final keyword at load time, allowing PHPUnit to generate a mock for it.
        $clientFactory = $this->createMock(StripeClientFactory::class);
        $clientFactory->method('create')->willReturn($this->stripeClient);

        $this->gateway = new StripeGateway($clientFactory, 'whsec_test_secret');
    }

    // ========================================
    // PaymentIntent Creation Tests (5 tests)
    // ========================================

    public function testItCreatesPaymentIntentSuccessfully(): void
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
                        && 'eur' === $params['currency']
                        && 'manual' === $params['capture_method']
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

    public function testItCreatesPaymentIntentWithCustomerId(): void
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

    public function testItHandlesCardDeclinedErrorOnCreate(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $amount = Money::fromScalars(10000, 'EUR');

        // getMessage() is final on \Exception and cannot be mocked.
        // Use ::factory() to construct a real CardException with the desired message and body.
        $cardException = CardException::factory(
            message: 'Your card was declined',
            jsonBody: ['error' => ['message' => 'Card declined', 'decline_code' => 'generic_decline']],
            stripeCode: 'card_declined',
            declineCode: 'generic_decline'
        );

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

    public function testItHandlesInsufficientFundsErrorOnCreate(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $amount = Money::fromScalars(100000, 'EUR');

        // getMessage() is final on \Exception and cannot be mocked.
        // Use ::factory() to construct a real CardException with the desired message and body.
        $cardException = CardException::factory(
            message: 'Insufficient funds',
            jsonBody: ['error' => ['decline_code' => 'insufficient_funds']],
            declineCode: 'insufficient_funds'
        );

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

    public function testItHandlesInvalidRequestErrorOnCreate(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $amount = Money::fromScalars(-100, 'EUR'); // Invalid amount

        // getMessage() is final on \Exception and cannot be mocked.
        // Use ::factory() to construct a real InvalidRequestException.
        $invalidRequestException = InvalidRequestException::factory(
            message: 'Invalid amount: must be positive',
            jsonBody: ['error' => ['message' => 'Invalid amount']]
        );

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

    public function testItConfirmsPaymentIntentSuccessfully(): void
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

    public function testItConfirmsPaymentIntentRequiring3dsAction(): void
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

    public function testItHandlesErrorOnConfirm(): void
    {
        // Arrange
        $gatewayPaymentIntentId = 'pi_test_invalid';

        // getMessage() is final on \Exception and cannot be mocked.
        // Use ::factory() to construct a real InvalidRequestException.
        $invalidRequestException = InvalidRequestException::factory(
            message: 'Payment intent not found',
            jsonBody: ['error' => ['message' => 'Not found']]
        );

        $this->paymentIntentService
            ->method('confirm')
            ->willThrowException($invalidRequestException);

        // Act
        $result = $this->gateway->confirmPaymentIntent(
            gatewayPaymentIntentId: $gatewayPaymentIntentId,
            paymentMethodId: 'pm_invalid'
        );

        // Assert
        $this->assertFalse($result->success);
        $this->assertSame('invalid_request', $result->errorCode);
    }

    // ========================================
    // PaymentIntent Capture Tests (3 tests)
    // ========================================

    public function testItCapturesPaymentIntentFullAmount(): void
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

    public function testItCapturesPaymentIntentPartialAmount(): void
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

    public function testItHandlesErrorOnCapture(): void
    {
        // Arrange
        $gatewayPaymentIntentId = 'pi_test_capture_failed';

        // getMessage() is final on \Exception and cannot be mocked.
        // Use ::factory() to construct a real InvalidRequestException.
        $invalidRequestException = InvalidRequestException::factory(
            message: 'Cannot capture cancelled payment intent',
            jsonBody: ['error' => ['message' => 'Cannot capture']]
        );

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

    public function testItCancelsPaymentIntentSuccessfully(): void
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

    public function testItHandlesErrorOnCancel(): void
    {
        // Arrange
        $gatewayPaymentIntentId = 'pi_test_cancel_failed';

        // getMessage() is final on \Exception and cannot be mocked.
        // Use ::factory() to construct a real InvalidRequestException.
        $invalidRequestException = InvalidRequestException::factory(
            message: 'Cannot cancel succeeded payment intent',
            jsonBody: ['error' => ['message' => 'Cannot cancel']]
        );

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

    public function testItCreatesRefundSuccessfully(): void
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
                        && 'requested_by_customer' === $params['reason'];
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

    public function testItCreatesRefundWithReasonMapping(): void
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
                    return 'fraudulent' === $params['reason'];
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

    public function testItHandlesErrorOnRefund(): void
    {
        // Arrange
        $gatewayPaymentIntentId = 'pi_test_refund_failed';
        $refundAmount = Money::fromScalars(10000, 'EUR');

        // getMessage() is final on \Exception and cannot be mocked.
        // Use ::factory() to construct a real InvalidRequestException.
        $invalidRequestException = InvalidRequestException::factory(
            message: 'Charge already refunded',
            jsonBody: ['error' => ['message' => 'Already refunded']]
        );

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

    public function testItMapsApiErrorWithRateLimit(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $amount = Money::fromScalars(10000, 'EUR');

        // getMessage() is final on \Exception and cannot be mocked.
        // UnknownApiErrorException is a concrete subclass of ApiErrorException;
        // its ::factory() accepts httpStatus so the gateway's match() on getHttpStatus()
        // returns the correct error code.
        $apiException = UnknownApiErrorException::factory(
            message: 'Rate limit exceeded',
            httpStatus: 429,
            jsonBody: ['error' => ['message' => 'Rate limit']]
        );

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

    public function testItMapsApiErrorWithGatewayTimeout(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $amount = Money::fromScalars(10000, 'EUR');

        // getMessage() is final on \Exception and cannot be mocked.
        $apiException = UnknownApiErrorException::factory(
            message: 'Gateway timeout',
            httpStatus: 503,
            jsonBody: ['error' => ['message' => 'Service unavailable']]
        );

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

    public function testItMapsApiErrorWithProcessingError(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $amount = Money::fromScalars(10000, 'EUR');

        // getMessage() is final on \Exception and cannot be mocked.
        $apiException = UnknownApiErrorException::factory(
            message: 'Processing error',
            httpStatus: 400,
            jsonBody: ['error' => ['message' => 'Processing error']]
        );

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

    public function testItReturnsCorrectGatewayId(): void
    {
        // PaymentMethod is a PHP enum; enum cases are singletons compared with ===.
        // There is no equals() method — use assertSame() directly.
        $this->assertSame(PaymentMethod::STRIPE, $this->gateway->getGatewayId());
    }

    public function testItReturnsCorrectGatewayName(): void
    {
        // Act & Assert
        $this->assertSame('Stripe', $this->gateway->getName());
    }

    public function testItReturnsWebhookSecret(): void
    {
        // Act & Assert
        $this->assertSame('whsec_test_secret', $this->gateway->getWebhookSecret());
    }

    // ========================================
    // Helper Methods
    // ========================================

    /**
     * Create a Stripe PaymentIntent with required properties populated.
     *
     * PaymentIntent extends StripeObject which stores all properties in an
     * internal $_values array accessed via __get/__set magic. PHPUnit mocks
     * intercept __set and do NOT forward writes to $_values, so property
     * assignments on mocks are silently lost and subsequent __get calls return
     * null. Using constructFrom() builds a real StripeObject instance whose
     * __get reads correctly from $_values.
     */
    private function createMockPaymentIntent(
        string $id,
        string $status,
        int $amount,
        string $currency,
        ?string $clientSecret = null,
        ?string $customerId = null,
    ): PaymentIntent {
        return PaymentIntent::constructFrom([
            'id' => $id,
            'status' => $status,
            'amount' => $amount,
            'currency' => $currency,
            'client_secret' => $clientSecret,
            'customer' => $customerId,
        ]);
    }

    /**
     * Create a Stripe Refund with required properties populated.
     *
     * Same rationale as createMockPaymentIntent(): use constructFrom() to build
     * a real StripeObject instance so that __get returns correct values when
     * production code reads $refund->currency, $refund->id, etc.
     */
    private function createMockRefund(
        string $id,
        string $status,
        int $amount,
        string $currency,
    ): Refund {
        return Refund::constructFrom([
            'id' => $id,
            'status' => $status,
            'amount' => $amount,
            'currency' => $currency,
        ]);
    }
}
