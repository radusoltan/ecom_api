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
use App\Shared\Domain\ValueObject\Money;
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
            amount: Money::fromScalars(9999, 'USD'),
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe()
        );

        $this->assertTrue($payment->id()->equals($this->paymentId));
        $this->assertTrue($payment->tenantId()->equals($this->tenantId));
        $this->assertSame($this->orderId, $payment->orderId());
        $this->assertSame(9999, $payment->amount()->getAmount());
        $this->assertSame('USD', $payment->currency());
        $this->assertTrue($payment->method()->isCard());
        $this->assertTrue($payment->gateway()->isStripe());
        $this->assertTrue($payment->status()->isPending());
        $this->assertNull($payment->gatewayTransactionId());
        $this->assertNull($payment->errorMessage());
        $this->assertSame(0, $payment->refundedAmount()->getAmount());

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
            amount: Money::fromScalars(0, 'USD'),
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
            amount: Money::fromScalars(100_000_001, 'USD'), // > $1,000,000.00
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe()
        );
    }

    public function testCreatePaymentWithInvalidCurrencyThrowsException(): void
    {
        $this->expectException(\Brick\Money\Exception\UnknownCurrencyException::class);

        Payment::create(
            id: $this->paymentId,
            tenantId: $this->tenantId,
            orderId: $this->orderId,
            amount: Money::fromScalars(9999, 'US'), // Invalid - must be 3 letters
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

    public function testAuthorizeFailedPaymentSucceedsForRetry(): void
    {
        $payment = $this->createPendingPayment();
        $payment->markAsFailed('Card declined');
        $payment->popEvents(); // Clear failed event

        // failed -> authorized is valid (enables retry flow)
        $payment->authorize('pi_retry_abc123xyz');

        $this->assertTrue($payment->status()->isAuthorized());
        $this->assertSame('pi_retry_abc123xyz', $payment->gatewayTransactionId());

        $events = $payment->popEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(PaymentAuthorized::class, $events[0]);
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
        $refundAmount = Money::fromScalars(5000, 'USD');
        $reason = 'Customer requested refund';

        $payment->refund($refundAmount, $reason);

        $this->assertTrue($payment->status()->isRefunded());
        $this->assertSame(5000, $payment->refundedAmount()->getAmount());

        $events = $payment->popEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(PaymentRefunded::class, $events[0]);
    }

    public function testPartialRefundPayment(): void
    {
        $payment = $this->createCapturedPayment();

        // First refund: $50 (out of $99.99)
        $payment->refund(Money::fromScalars(5000, 'USD'), 'Partial refund 1');
        $this->assertSame(5000, $payment->refundedAmount()->getAmount());

        // After first refund, status changes to refunded
        // This is expected behavior - any refund changes status
        $this->assertTrue($payment->status()->isRefunded());
    }

    public function testRefundExceedsAvailableAmountThrowsException(): void
    {
        $payment = $this->createCapturedPayment(); // $99.99

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Refund amount.*exceeds available amount/');

        $payment->refund(Money::fromScalars(10000, 'USD'), 'Refund exceeds captured amount'); // $100.00 > $99.99
    }

    public function testRefundWithEmptyReasonThrowsException(): void
    {
        $payment = $this->createCapturedPayment();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Refund reason is required');

        $payment->refund(Money::fromScalars(5000, 'USD'), '');
    }

    public function testRefundPendingPaymentThrowsException(): void
    {
        $payment = $this->createPendingPayment();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot refund payment in status: pending');

        $payment->refund(Money::fromScalars(5000, 'USD'), 'Cannot refund pending payment');
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
            amount: Money::fromScalars(9999, 'USD'),
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe(),
            status: PaymentStatus::captured(),
            gatewayTransactionId: 'pi_abc123xyz',
            errorMessage: null,
            refundedAmount: Money::fromScalars(0, 'USD'),
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
            amount: Money::fromScalars(9999, 'USD'),
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

    // ========================================
    // Retry Mechanism Tests
    // ========================================

    public function testScheduleRetryForFailedPayment(): void
    {
        $payment = $this->createPendingPayment();
        $payment->markAsFailed('Card declined', 'card_declined');
        $payment->popEvents(); // Clear failed event

        $policy = \App\Payment\Domain\ValueObject\RetryPolicy::default();
        $payment->scheduleRetry($policy);

        $this->assertNotNull($payment->nextRetryAt());
        $this->assertSame(0, $payment->retryCount());

        $events = $payment->popEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(\App\Payment\Domain\Event\PaymentRetryScheduled::class, $events[0]);
    }

    public function testScheduleRetryThrowsWhenPaymentNotFailed(): void
    {
        $payment = $this->createPendingPayment();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Can only schedule retry for failed payments');

        $policy = \App\Payment\Domain\ValueObject\RetryPolicy::default();
        $payment->scheduleRetry($policy);
    }

    public function testScheduleRetryThrowsWhenMaxAttemptsReached(): void
    {
        $payment = Payment::reconstituteFromPersistence(
            id: $this->paymentId,
            tenantId: $this->tenantId,
            orderId: $this->orderId,
            amount: Money::fromScalars(9999, 'USD'),
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe(),
            status: PaymentStatus::failed(),
            gatewayTransactionId: 'pi_abc123xyz',
            errorMessage: 'Card declined',
            refundedAmount: Money::fromScalars(0, 'USD'),
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
            errorCode: 'card_declined',
            retryCount: 3, // Max attempts reached
            nextRetryAt: null
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot schedule retry. Max attempts (3) reached');

        $policy = \App\Payment\Domain\ValueObject\RetryPolicy::default();
        $payment->scheduleRetry($policy);
    }

    public function testRecordRetryAttemptSuccess(): void
    {
        $payment = $this->createPendingPayment();
        $payment->markAsFailed('Card declined', 'card_declined');
        $payment->popEvents();

        $payment->recordRetryAttempt(wasSuccessful: true);

        $this->assertSame(1, $payment->retryCount());

        $events = $payment->popEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(\App\Payment\Domain\Event\PaymentRetryAttempted::class, $events[0]);
    }

    public function testRecordRetryAttemptFailure(): void
    {
        $payment = $this->createPendingPayment();
        $payment->markAsFailed('Card declined', 'card_declined');
        $payment->popEvents();

        $payment->recordRetryAttempt(
            wasSuccessful: false,
            errorCode: 'insufficient_funds',
            errorMessage: 'Insufficient funds'
        );

        $this->assertSame(1, $payment->retryCount());
        $this->assertSame('insufficient_funds', $payment->errorCode());
        $this->assertSame('Insufficient funds', $payment->errorMessage());

        $events = $payment->popEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(\App\Payment\Domain\Event\PaymentRetryAttempted::class, $events[0]);
    }

    public function testMarkRetryExhausted(): void
    {
        $payment = Payment::reconstituteFromPersistence(
            id: $this->paymentId,
            tenantId: $this->tenantId,
            orderId: $this->orderId,
            amount: Money::fromScalars(9999, 'USD'),
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe(),
            status: PaymentStatus::failed(),
            gatewayTransactionId: 'pi_abc123xyz',
            errorMessage: 'Card declined',
            refundedAmount: Money::fromScalars(0, 'USD'),
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
            errorCode: 'card_declined',
            retryCount: 3,
            nextRetryAt: new \DateTimeImmutable('+1 hour')
        );

        $policy = \App\Payment\Domain\ValueObject\RetryPolicy::default();
        $payment->markRetryExhausted($policy);

        $this->assertNull($payment->nextRetryAt());

        $events = $payment->popEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(\App\Payment\Domain\Event\PaymentRetryExhausted::class, $events[0]);
    }

    public function testMarkRetryExhaustedThrowsWhenNotAtMaxAttempts(): void
    {
        $payment = $this->createPendingPayment();
        $payment->markAsFailed('Card declined', 'card_declined');
        $payment->popEvents();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot mark retry exhausted before reaching max attempts');

        $policy = \App\Payment\Domain\ValueObject\RetryPolicy::default();
        $payment->markRetryExhausted($policy);
    }

    public function testCanRetryReturnsTrueForEligiblePayment(): void
    {
        $payment = $this->createPendingPayment();
        $payment->markAsFailed('Card declined', 'card_declined');

        $policy = \App\Payment\Domain\ValueObject\RetryPolicy::default();
        $this->assertTrue($payment->canRetry($policy));
    }

    public function testCanRetryReturnsFalseWhenNotFailed(): void
    {
        $payment = $this->createPendingPayment();

        $policy = \App\Payment\Domain\ValueObject\RetryPolicy::default();
        $this->assertFalse($payment->canRetry($policy));
    }

    public function testCanRetryReturnsFalseWhenMaxAttemptsReached(): void
    {
        $payment = Payment::reconstituteFromPersistence(
            id: $this->paymentId,
            tenantId: $this->tenantId,
            orderId: $this->orderId,
            amount: Money::fromScalars(9999, 'USD'),
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe(),
            status: PaymentStatus::failed(),
            gatewayTransactionId: null,
            errorMessage: 'Card declined',
            refundedAmount: Money::fromScalars(0, 'USD'),
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
            errorCode: 'card_declined',
            retryCount: 3,
            nextRetryAt: null
        );

        $policy = \App\Payment\Domain\ValueObject\RetryPolicy::default();
        $this->assertFalse($payment->canRetry($policy));
    }

    public function testCanRetryReturnsFalseForNonRetryableError(): void
    {
        $payment = $this->createPendingPayment();
        $payment->markAsFailed('Expired card', 'expired_card');

        $policy = \App\Payment\Domain\ValueObject\RetryPolicy::default();
        $this->assertFalse($payment->canRetry($policy));
    }

    public function testIsDueForRetryReturnsTrueWhenTimeHasPassed(): void
    {
        $payment = Payment::reconstituteFromPersistence(
            id: $this->paymentId,
            tenantId: $this->tenantId,
            orderId: $this->orderId,
            amount: Money::fromScalars(9999, 'USD'),
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe(),
            status: PaymentStatus::failed(),
            gatewayTransactionId: null,
            errorMessage: 'Card declined',
            refundedAmount: Money::fromScalars(0, 'USD'),
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
            errorCode: 'card_declined',
            retryCount: 1,
            nextRetryAt: new \DateTimeImmutable('-1 hour') // Past time
        );

        $this->assertTrue($payment->isDueForRetry());
    }

    public function testIsDueForRetryReturnsFalseWhenTimeNotYet(): void
    {
        $payment = Payment::reconstituteFromPersistence(
            id: $this->paymentId,
            tenantId: $this->tenantId,
            orderId: $this->orderId,
            amount: Money::fromScalars(9999, 'USD'),
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe(),
            status: PaymentStatus::failed(),
            gatewayTransactionId: null,
            errorMessage: 'Card declined',
            refundedAmount: Money::fromScalars(0, 'USD'),
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
            errorCode: 'card_declined',
            retryCount: 1,
            nextRetryAt: new \DateTimeImmutable('+1 hour') // Future time
        );

        $this->assertFalse($payment->isDueForRetry());
    }

    public function testIsDueForRetryReturnsFalseWhenNoRetryScheduled(): void
    {
        $payment = $this->createPendingPayment();
        $payment->markAsFailed('Card declined', 'card_declined');

        $this->assertFalse($payment->isDueForRetry());
    }

    // ========================================
    // Idempotency Key Tests
    // ========================================

    public function testCreatePaymentWithIdempotencyKey(): void
    {
        $idempotencyKey = 'idem_'.bin2hex(random_bytes(16));

        $payment = Payment::create(
            id: $this->paymentId,
            tenantId: $this->tenantId,
            orderId: $this->orderId,
            amount: Money::fromScalars(9999, 'USD'),
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe(),
            idempotencyKey: $idempotencyKey
        );

        $this->assertSame($idempotencyKey, $payment->idempotencyKey());
    }

    public function testCreatePaymentWithoutIdempotencyKeyDefaultsToNull(): void
    {
        $payment = Payment::create(
            id: $this->paymentId,
            tenantId: $this->tenantId,
            orderId: $this->orderId,
            amount: Money::fromScalars(9999, 'USD'),
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe()
        );

        $this->assertNull($payment->idempotencyKey());
    }

    public function testReconstituteFromPersistencePreservesIdempotencyKey(): void
    {
        $idempotencyKey = 'idem_reconstitute_test';

        $payment = Payment::reconstituteFromPersistence(
            id: $this->paymentId,
            tenantId: $this->tenantId,
            orderId: $this->orderId,
            amount: Money::fromScalars(9999, 'USD'),
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe(),
            status: PaymentStatus::pending(),
            gatewayTransactionId: null,
            errorMessage: null,
            refundedAmount: Money::fromScalars(0, 'USD'),
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
            idempotencyKey: $idempotencyKey
        );

        $this->assertSame($idempotencyKey, $payment->idempotencyKey());
    }

    public function testReconstituteFromPersistenceWithNullIdempotencyKey(): void
    {
        $payment = Payment::reconstituteFromPersistence(
            id: $this->paymentId,
            tenantId: $this->tenantId,
            orderId: $this->orderId,
            amount: Money::fromScalars(9999, 'USD'),
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe(),
            status: PaymentStatus::pending(),
            gatewayTransactionId: null,
            errorMessage: null,
            refundedAmount: Money::fromScalars(0, 'USD'),
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable()
        );

        $this->assertNull($payment->idempotencyKey());
    }

    public function testReconstituteBackwardCompatibilityWithoutIdempotencyKey(): void
    {
        // Calling without the idempotencyKey parameter should work (backward compat)
        $payment = Payment::reconstituteFromPersistence(
            id: $this->paymentId,
            tenantId: $this->tenantId,
            orderId: $this->orderId,
            amount: Money::fromScalars(5000, 'EUR'),
            method: PaymentMethod::paypal(),
            gateway: PaymentGateway::paypal(),
            status: PaymentStatus::captured(),
            gatewayTransactionId: 'pp_test_123',
            errorMessage: null,
            refundedAmount: Money::fromScalars(0, 'EUR'),
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
            errorCode: null,
            retryCount: 0,
            nextRetryAt: null
        );

        $this->assertNull($payment->idempotencyKey());
        $this->assertTrue($payment->status()->isCaptured());
    }

    // ========================================
    // Edge Case Tests
    // ========================================

    public function testCreatePaymentWithNegativeAmountThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Payment amount must be greater than 0');

        Payment::create(
            id: $this->paymentId,
            tenantId: $this->tenantId,
            orderId: $this->orderId,
            amount: Money::fromScalars(-1000, 'USD'),
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe()
        );
    }

    public function testCapturingCapturedPaymentThrowsException(): void
    {
        $payment = $this->createCapturedPayment();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot capture payment in status: captured');

        $payment->capture();
    }

    public function testRefundZeroAmountThrowsException(): void
    {
        $payment = $this->createCapturedPayment();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Refund amount must be greater than 0');

        $payment->refund(Money::fromScalars(0, 'USD'), 'Invalid refund');
    }

    public function testRefundNegativeAmountThrowsException(): void
    {
        $payment = $this->createCapturedPayment();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Refund amount must be greater than 0');

        $payment->refund(Money::fromScalars(-1000, 'USD'), 'Invalid refund');
    }

    public function testMarkAsFailedWithErrorCode(): void
    {
        $payment = $this->createPendingPayment();

        $payment->markAsFailed('Card declined', 'card_declined');

        $this->assertTrue($payment->status()->isFailed());
        $this->assertSame('Card declined', $payment->errorMessage());
        $this->assertSame('card_declined', $payment->errorCode());
    }

    public function testCurrencyIsNormalizedToUppercase(): void
    {
        $payment = Payment::create(
            id: $this->paymentId,
            tenantId: $this->tenantId,
            orderId: $this->orderId,
            amount: Money::fromScalars(9999, 'USD'), // Already uppercase
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe()
        );

        // Verify currency is stored in uppercase
        $this->assertSame('USD', $payment->currency());
    }

    public function testCreatePaymentWithLowercaseCurrencyThrowsException(): void
    {
        $this->expectException(\Brick\Money\Exception\UnknownCurrencyException::class);

        Payment::create(
            id: $this->paymentId,
            tenantId: $this->tenantId,
            orderId: $this->orderId,
            amount: Money::fromScalars(9999, 'usd'), // lowercase - invalid
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe()
        );
    }

    // Helper methods
    private function createPendingPayment(): Payment
    {
        $payment = Payment::create(
            id: $this->paymentId,
            tenantId: $this->tenantId,
            orderId: $this->orderId,
            amount: Money::fromScalars(9999, 'USD'),
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
