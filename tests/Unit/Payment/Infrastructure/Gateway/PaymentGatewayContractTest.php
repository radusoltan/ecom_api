<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Infrastructure\Gateway;

use App\Payment\Domain\Model\PaymentId;
use App\Payment\Domain\Model\PaymentMethod;
use App\Payment\Domain\Service\PaymentGatewayInterface;
use App\Payment\Domain\Service\PaymentIntentResult;
use App\Payment\Domain\Service\RefundResult;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Contract test for PaymentGatewayInterface implementations.
 *
 * Every concrete gateway (FakeStripeGateway, FakePayPalGateway, StripeGateway, …)
 * must satisfy these behavioural guarantees.  Concrete subclasses implement the
 * three abstract factory/configuration helpers and inherit all test methods.
 *
 * Tested contract points:
 * - getGatewayId() returns the expected PaymentMethod enum case
 * - getName()      returns a non-empty string matching the expected value
 * - createPaymentIntent() returns a PaymentIntentResult with a populated gatewayPaymentIntentId
 * - createPaymentIntent() accepts an optional metadata array without throwing
 * - confirmPaymentIntent() echoes back the gatewayPaymentIntentId and marks the result successful
 * - capturePaymentIntent() echoes back the gatewayPaymentIntentId and marks the result successful
 * - cancelPaymentIntent()  echoes back the gatewayPaymentIntentId and uses status "canceled"
 * - createRefund() returns a RefundResult with a non-empty gatewayRefundId
 * - createRefund() with a partial (smaller) amount does not throw
 * - verifyWebhookSignature() returns false for clearly invalid data
 * - Calling createPaymentIntent() twice with the same idempotency key does not throw
 */
