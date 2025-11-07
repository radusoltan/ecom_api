<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Infrastructure\Gateway;

use App\Payment\Domain\Service\PaymentGatewayInterface;
use App\Payment\Domain\ValueObject\PaymentMethod;
use App\Payment\Infrastructure\Gateway\FakeStripeGateway;
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

        $this->assertSame('stripe', $gateway->getName());
    }

    #[Test]
    public function itAuthorizesPaymentWithout3dsByDefault(): void
    {
        $gateway = new FakeStripeGateway($this->logger, require3DS: false);

        $result = $gateway->authorize(
            amountInCents: 5000,
            currency: 'USD',
            method: PaymentMethod::card(),
            metadata: ['order_id' => 'ORD-123']
        );

        $this->assertArrayHasKey('transaction_id', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('metadata', $result);

        $this->assertSame('requires_capture', $result['status']);
        $this->assertStringStartsWith('pi_fake_', $result['transaction_id']);
        $this->assertSame(5000, $result['metadata']['amount']);
        $this->assertSame('usd', $result['metadata']['currency']);
        $this->assertArrayHasKey('payment_method_types', $result['metadata']);
    }

    #[Test]
    public function itRequires3dsAuthenticationWhenEnabled(): void
    {
        $gateway = new FakeStripeGateway($this->logger, require3DS: true);

        $result = $gateway->authorize(
            amountInCents: 5000,
            currency: 'EUR',
            method: PaymentMethod::card(),
            metadata: ['order_id' => 'ORD-456', 'return_url' => 'https://example.com/return']
        );

        $this->assertSame('requires_action', $result['status']);
        $this->assertArrayHasKey('next_action', $result);
        $this->assertArrayHasKey('type', $result['next_action']);
        $this->assertSame('redirect_to_url', $result['next_action']['type']);
    }

    #[Test]
    public function itIncludes3dsChallengeUrlWhenAuthenticationRequired(): void
    {
        $gateway = new FakeStripeGateway($this->logger, require3DS: true);

        $result = $gateway->authorize(
            amountInCents: 10000,
            currency: 'GBP',
            method: PaymentMethod::card(),
            metadata: ['return_url' => 'https://example.com/payment/confirm']
        );

        $this->assertArrayHasKey('redirect_to_url', $result['next_action']);
        $this->assertArrayHasKey('url', $result['next_action']['redirect_to_url']);
        $this->assertArrayHasKey('return_url', $result['next_action']['redirect_to_url']);

        $this->assertStringContainsString('3d_secure', $result['next_action']['redirect_to_url']['url']);
        $this->assertSame('https://example.com/payment/confirm', $result['next_action']['redirect_to_url']['return_url']);
    }

    #[Test]
    public function itMarks3dsRequiredInMetadata(): void
    {
        $gateway = new FakeStripeGateway($this->logger, require3DS: true);

        $result = $gateway->authorize(
            amountInCents: 7500,
            currency: 'EUR',
            method: PaymentMethod::card()
        );

        $this->assertArrayHasKey('metadata', $result);
        $this->assertArrayHasKey('requires_3ds', $result['metadata']);
        $this->assertTrue($result['metadata']['requires_3ds']);
    }

    #[Test]
    public function itAllowsForcing3dsViaMetadata(): void
    {
        $gateway = new FakeStripeGateway($this->logger, require3DS: false);

        $result = $gateway->authorize(
            amountInCents: 5000,
            currency: 'USD',
            method: PaymentMethod::card(),
            metadata: ['force_3ds' => true]
        );

        $this->assertSame('requires_action', $result['status']);
        $this->assertArrayHasKey('next_action', $result);
    }

    #[Test]
    public function itCapturesAuthorizedPayment(): void
    {
        $gateway = new FakeStripeGateway($this->logger);

        $result = $gateway->capture(
            transactionId: 'pi_fake_1234567890abcdef',
            amountInCents: 5000
        );

        $this->assertArrayHasKey('transaction_id', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('captured_amount', $result);

        $this->assertSame('succeeded', $result['status']);
        $this->assertSame(5000, $result['captured_amount']);
    }

    #[Test]
    public function itGetsPaymentStatusAfter3dsAuthentication(): void
    {
        $gateway = new FakeStripeGateway($this->logger, require3DS: true);

        $result = $gateway->getStatus('pi_fake_1234567890abcdef');

        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('metadata', $result);

        // After 3DS authentication, status should be 'requires_capture'
        $this->assertSame('requires_capture', $result['status']);
        $this->assertArrayHasKey('3ds_authenticated', $result['metadata']);
        $this->assertTrue($result['metadata']['3ds_authenticated']);
    }

    #[Test]
    public function itRefundsCapturedPayment(): void
    {
        $gateway = new FakeStripeGateway($this->logger);

        $result = $gateway->refund(
            transactionId: 'pi_fake_1234567890abcdef',
            amountInCents: 2500,
            reason: 'Customer request'
        );

        $this->assertArrayHasKey('refund_id', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('refunded_amount', $result);

        $this->assertSame('succeeded', $result['status']);
        $this->assertSame(2500, $result['refunded_amount']);
        $this->assertStringStartsWith('re_fake_', $result['refund_id']);
    }

    #[Test]
    public function itCancelsAuthorizedPayment(): void
    {
        $gateway = new FakeStripeGateway($this->logger);

        $result = $gateway->cancel(
            transactionId: 'pi_fake_1234567890abcdef',
            reason: 'Timeout'
        );

        $this->assertArrayHasKey('status', $result);
        $this->assertSame('canceled', $result['status']);
    }

    #[Test]
    public function itLogsPaymentOperations(): void
    {
        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with(
                $this->stringContains('[FAKE] Stripe: Authorizing payment'),
                $this->callback(function (array $context) {
                    $this->assertArrayHasKey('amount', $context);
                    $this->assertArrayHasKey('currency', $context);
                    $this->assertArrayHasKey('payment_method_types', $context);
                    $this->assertSame(5000, $context['amount']);
                    $this->assertSame('USD', $context['currency']);

                    return true;
                })
            );

        $gateway = new FakeStripeGateway($this->logger);

        $gateway->authorize(
            amountInCents: 5000,
            currency: 'USD',
            method: PaymentMethod::card()
        );
    }
}
