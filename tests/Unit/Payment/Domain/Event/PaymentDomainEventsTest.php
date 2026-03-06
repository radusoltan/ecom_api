<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Domain\Event;

use App\Payment\Domain\Event\PaymentAuthorized;
use App\Payment\Domain\Event\PaymentCancelled;
use App\Payment\Domain\Event\PaymentCaptured;
use App\Payment\Domain\Event\PaymentCreated;
use App\Payment\Domain\Event\PaymentFailed;
use App\Payment\Domain\Event\PaymentRefunded;
use App\Payment\Domain\Event\PaymentRetryAttempted;
use App\Payment\Domain\Event\PaymentRetryExhausted;
use App\Payment\Domain\Event\PaymentRetryScheduled;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PaymentAuthorized::class)]
#[CoversClass(PaymentCancelled::class)]
#[CoversClass(PaymentCaptured::class)]
#[CoversClass(PaymentCreated::class)]
#[CoversClass(PaymentFailed::class)]
#[CoversClass(PaymentRefunded::class)]
#[CoversClass(PaymentRetryAttempted::class)]
#[CoversClass(PaymentRetryExhausted::class)]
#[CoversClass(PaymentRetryScheduled::class)]
final class PaymentDomainEventsTest extends TestCase
{
    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function makePaymentId(): PaymentId
    {
        return PaymentId::generate();
    }

    private function makeTenantId(): TenantId
    {
        return TenantId::fromString('00000000-0000-4000-8000-000000000001');
    }

    private function makeMoney(int $cents = 9999, string $currency = 'USD'): Money
    {
        return Money::fromScalars($cents, $currency);
    }

    // ---------------------------------------------------------------
    // PaymentCreated
    // ---------------------------------------------------------------

    #[Test]
    public function itStoresAllPropertiesOnPaymentCreated(): void
    {
        $paymentId = $this->makePaymentId();
        $tenantId = $this->makeTenantId();
        $amount = $this->makeMoney(9999, 'USD');

        $event = new PaymentCreated(
            paymentId: $paymentId,
            tenantId: $tenantId,
            orderId: 'order-abc-123',
            amount: $amount,
            gateway: 'stripe',
        );

        self::assertSame($paymentId, $event->paymentId);
        self::assertSame($tenantId, $event->tenantId);
        self::assertSame('order-abc-123', $event->orderId);
        self::assertSame($amount, $event->amount);
        self::assertSame('stripe', $event->gateway);
    }

    #[Test]
    public function itAcceptsPaypalGatewayOnPaymentCreated(): void
    {
        $event = new PaymentCreated(
            paymentId: $this->makePaymentId(),
            tenantId: $this->makeTenantId(),
            orderId: 'order-xyz',
            amount: $this->makeMoney(5000, 'EUR'),
            gateway: 'paypal',
        );

        self::assertSame('paypal', $event->gateway);
        self::assertSame(5000, $event->amount->getAmount());
        self::assertSame('EUR', $event->amount->getCurrency()->getCurrencyCode());
    }

    // ---------------------------------------------------------------
    // PaymentAuthorized
    // ---------------------------------------------------------------

    #[Test]
    public function itStoresAllPropertiesOnPaymentAuthorized(): void
    {
        $paymentId = $this->makePaymentId();
        $tenantId = $this->makeTenantId();

        $event = new PaymentAuthorized(
            paymentId: $paymentId,
            tenantId: $tenantId,
            gatewayTransactionId: 'pi_stripe_abc123',
        );

        self::assertSame($paymentId, $event->paymentId);
        self::assertSame($tenantId, $event->tenantId);
        self::assertSame('pi_stripe_abc123', $event->gatewayTransactionId);
    }

    #[Test]
    public function itAcceptsArbitraryTransactionIdOnPaymentAuthorized(): void
    {
        $event = new PaymentAuthorized(
            paymentId: $this->makePaymentId(),
            tenantId: $this->makeTenantId(),
            gatewayTransactionId: 'pp_paypal_xyz789',
        );

        self::assertSame('pp_paypal_xyz789', $event->gatewayTransactionId);
    }

    // ---------------------------------------------------------------
    // PaymentCancelled
    // ---------------------------------------------------------------

    #[Test]
    public function itStoresAllPropertiesOnPaymentCancelled(): void
    {
        $paymentId = $this->makePaymentId();
        $tenantId = $this->makeTenantId();

        $event = new PaymentCancelled(
            paymentId: $paymentId,
            tenantId: $tenantId,
            reason: 'Customer requested cancellation',
        );

        self::assertSame($paymentId, $event->paymentId);
        self::assertSame($tenantId, $event->tenantId);
        self::assertSame('Customer requested cancellation', $event->reason);
    }

