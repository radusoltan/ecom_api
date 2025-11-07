<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Domain\Model;

use App\Payment\Domain\Event\PaymentAuthorized;
use App\Payment\Domain\Event\PaymentCancelled;
use App\Payment\Domain\Event\PaymentCaptured;
use App\Payment\Domain\Event\PaymentCreated;
use App\Payment\Domain\Event\PaymentFailed;
use App\Payment\Domain\Event\PaymentRefunded;
use App\Payment\Domain\Model\Payment;
use App\Payment\Domain\ValueObject\PaymentGateway;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Payment\Domain\ValueObject\PaymentMethod;
use App\Payment\Domain\ValueObject\PaymentStatus;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class PaymentTest extends TestCase
{
    private PaymentId $paymentId;
    private TenantId $tenantId;
    private string $orderId;

    protected function setUp(): void
    {
        $this->paymentId = PaymentId::generate();
        $this->tenantId = TenantId::generate();
        $this->orderId = '01JCEXAMPLE987654321XYZ';
    }

    public function testCreatePayment(): void
    {
        $payment = Payment::create(
            id: $this->paymentId,
            tenantId: $this->tenantId,
            orderId: $this->orderId,
            amountInCents: 9999,
            currency: 'USD',
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe()
        );

        $this->assertTrue($payment->id()->equals($this->paymentId));
        $this->assertTrue($payment->tenantId()->equals($this->tenantId));
        $this->assertSame($this->orderId, $payment->orderId());
        $this->assertSame(9999, $payment->amountInCents());
        $this->assertSame('USD', $payment->currency());
        $this->assertTrue($payment->method()->isCard());
        $this->assertTrue($payment->gateway()->isStripe());
        $this->assertTrue($payment->status()->isPending());
        $this->assertNull($payment->gatewayTransactionId());
        $this->assertNull($payment->errorMessage());
        $this->assertSame(0, $payment->refundedAmountInCents());

        // Check domain event
        $events = $payment->popEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(PaymentCreated::class, $events[0]);
    }

    public function testCreatePaymentWithInvalidAmountThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Payment amount must be greater than 0');

        Payment::create(
            id: $this->paymentId,
            tenantId: $this->tenantId,
            orderId: $this->orderId,
            amountInCents: 0,
            currency: 'USD',
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe()
        );
    }

    public function testCreatePaymentWithExcessiveAmountThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Payment amount exceeds maximum allowed');

        Payment::create(
            id: $this->paymentId,
            tenantId: $this->tenantId,
            orderId: $this->orderId,
            amountInCents: 100_000_001, // > $1,000,000.00
            currency: 'USD',
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe()
        );
    }

    public function testCreatePaymentWithInvalidCurrencyThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Invalid currency code/');

        Payment::create(
            id: $this->paymentId,
            tenantId: $this->tenantId,
            orderId: $this->orderId,
            amountInCents: 9999,
            currency: 'US', // Invalid - must be 3 letters
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe()
        );
    }

    public function testAuthorizePayment(): void
    {
        $payment = $this->createPendingPayment();
        $gatewayTransactionId = 'pi_abc123xyz';

        $payment->authorize($gatewayTransactionId);

        $this->assertTrue($payment->status()->isAuthorized());
        $this->assertSame($gatewayTransactionId, $payment->gatewayTransactionId());

        $events = $payment->popEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(PaymentAuthorized::class, $events[0]);
    }

    public function testAuthorizePaymentWithEmptyTransactionIdThrowsException(): void
    {
        $payment = $this->createPendingPayment();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Gateway transaction ID is required');

        $payment->authorize('');
    }

    public function testAuthorizeFailedPaymentThrowsException(): void
    {
        $payment = $this->createPendingPayment();
        $payment->markAsFailed('Card declined');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot authorize payment in status: failed');

        $payment->authorize('pi_abc123xyz');
    }

    public function testCapturePayment(): void
    {
        $payment = $this->createAuthorizedPayment();

        $payment->capture();

        $this->assertTrue($payment->status()->isCaptured());

        $events = $payment->popEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(PaymentCaptured::class, $events[0]);
    }

    public function testCapturePendingPaymentThrowsException(): void
    {
        $payment = $this->createPendingPayment();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot capture payment in status: pending');

        $payment->capture();
    }

    public function testRefundPayment(): void
    {
        $payment = $this->createCapturedPayment();
        $refundAmount = 5000;
        $reason = 'Customer requested refund';

        $payment->refund($refundAmount, $reason);

        $this->assertTrue($payment->status()->isRefunded());
        $this->assertSame($refundAmount, $payment->refundedAmountInCents());

        $events = $payment->popEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(PaymentRefunded::class, $events[0]);
    }

    public function testPartialRefundPayment(): void
    {
        $payment = $this->createCapturedPayment();

        // First refund: $50 (out of $99.99)
        $payment->refund(5000, 'Partial refund 1');
        $this->assertSame(5000, $payment->refundedAmountInCents());

        // After first refund, status changes to refunded
        // This is expected behavior - any refund changes status
        $this->assertTrue($payment->status()->isRefunded());
    }

    public function testRefundExceedsAvailableAmountThrowsException(): void
    {
        $payment = $this->createCapturedPayment(); // $99.99

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Refund amount.*exceeds available amount/');

        $payment->refund(10000, 'Refund exceeds captured amount'); // $100.00 > $99.99
    }

    public function testRefundWithEmptyReasonThrowsException(): void
    {
        $payment = $this->createCapturedPayment();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Refund reason is required');

        $payment->refund(5000, '');
    }

    public function testRefundPendingPaymentThrowsException(): void
    {
        $payment = $this->createPendingPayment();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot refund payment in status: pending');

        $payment->refund(5000, 'Cannot refund pending payment');
    }

    public function testCancelPayment(): void
    {
        $payment = $this->createPendingPayment();
        $reason = 'Customer cancelled order';

        $payment->cancel($reason);

        $this->assertTrue($payment->status()->isCancelled());

        $events = $payment->popEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(PaymentCancelled::class, $events[0]);
    }

    public function testCancelAuthorizedPayment(): void
    {
        $payment = $this->createAuthorizedPayment();
        $reason = 'Timeout';

        $payment->cancel($reason);

        $this->assertTrue($payment->status()->isCancelled());
    }

    public function testCancelWithEmptyReasonThrowsException(): void
    {
        $payment = $this->createPendingPayment();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cancellation reason is required');

        $payment->cancel('');
    }

    public function testCancelCapturedPaymentThrowsException(): void
    {
        $payment = $this->createCapturedPayment();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot cancel payment in status: captured');

        $payment->cancel('Cannot cancel captured payment');
    }

    public function testMarkAsFailedPayment(): void
    {
        $payment = $this->createPendingPayment();
        $errorMessage = 'Card declined by issuer';

        $payment->markAsFailed($errorMessage);

        $this->assertTrue($payment->status()->isFailed());
        $this->assertSame($errorMessage, $payment->errorMessage());

        $events = $payment->popEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(PaymentFailed::class, $events[0]);
    }

    public function testMarkAsFailedWithEmptyMessageThrowsException(): void
    {
        $payment = $this->createPendingPayment();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Error message is required');

        $payment->markAsFailed('');
    }

    public function testReconstituteFromPersistence(): void
    {
        $createdAt = new \DateTimeImmutable('2025-10-11 10:00:00');
        $updatedAt = new \DateTimeImmutable('2025-10-11 11:00:00');

        $payment = Payment::reconstituteFromPersistence(
            id: $this->paymentId,
            tenantId: $this->tenantId,
            orderId: $this->orderId,
            amountInCents: 9999,
            currency: 'USD',
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe(),
            status: PaymentStatus::captured(),
            gatewayTransactionId: 'pi_abc123xyz',
            errorMessage: null,
            refundedAmountInCents: 0,
            createdAt: $createdAt,
            updatedAt: $updatedAt
        );

        $this->assertTrue($payment->id()->equals($this->paymentId));
        $this->assertTrue($payment->status()->isCaptured());
        $this->assertSame('pi_abc123xyz', $payment->gatewayTransactionId());
        $this->assertSame($createdAt, $payment->createdAt());
        $this->assertSame($updatedAt, $payment->updatedAt());

        // Reconstituted payments should NOT have domain events
        $this->assertEmpty($payment->popEvents());
    }

    public function testPopEventsClearsEvents(): void
    {
        $payment = Payment::create(
            id: $this->paymentId,
            tenantId: $this->tenantId,
            orderId: $this->orderId,
            amountInCents: 9999,
            currency: 'USD',
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe()
        );

        // First pop - should return creation event
        $events1 = $payment->popEvents();
        $this->assertCount(1, $events1);

        // Second pop - should be empty
        $events2 = $payment->popEvents();
        $this->assertEmpty($events2);
    }

    // Helper methods
    private function createPendingPayment(): Payment
    {
        $payment = Payment::create(
            id: $this->paymentId,
            tenantId: $this->tenantId,
            orderId: $this->orderId,
            amountInCents: 9999,
            currency: 'USD',
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe()
        );
        $payment->popEvents(); // Clear creation event

        return $payment;
    }

    private function createAuthorizedPayment(): Payment
    {
        $payment = $this->createPendingPayment();
        $payment->authorize('pi_abc123xyz');
        $payment->popEvents(); // Clear authorization event

        return $payment;
    }

    private function createCapturedPayment(): Payment
    {
        $payment = $this->createAuthorizedPayment();
        $payment->capture();
        $payment->popEvents(); // Clear capture event

        return $payment;
    }
}