abstract class PaymentGatewayContractTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Abstract configuration — subclasses provide these
    // -----------------------------------------------------------------------

    /**
     * Return the gateway under test, freshly instantiated (no shared state).
     */
    abstract protected function createGateway(): PaymentGatewayInterface;

    /**
     * Return the PaymentMethod enum case the gateway should report.
     */
    abstract protected function getExpectedGatewayId(): PaymentMethod;

    /**
     * Return the exact string getName() must return.
     */
    abstract protected function getExpectedName(): string;

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Create a standard payment intent and return its result plus the used gateway ID.
     *
     * @return array{0: PaymentIntentResult, 1: string}
     */
    private function createStandardIntent(PaymentGatewayInterface $gateway): array
    {
        $amount = Money::fromScalars(10000, 'USD');
        $result = $gateway->createPaymentIntent(
            paymentId: PaymentId::generate(),
            amount: $amount,
            currency: 'USD',
            idempotencyKey: 'contract-test-' . bin2hex(random_bytes(8)),
        );

        return [$result, $result->gatewayPaymentIntentId];
    }

    // -----------------------------------------------------------------------
    // Gateway identity
    // -----------------------------------------------------------------------

    #[Test]
    public function testGetGatewayId(): void
    {
        $gateway = $this->createGateway();

        $this->assertSame($this->getExpectedGatewayId(), $gateway->getGatewayId());
    }

    #[Test]
    public function testGetName(): void
    {
        $gateway = $this->createGateway();
        $name    = $gateway->getName();

        $this->assertNotEmpty($name, 'getName() must return a non-empty string');
        $this->assertSame($this->getExpectedName(), $name);
    }

    // -----------------------------------------------------------------------
    // createPaymentIntent
    // -----------------------------------------------------------------------

    #[Test]
    public function testCreatePaymentIntentReturnsResult(): void
    {
        $gateway = $this->createGateway();
        $amount  = Money::fromScalars(5000, 'USD');

        $result = $gateway->createPaymentIntent(
            paymentId: PaymentId::generate(),
            amount: $amount,
            currency: 'USD',
            idempotencyKey: 'contract-create-intent',
        );

        $this->assertInstanceOf(PaymentIntentResult::class, $result);
        $this->assertNotEmpty(
            $result->gatewayPaymentIntentId,
            'createPaymentIntent() must return a non-empty gatewayPaymentIntentId',
        );
    }

    #[Test]
    public function testCreatePaymentIntentWithMetadata(): void
    {
        $gateway  = $this->createGateway();
        $metadata = [
            'order_id'  => 'ORD-CONTRACT-001',
            'tenant_id' => '00000000-0000-4000-8000-000000000001',
            'channel'   => 'web',
        ];

        $result = $gateway->createPaymentIntent(
            paymentId: PaymentId::generate(),
            amount: Money::fromScalars(7500, 'USD'),
            currency: 'USD',
            idempotencyKey: 'contract-metadata-key',
            customerId: 'cust_contract_01',
            metadata: $metadata,
        );

        // The gateway must accept metadata without throwing and still return a valid result.
        $this->assertInstanceOf(PaymentIntentResult::class, $result);
        $this->assertNotEmpty($result->gatewayPaymentIntentId);
    }

    // -----------------------------------------------------------------------
    // confirmPaymentIntent
    // -----------------------------------------------------------------------

    #[Test]
    public function testConfirmPaymentIntent(): void
    {
        $gateway = $this->createGateway();
        [$createResult, $intentId] = $this->createStandardIntent($gateway);

        $confirmResult = $gateway->confirmPaymentIntent(
            gatewayPaymentIntentId: $intentId,
            paymentMethodId: 'pm_contract_card_visa',
        );

        $this->assertInstanceOf(PaymentIntentResult::class, $confirmResult);
        // The confirmed result must echo back the same gateway payment intent ID.
        $this->assertSame($intentId, $confirmResult->gatewayPaymentIntentId);
        // After confirmation, a successful fake gateway marks the intent as succeeded.
        $this->assertSame('succeeded', $confirmResult->status);
        $this->assertTrue($confirmResult->isCaptured());
    }

    // -----------------------------------------------------------------------
    // capturePaymentIntent
    // -----------------------------------------------------------------------

    #[Test]
    public function testCapturePaymentIntent(): void
    {
        $gateway = $this->createGateway();
        [$createResult, $intentId] = $this->createStandardIntent($gateway);

        // Confirm first so the intent is in a confirmable state.
        $gateway->confirmPaymentIntent(
            gatewayPaymentIntentId: $intentId,
            paymentMethodId: 'pm_contract_card_visa',
        );

        $captureResult = $gateway->capturePaymentIntent(
            gatewayPaymentIntentId: $intentId,
            amount: Money::fromScalars(10000, 'USD'),
        );

        $this->assertInstanceOf(PaymentIntentResult::class, $captureResult);
        $this->assertSame($intentId, $captureResult->gatewayPaymentIntentId);
        $this->assertSame('succeeded', $captureResult->status);
        $this->assertTrue($captureResult->isCaptured());
    }

    // -----------------------------------------------------------------------
    // cancelPaymentIntent
    // -----------------------------------------------------------------------

    #[Test]
    public function testCancelPaymentIntent(): void
    {
        $gateway = $this->createGateway();
        [$createResult, $intentId] = $this->createStandardIntent($gateway);

        $cancelResult = $gateway->cancelPaymentIntent(
            gatewayPaymentIntentId: $intentId,
        );

        $this->assertInstanceOf(PaymentIntentResult::class, $cancelResult);
        $this->assertSame($intentId, $cancelResult->gatewayPaymentIntentId);
        $this->assertSame('canceled', $cancelResult->status);
        $this->assertTrue($cancelResult->isFinal());
    }

    // -----------------------------------------------------------------------
    // createRefund
    // -----------------------------------------------------------------------

    #[Test]
    public function testCreateRefundReturnsResult(): void
    {
        $gateway = $this->createGateway();
        [$createResult, $intentId] = $this->createStandardIntent($gateway);

        $refundResult = $gateway->createRefund(
            gatewayPaymentIntentId: $intentId,
            amount: Money::fromScalars(10000, 'USD'),
            reason: 'customer_request',
            idempotencyKey: 'contract-refund-full',
        );

        $this->assertInstanceOf(RefundResult::class, $refundResult);
        $this->assertNotEmpty(
            $refundResult->gatewayRefundId,
            'createRefund() must return a non-empty gatewayRefundId',
        );
        $this->assertSame('succeeded', $refundResult->status);
        $this->assertTrue($refundResult->isSucceeded());
    }

    #[Test]
    public function testCreateRefundPartialAmount(): void
    {
        $gateway = $this->createGateway();
        [$createResult, $intentId] = $this->createStandardIntent($gateway);

        // Partial refund: 4000 out of 10000 minor units.
        $partialAmount = Money::fromScalars(4000, 'USD');

        $refundResult = $gateway->createRefund(
            gatewayPaymentIntentId: $intentId,
            amount: $partialAmount,
            reason: 'partial_return',
            idempotencyKey: 'contract-refund-partial',
        );

        $this->assertInstanceOf(RefundResult::class, $refundResult);
        $this->assertNotEmpty($refundResult->gatewayRefundId);
        // Partial refunds must still succeed in the fake implementation.
        $this->assertSame('succeeded', $refundResult->status);
    }

    // -----------------------------------------------------------------------
    // verifyWebhookSignature
    // -----------------------------------------------------------------------

    #[Test]
    public function testVerifyWebhookSignatureWithInvalidData(): void
    {
        $gateway = $this->createGateway();

        $isValid = $gateway->verifyWebhookSignature(
            payload: '{"type":"payment_intent.succeeded","data":{}}',
            signature: 'totally_invalid_signature_xyz',
            secret: 'whsec_contract_test',
        );

        $this->assertFalse(
            $isValid,
            'verifyWebhookSignature() must return false for an invalid signature',
        );
    }

    #[Test]
    public function testVerifyWebhookSignatureWithValidSignature(): void
    {
        $gateway = $this->createGateway();

        // Both fakes accept the sentinel value 'valid_signature' as the shared contract.
        $isValid = $gateway->verifyWebhookSignature(
            payload: '{"type":"payment_intent.succeeded"}',
            signature: 'valid_signature',
            secret: 'whsec_contract_test',
        );

        $this->assertTrue(
            $isValid,
            'verifyWebhookSignature() must return true for the known-valid sentinel signature',
        );
    }

    // -----------------------------------------------------------------------
    // Idempotency
    // -----------------------------------------------------------------------

    #[Test]
    public function testIdempotencyKeyIsAccepted(): void
    {
        $gateway        = $this->createGateway();
        $idempotencyKey = 'contract-idempotency-fixed-key';
        $paymentId      = PaymentId::generate();
        $amount         = Money::fromScalars(3000, 'USD');

        // First call — must not throw.
        $first = $gateway->createPaymentIntent(
            paymentId: $paymentId,
            amount: $amount,
            currency: 'USD',
            idempotencyKey: $idempotencyKey,
        );

        // Second call with the same key — must not throw either.
        $second = $gateway->createPaymentIntent(
            paymentId: $paymentId,
            amount: $amount,
            currency: 'USD',
            idempotencyKey: $idempotencyKey,
        );

        $this->assertInstanceOf(PaymentIntentResult::class, $first);
        $this->assertInstanceOf(PaymentIntentResult::class, $second);
    }
}
