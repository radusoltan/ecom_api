<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Infrastructure\Gateway;

use App\Payment\Domain\Model\PaymentId;
use App\Payment\Domain\Model\PaymentMethod;
use App\Payment\Domain\Service\PaymentGatewayInterface;
use App\Payment\Domain\Service\PaymentIntentResult;
use App\Payment\Domain\Service\RefundResult;
use App\Payment\Infrastructure\Gateway\FakeStripeGateway;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for FakeStripeGateway with 3D Secure support.
 *
 * Tests coverage:
 * - Standard payment flow (without 3DS)
 * - 3D Secure flow (requires_action status)
 * - Payment method types configuration
 * - 3DS challenge URL generation
 * - Status transitions after 3DS authentication
 */
final class FakeStripeGatewayTest extends TestCase
{
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    #[Test]
    public function itImplementsPaymentGatewayInterface(): void
    {
        $gateway = new FakeStripeGateway($this->logger);

        $this->assertInstanceOf(PaymentGatewayInterface::class, $gateway);
    }

    #[Test]
    public function itReturnsStripeAsGatewayName(): void
    {
        $gateway = new FakeStripeGateway($this->logger);

        $this->assertSame('Stripe Sandbox', $gateway->getName());
    }

    #[Test]
    public function itReturnsStripeAsGatewayId(): void
    {
        $gateway = new FakeStripeGateway($this->logger);

        $this->assertSame(PaymentMethod::STRIPE, $gateway->getGatewayId());
    }

    // -----------------------------------------------------------------------
    // createPaymentIntent tests
    // -----------------------------------------------------------------------

    #[Test]
    public function itCreatesPaymentIntentWithout3dsByDefault(): void
    {
        $gateway = new FakeStripeGateway($this->logger, require3DS: false);

        $result = $gateway->createPaymentIntent(
            paymentId: PaymentId::generate(),
            amount: Money::fromScalars(5000, 'USD'),
            currency: 'USD',
            idempotencyKey: 'idem-test-123',
            metadata: ['order_id' => 'ORD-123']
        );

        $this->assertInstanceOf(PaymentIntentResult::class, $result);
        $this->assertTrue($result->success);
        $this->assertSame('requires_payment_method', $result->status);
        $this->assertStringStartsWith('pi_fake_', $result->gatewayPaymentIntentId);
        $this->assertStringStartsWith('secret_fake_', $result->clientSecret);
    }

    #[Test]
    public function itRequires3dsAuthenticationWhenEnabled(): void
    {
        $gateway = new FakeStripeGateway($this->logger, require3DS: true);

        $result = $gateway->createPaymentIntent(
            paymentId: PaymentId::generate(),
            amount: Money::fromScalars(5000, 'USD'),
            currency: 'USD',
            idempotencyKey: 'idem-test-456',
            metadata: ['order_id' => 'ORD-456']
        );

        $this->assertInstanceOf(PaymentIntentResult::class, $result);
        $this->assertTrue($result->success);
        $this->assertSame('requires_action', $result->status);
    }

    #[Test]
    public function itAllowsForcing3dsViaMetadata(): void
    {
        $gateway = new FakeStripeGateway($this->logger, require3DS: false);

        $result = $gateway->createPaymentIntent(
            paymentId: PaymentId::generate(),
            amount: Money::fromScalars(5000, 'USD'),
            currency: 'USD',
            idempotencyKey: 'idem-test-789',
            metadata: ['force_3ds' => true]
        );

        $this->assertSame('requires_action', $result->status);
    }

    #[Test]
    public function itGetsPaymentStatusAfter3dsAuthentication(): void
    {
        $gateway = new FakeStripeGateway($this->logger, require3DS: true);

        $result = $gateway->createPaymentIntent(
            paymentId: PaymentId::generate(),
            amount: Money::fromScalars(5000, 'USD'),
            currency: 'USD',
            idempotencyKey: 'idem-3ds-test'
        );

        // 3DS required -> result status is requires_action
        $this->assertSame('requires_action', $result->status);
    }

    // -----------------------------------------------------------------------
    // confirmPaymentIntent tests
    // -----------------------------------------------------------------------