    #[Test]
    public function itStoresArbitraryReasonOnPaymentCancelled(): void
    {
        $event = new PaymentCancelled(
            paymentId: $this->makePaymentId(),
            tenantId: $this->makeTenantId(),
            reason: 'Inventory unavailable',
        );

        self::assertSame('Inventory unavailable', $event->reason);
    }

    // ---------------------------------------------------------------
    // PaymentCaptured
    // ---------------------------------------------------------------

    #[Test]
    public function itStoresAllPropertiesOnPaymentCaptured(): void
    {
        $paymentId = $this->makePaymentId();
        $tenantId = $this->makeTenantId();
        $capturedAmount = $this->makeMoney(9999, 'USD');

        $event = new PaymentCaptured(
            paymentId: $paymentId,
            tenantId: $tenantId,
            capturedAmount: $capturedAmount,
            orderId: 'order-capture-001',
        );

        self::assertSame($paymentId, $event->paymentId);
        self::assertSame($tenantId, $event->tenantId);
        self::assertSame($capturedAmount, $event->capturedAmount);
        self::assertSame('order-capture-001', $event->orderId);
    }

    #[Test]
    public function itDefaultsOrderIdToNullOnPaymentCaptured(): void
    {
        $event = new PaymentCaptured(
            paymentId: $this->makePaymentId(),
            tenantId: $this->makeTenantId(),
            capturedAmount: $this->makeMoney(5000, 'GBP'),
        );

        self::assertNull($event->orderId);
        self::assertSame(5000, $event->capturedAmount->getAmount());
    }

    // ---------------------------------------------------------------
    // PaymentFailed
    // ---------------------------------------------------------------

    #[Test]
    public function itStoresAllPropertiesOnPaymentFailed(): void
    {
        $paymentId = $this->makePaymentId();
        $tenantId = $this->makeTenantId();

        $event = new PaymentFailed(
            paymentId: $paymentId,
            tenantId: $tenantId,
            errorMessage: 'Card declined',
        );

        self::assertSame($paymentId, $event->paymentId);
        self::assertSame($tenantId, $event->tenantId);
        self::assertSame('Card declined', $event->errorMessage);
    }

    #[Test]
    public function itStoresDetailedErrorMessageOnPaymentFailed(): void
    {
        $event = new PaymentFailed(
            paymentId: $this->makePaymentId(),
            tenantId: $this->makeTenantId(),
            errorMessage: 'Insufficient funds: balance too low',
        );

        self::assertSame('Insufficient funds: balance too low', $event->errorMessage);
    }

    // ---------------------------------------------------------------
    // PaymentRefunded
    // ---------------------------------------------------------------

    #[Test]
    public function itStoresAllPropertiesOnPaymentRefunded(): void
    {
        $paymentId = $this->makePaymentId();
        $tenantId = $this->makeTenantId();
        $refundedAmount = $this->makeMoney(4999, 'USD');

        $event = new PaymentRefunded(
            paymentId: $paymentId,
            tenantId: $tenantId,
            refundedAmount: $refundedAmount,
            reason: 'Product returned',
        );

        self::assertSame($paymentId, $event->paymentId);
        self::assertSame($tenantId, $event->tenantId);
        self::assertSame($refundedAmount, $event->refundedAmount);
        self::assertSame('Product returned', $event->reason);
    }

    #[Test]
    public function itAcceptsPartialRefundAmountOnPaymentRefunded(): void
    {
        $event = new PaymentRefunded(
            paymentId: $this->makePaymentId(),
            tenantId: $this->makeTenantId(),
            refundedAmount: $this->makeMoney(1000, 'EUR'),
            reason: 'Damaged item',
        );

        self::assertSame(1000, $event->refundedAmount->getAmount());
        self::assertSame('EUR', $event->refundedAmount->getCurrency()->getCurrencyCode());
    }

    // ---------------------------------------------------------------
    // PaymentRetryAttempted
    // ---------------------------------------------------------------

    #[Test]
    public function itStoresSuccessfulRetryAttempt(): void
    {
        $paymentId = $this->makePaymentId();
        $tenantId = $this->makeTenantId();

        $event = new PaymentRetryAttempted(
            paymentId: $paymentId,
            tenantId: $tenantId,
            attemptNumber: 2,
            wasSuccessful: true,
        );

        self::assertSame($paymentId, $event->paymentId);
        self::assertSame($tenantId, $event->tenantId);
        self::assertSame(2, $event->attemptNumber);
        self::assertTrue($event->wasSuccessful);
        self::assertNull($event->errorCode);
        self::assertNull($event->errorMessage);
    }