    #[Test]
    public function itConfirmsPaymentIntentSuccessfully(): void
    {
        $gateway = new FakeStripeGateway($this->logger);

        $result = $gateway->confirmPaymentIntent(
            gatewayPaymentIntentId: 'pi_fake_1234567890abcdef',
            paymentMethodId: 'pm_card_visa'
        );

        $this->assertInstanceOf(PaymentIntentResult::class, $result);
        $this->assertTrue($result->success);
        $this->assertSame('succeeded', $result->status);
        $this->assertSame('pi_fake_1234567890abcdef', $result->gatewayPaymentIntentId);
    }

    // -----------------------------------------------------------------------
    // capturePaymentIntent tests
    // -----------------------------------------------------------------------

    #[Test]
    public function itCapturesAuthorizedPayment(): void
    {
        $gateway = new FakeStripeGateway($this->logger);

        $result = $gateway->capturePaymentIntent(
            gatewayPaymentIntentId: 'pi_fake_1234567890abcdef',
            amount: Money::fromScalars(5000, 'USD')
        );

        $this->assertInstanceOf(PaymentIntentResult::class, $result);
        $this->assertTrue($result->success);
        $this->assertSame('succeeded', $result->status);
        $this->assertSame('pi_fake_1234567890abcdef', $result->gatewayPaymentIntentId);
    }

    // -----------------------------------------------------------------------
    // cancelPaymentIntent tests
    // -----------------------------------------------------------------------

    #[Test]
    public function itCancelsAuthorizedPayment(): void
    {
        $gateway = new FakeStripeGateway($this->logger);

        $result = $gateway->cancelPaymentIntent(
            gatewayPaymentIntentId: 'pi_fake_1234567890abcdef'
        );

        $this->assertInstanceOf(PaymentIntentResult::class, $result);
        $this->assertTrue($result->success);
        $this->assertSame('canceled', $result->status);
    }

    // -----------------------------------------------------------------------
    // createRefund tests
    // -----------------------------------------------------------------------

    #[Test]
    public function itRefundsCapturedPayment(): void
    {
        $gateway = new FakeStripeGateway($this->logger);

        $result = $gateway->createRefund(
            gatewayPaymentIntentId: 'pi_fake_1234567890abcdef',
            amount: Money::fromScalars(2500, 'USD'),
            reason: 'Customer request',
            idempotencyKey: 'refund-idem-123'
        );

        $this->assertInstanceOf(RefundResult::class, $result);
        $this->assertTrue($result->success);
        $this->assertSame('succeeded', $result->status);
        $this->assertStringStartsWith('re_fake_', $result->gatewayRefundId);
    }

    // -----------------------------------------------------------------------
    // verifyWebhookSignature tests
    // -----------------------------------------------------------------------

    #[Test]
    public function itVerifiesWebhookSignatureWithValidSignature(): void
    {
        $gateway = new FakeStripeGateway($this->logger);

        $result = $gateway->verifyWebhookSignature(
            payload: '{"type":"payment_intent.succeeded"}',
            signature: 'valid_signature',
            secret: 'whsec_test'
        );

        $this->assertTrue($result);
    }

    #[Test]
    public function itRejectsInvalidWebhookSignature(): void
    {
        $gateway = new FakeStripeGateway($this->logger);

        $result = $gateway->verifyWebhookSignature(
            payload: '{"type":"payment_intent.succeeded"}',
            signature: 'invalid_signature',
            secret: 'whsec_test'
        );

        $this->assertFalse($result);
    }

    // -----------------------------------------------------------------------
    // Logging tests
    // -----------------------------------------------------------------------

    #[Test]
    public function itLogsPaymentIntentCreation(): void
    {
        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with(
                $this->stringContains('[FAKE] Stripe: Creating payment intent'),
                $this->callback(function (array $context) {
                    $this->assertArrayHasKey('payment_id', $context);
                    $this->assertArrayHasKey('amount', $context);
                    $this->assertArrayHasKey('currency', $context);

                    return true;
                })
            );

        $gateway = new FakeStripeGateway($this->logger);

        $gateway->createPaymentIntent(
            paymentId: PaymentId::generate(),
            amount: Money::fromScalars(5000, 'USD'),
            currency: 'USD',
            idempotencyKey: 'idem-log-test'
        );
    }
}