    #[Test]
    public function itStoresFailedRetryAttemptWithErrorDetails(): void
    {
        $event = new PaymentRetryAttempted(
            paymentId: $this->makePaymentId(),
            tenantId: $this->makeTenantId(),
            attemptNumber: 1,
            wasSuccessful: false,
            errorCode: 'card_declined',
            errorMessage: 'Your card was declined',
        );

        self::assertFalse($event->wasSuccessful);
        self::assertSame(1, $event->attemptNumber);
        self::assertSame('card_declined', $event->errorCode);
        self::assertSame('Your card was declined', $event->errorMessage);
    }

    #[Test]
    public function itDefaultsOptionalErrorFieldsToNullOnRetryAttempted(): void
    {
        $event = new PaymentRetryAttempted(
            paymentId: $this->makePaymentId(),
            tenantId: $this->makeTenantId(),
            attemptNumber: 3,
            wasSuccessful: false,
        );

        self::assertNull($event->errorCode);
        self::assertNull($event->errorMessage);
    }

    // ---------------------------------------------------------------
    // PaymentRetryExhausted
    // ---------------------------------------------------------------

    #[Test]
    public function itStoresAllPropertiesOnPaymentRetryExhausted(): void
    {
        $paymentId = $this->makePaymentId();
        $tenantId = $this->makeTenantId();

        $event = new PaymentRetryExhausted(
            paymentId: $paymentId,
            tenantId: $tenantId,
            totalAttempts: 3,
            lastErrorCode: 'card_declined',
            lastErrorMessage: 'Card repeatedly declined',
            orderId: 'order-retry-exhausted',
        );

        self::assertSame($paymentId, $event->paymentId);
        self::assertSame($tenantId, $event->tenantId);
        self::assertSame(3, $event->totalAttempts);
        self::assertSame('card_declined', $event->lastErrorCode);
        self::assertSame('Card repeatedly declined', $event->lastErrorMessage);
        self::assertSame('order-retry-exhausted', $event->orderId);
    }

    #[Test]
    public function itRecordsMaxAttemptsOnPaymentRetryExhausted(): void
    {
        $event = new PaymentRetryExhausted(
            paymentId: $this->makePaymentId(),
            tenantId: $this->makeTenantId(),
            totalAttempts: 3,
            lastErrorCode: 'insufficient_funds',
            lastErrorMessage: 'Not enough funds after 3 tries',
            orderId: 'order-001',
        );

        self::assertSame(3, $event->totalAttempts);
        self::assertSame('insufficient_funds', $event->lastErrorCode);
    }

    // ---------------------------------------------------------------
    // PaymentRetryScheduled
    // ---------------------------------------------------------------

    #[Test]
    public function itStoresAllPropertiesOnPaymentRetryScheduled(): void
    {
        $paymentId = $this->makePaymentId();
        $tenantId = $this->makeTenantId();
        $scheduledFor = new \DateTimeImmutable('2026-03-04 10:00:00');

        $event = new PaymentRetryScheduled(
            paymentId: $paymentId,
            tenantId: $tenantId,
            retryAttempt: 1,
            scheduledFor: $scheduledFor,
            errorCode: 'processing_error',
            orderId: 'order-scheduled-retry',
        );

        self::assertSame($paymentId, $event->paymentId);
        self::assertSame($tenantId, $event->tenantId);
        self::assertSame(1, $event->retryAttempt);
        self::assertSame($scheduledFor, $event->scheduledFor);
        self::assertSame('processing_error', $event->errorCode);
        self::assertSame('order-scheduled-retry', $event->orderId);
    }

    #[Test]
    public function itStoresSubsequentRetryAttemptOnPaymentRetryScheduled(): void
    {
        $scheduledFor = new \DateTimeImmutable('+4 hours');

        $event = new PaymentRetryScheduled(
            paymentId: $this->makePaymentId(),
            tenantId: $this->makeTenantId(),
            retryAttempt: 2,
            scheduledFor: $scheduledFor,
            errorCode: 'network_error',
            orderId: 'order-002',
        );

        self::assertSame(2, $event->retryAttempt);
        self::assertSame($scheduledFor, $event->scheduledFor);
        self::assertSame('network_error', $event->errorCode);
    }
}
