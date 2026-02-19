<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Application\Service;

use App\Payment\Application\Service\PaymentRetryService;
use App\Payment\Domain\Model\Payment;
use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use App\Payment\Domain\Service\PaymentGatewayFactoryInterface;
use App\Payment\Domain\Service\PaymentGatewayInterface;
use App\Payment\Domain\Service\PaymentIntentResult;
use App\Payment\Domain\ValueObject\PaymentGateway;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Payment\Domain\ValueObject\PaymentMethod;
use App\Payment\Domain\ValueObject\PaymentStatus;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for PaymentRetryService.
 *
 * Tests retry orchestration logic including real gateway calls:
 * - scheduleRetry: Scheduling retry attempts with exponential backoff
 * - shouldRetry: Determining retry eligibility
 * - processRetry: Processing retry attempts via gateway
 * - getPaymentsDueForRetry: Retrieving payments due for retry
 */
final class PaymentRetryServiceTest extends TestCase
{
    private PaymentRetryService $service;
    private PaymentRepositoryInterface&MockObject $repository;
    private PaymentGatewayFactoryInterface&MockObject $gatewayFactory;
    private PaymentGatewayInterface&MockObject $gateway;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(PaymentRepositoryInterface::class);
        $this->gatewayFactory = $this->createMock(PaymentGatewayFactoryInterface::class);
        $this->gateway = $this->createMock(PaymentGatewayInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new PaymentRetryService(
            $this->repository,
            $this->gatewayFactory,
            $this->logger
        );
    }

    // ========================================================================
    // scheduleRetry Tests
    // ========================================================================

    #[Test]
    public function testScheduleRetryReturnsNextRetryTime(): void
    {
        $payment = $this->createFailedPayment(errorCode: 'card_declined', retryCount: 0);

        $this->repository->expects($this->once())
            ->method('save')
            ->with($payment);

        $nextRetryAt = $this->service->scheduleRetry($payment);

        $this->assertNotNull($nextRetryAt);
        $this->assertInstanceOf(\DateTimeImmutable::class, $nextRetryAt);
        // Should be approximately 1 hour from now
        $diff = $nextRetryAt->getTimestamp() - (new \DateTimeImmutable())->getTimestamp();
        $this->assertGreaterThan(3500, $diff);
        $this->assertLessThan(3700, $diff);
    }

    #[Test]
    public function testScheduleRetryReturnsNullWhenNotEligible(): void
    {
        $payment = $this->createExhaustedPayment();

        $this->repository->expects($this->never())->method('save');

        $nextRetryAt = $this->service->scheduleRetry($payment);

        $this->assertNull($nextRetryAt);
    }

    #[Test]
    public function testScheduleRetrySavesPayment(): void
    {
        $payment = $this->createFailedPayment(errorCode: 'card_declined', retryCount: 0);

        $this->repository->expects($this->once())
            ->method('save')
            ->with($this->identicalTo($payment));

        $this->service->scheduleRetry($payment);
    }

    #[Test]
    public function testScheduleRetryLogsSuccess(): void
    {
        $payment = $this->createFailedPayment(errorCode: 'card_declined', retryCount: 1);

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                'Payment retry scheduled',
                $this->callback(function (array $context) use ($payment) {
                    return $context['payment_id'] === $payment->id()->toString()
                        && 1 === $context['retry_count']
                        && isset($context['next_retry_at']);
                })
            );

        $this->service->scheduleRetry($payment);
    }

    #[Test]
    public function testScheduleRetryReturnsNullOnException(): void
    {
        // retryCount=3 means scheduleRetry on aggregate will throw (max reached)
        $payment = $this->createFailedPayment(errorCode: 'card_declined', retryCount: 3);

        $nextRetryAt = $this->service->scheduleRetry($payment);

        $this->assertNull($nextRetryAt);
    }

    // ========================================================================
    // shouldRetry Tests
    // ========================================================================

    #[Test]
    public function testShouldRetryReturnsFalseForNonFailedPayment(): void
    {
        $payment = Payment::create(
            id: PaymentId::generate(),
            tenantId: TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            orderId: 'order-123',
            amountInCents: 10000,
            currency: 'USD',
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe()
        );
        $payment->authorize('txn_123');

        $result = $this->service->shouldRetry($payment);

        $this->assertFalse($result);
    }

    #[Test]
    public function testShouldRetryReturnsFalseForNullErrorCode(): void
    {
        $payment = $this->createFailedPayment(errorCode: null, retryCount: 0);
        $payment->markAsFailed('Payment failed');

        $result = $this->service->shouldRetry($payment);

        $this->assertFalse($result);
    }

    #[Test]
    public function testShouldRetryReturnsFalseForNonRetryableError(): void
    {
        $payment = $this->createNonRetryablePayment();

        $result = $this->service->shouldRetry($payment);

        $this->assertFalse($result);
    }

    #[Test]
    public function testShouldRetryReturnsFalseWhenMaxAttemptsReached(): void
    {
        $payment = $this->createExhaustedPayment();

        $result = $this->service->shouldRetry($payment);

        $this->assertFalse($result);
    }

    #[Test]
    public function testShouldRetryReturnsTrueForRetryableError(): void
    {
        $payment = $this->createFailedPayment(errorCode: 'card_declined', retryCount: 0);

        $result = $this->service->shouldRetry($payment);

        $this->assertTrue($result);
    }

    // ========================================================================
    // processRetry Tests — Gateway Integration
    // ========================================================================

    #[Test]
    public function testProcessRetrySuccessAuthorizesPayment(): void
    {
        $payment = $this->createDueForRetryPayment();
        $gatewayTxnId = 'pi_retry_success_123';

        $this->expectGatewayFactoryResolves();
        $this->gateway->expects($this->once())
            ->method('createPaymentIntent')
            ->willReturn(PaymentIntentResult::success(
                gatewayPaymentIntentId: $gatewayTxnId,
                status: 'requires_capture',
                amount: Money::fromScalars(10000, 'USD'),
                clientSecret: 'secret_retry'
            ));

        $this->repository->expects($this->once())->method('save');

        $result = $this->service->processRetry($payment);

        $this->assertTrue($result);
        // Payment should be authorized with new gateway transaction ID
        $this->assertTrue($payment->status()->isAuthorized());
        $this->assertSame($gatewayTxnId, $payment->gatewayTransactionId());
        $this->assertSame(2, $payment->retryCount()); // Was 1, incremented to 2
    }

    #[Test]
    public function testProcessRetrySuccessEmitsRetryAttemptedAndAuthorizedEvents(): void
    {
        $payment = $this->createDueForRetryPayment();

        $this->expectGatewayFactoryResolves();
        $this->gateway->expects($this->once())
            ->method('createPaymentIntent')
            ->willReturn(PaymentIntentResult::success(
                gatewayPaymentIntentId: 'pi_event_test',
                status: 'requires_capture',
                amount: Money::fromScalars(10000, 'USD')
            ));

        $this->repository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Payment $p) {
                $events = $p->popEvents();
                // Should emit PaymentRetryAttempted (wasSuccessful=true) + PaymentAuthorized
                $this->assertCount(2, $events);
                $this->assertInstanceOf(\App\Payment\Domain\Event\PaymentRetryAttempted::class, $events[0]);
                $this->assertTrue($events[0]->wasSuccessful);
                $this->assertInstanceOf(\App\Payment\Domain\Event\PaymentAuthorized::class, $events[1]);

                return true;
            }));

        $this->service->processRetry($payment);
    }

    #[Test]
    public function testProcessRetryFailureSchedulesNextRetry(): void
    {
        $payment = $this->createFailedPayment(
            errorCode: 'card_declined',
            retryCount: 0,
            nextRetryAt: new \DateTimeImmutable('-1 hour')
        );

        $this->expectGatewayFactoryResolves();
        $this->gateway->expects($this->once())
            ->method('createPaymentIntent')
            ->willReturn(PaymentIntentResult::failure(
                errorCode: 'card_declined',
                errorMessage: 'Your card was declined'
            ));

        $this->repository->expects($this->once())->method('save');

        $result = $this->service->processRetry($payment);

        $this->assertTrue($result);
        $this->assertTrue($payment->status()->isFailed());
        $this->assertSame(1, $payment->retryCount());
        // Next retry should be scheduled in the future
        $this->assertNotNull($payment->nextRetryAt());
        $this->assertGreaterThan(new \DateTimeImmutable(), $payment->nextRetryAt());
    }

    #[Test]
    public function testProcessRetryFailureEmitsRetryAttemptedAndScheduledEvents(): void
    {
        $payment = $this->createFailedPayment(
            errorCode: 'card_declined',
            retryCount: 0,
            nextRetryAt: new \DateTimeImmutable('-1 hour')
        );

        $this->expectGatewayFactoryResolves();
        $this->gateway->expects($this->once())
            ->method('createPaymentIntent')
            ->willReturn(PaymentIntentResult::failure(
                errorCode: 'insufficient_funds',
                errorMessage: 'Insufficient funds'
            ));

        $this->repository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Payment $p) {
                $events = $p->popEvents();
                // PaymentRetryAttempted (failed) + PaymentRetryScheduled
                $this->assertCount(2, $events);
                $this->assertInstanceOf(\App\Payment\Domain\Event\PaymentRetryAttempted::class, $events[0]);
                $this->assertFalse($events[0]->wasSuccessful);
                $this->assertSame('insufficient_funds', $events[0]->errorCode);
                $this->assertInstanceOf(\App\Payment\Domain\Event\PaymentRetryScheduled::class, $events[1]);

                return true;
            }));

        $this->service->processRetry($payment);
    }

    #[Test]
    public function testProcessRetryMaxRetriesExhausted(): void
    {
        // retryCount=2, this will be attempt 3 (the max)
        $payment = $this->createFailedPayment(
            errorCode: 'card_declined',
            retryCount: 2,
            nextRetryAt: new \DateTimeImmutable('-1 hour')
        );

        $this->expectGatewayFactoryResolves();
        $this->gateway->expects($this->once())
            ->method('createPaymentIntent')
            ->willReturn(PaymentIntentResult::failure(
                errorCode: 'processing_error',
                errorMessage: 'Processing error'
            ));

        $this->logger->expects($this->atLeastOnce())
            ->method('warning')
            ->with(
                $this->logicalOr(
                    $this->equalTo('Payment retry failed at gateway'),
                    $this->equalTo('Payment retry exhausted')
                ),
                $this->isType('array')
            );

        $this->repository->expects($this->once())->method('save');

        $result = $this->service->processRetry($payment);

        $this->assertTrue($result);
        $this->assertSame(3, $payment->retryCount());
        // nextRetryAt should be null — no more retries
        $this->assertNull($payment->nextRetryAt());
    }

    #[Test]
    public function testProcessRetryMaxRetriesEmitsExhaustedEvent(): void
    {
        $payment = $this->createFailedPayment(
            errorCode: 'card_declined',
            retryCount: 2,
            nextRetryAt: new \DateTimeImmutable('-1 hour')
        );

        $this->expectGatewayFactoryResolves();
        $this->gateway->expects($this->once())
            ->method('createPaymentIntent')
            ->willReturn(PaymentIntentResult::failure(
                errorCode: 'card_declined',
                errorMessage: 'Card declined'
            ));

        $this->repository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Payment $p) {
                $events = $p->popEvents();
                // PaymentRetryAttempted (failed) + PaymentRetryExhausted
                $this->assertCount(2, $events);
                $this->assertInstanceOf(\App\Payment\Domain\Event\PaymentRetryAttempted::class, $events[0]);
                $this->assertFalse($events[0]->wasSuccessful);
                $this->assertInstanceOf(\App\Payment\Domain\Event\PaymentRetryExhausted::class, $events[1]);

                return true;
            }));

        $this->service->processRetry($payment);
    }

    #[Test]
    public function testProcessRetryGatewaySpecificStripeDecline(): void
    {
        $payment = $this->createDueForRetryPayment(gateway: PaymentGateway::stripe());

        $this->expectGatewayFactoryResolves();
        $this->gateway->expects($this->once())
            ->method('createPaymentIntent')
            ->willReturn(PaymentIntentResult::failure(
                errorCode: 'insufficient_funds',
                errorMessage: 'Your card has insufficient funds'
            ));

        $this->repository->expects($this->once())->method('save');

        $result = $this->service->processRetry($payment);

        $this->assertTrue($result);
        $this->assertSame('insufficient_funds', $payment->errorCode());
        $this->assertSame('Your card has insufficient funds', $payment->errorMessage());
    }

    #[Test]
    public function testProcessRetryGatewaySpecificPayPalTimeout(): void
    {
        $payment = $this->createDueForRetryPayment(gateway: PaymentGateway::paypal());

        $this->gatewayFactory->expects($this->once())
            ->method('create')
            ->willReturn($this->gateway);

        $this->gateway->expects($this->once())
            ->method('createPaymentIntent')
            ->willReturn(PaymentIntentResult::failure(
                errorCode: 'gateway_timeout',
                errorMessage: 'PayPal gateway timeout'
            ));

        $this->repository->expects($this->once())->method('save');

        $result = $this->service->processRetry($payment);

        $this->assertTrue($result);
        $this->assertSame('gateway_timeout', $payment->errorCode());
    }

    #[Test]
    public function testProcessRetryOnNonFailedPaymentIsNoOp(): void
    {
        // Payment is authorized (not failed) — retry should be a no-op
        $payment = Payment::create(
            id: PaymentId::generate(),
            tenantId: TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            orderId: 'order-noop',
            amountInCents: 10000,
            currency: 'USD',
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe()
        );
        $payment->authorize('pi_already_authorized');

        // Gateway should NOT be called
        $this->gatewayFactory->expects($this->never())->method('create');
        $this->repository->expects($this->never())->method('save');

        $result = $this->service->processRetry($payment);

        $this->assertFalse($result);
    }

    #[Test]
    public function testProcessRetryNotDueYetReturnsFalse(): void
    {
        $payment = $this->createFailedPayment(
            errorCode: 'card_declined',
            retryCount: 1,
            nextRetryAt: new \DateTimeImmutable('+1 hour')
        );

        $this->gatewayFactory->expects($this->never())->method('create');
        $this->repository->expects($this->never())->method('save');

        $result = $this->service->processRetry($payment);

        $this->assertFalse($result);
    }

    #[Test]
    public function testProcessRetryUsesRetrySpecificIdempotencyKey(): void
    {
        $payment = $this->createDueForRetryPayment();
        $paymentId = $payment->id()->toString();

        $this->expectGatewayFactoryResolves();
        $this->gateway->expects($this->once())
            ->method('createPaymentIntent')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->equalTo('USD'),
                $this->equalTo("retry_{$paymentId}_2"), // retryCount=1, attempt=2
                $this->isNull(),
                $this->callback(function (array $metadata) use ($paymentId) {
                    return $metadata['retry_attempt'] === 2
                        && $metadata['original_payment_id'] === $paymentId;
                })
            )
            ->willReturn(PaymentIntentResult::success(
                gatewayPaymentIntentId: 'pi_idempotent',
                status: 'requires_capture',
                amount: Money::fromScalars(10000, 'USD')
            ));

        $this->service->processRetry($payment);
    }

    #[Test]
    public function testProcessRetryExponentialBackoffTiming(): void
    {
        // After recordRetryAttempt increments retryCount, scheduleRetry uses the NEW count.
        // retryCount 0→1 schedules with calculateNextRetryTime(1) → attempt 2 delay → 14400s (4h)
        // retryCount 1→2 schedules with calculateNextRetryTime(2) → attempt 3 delay → 86400s (24h)

        // First retry (retryCount=0→1): next retry should be ~4h from now
        $payment1 = $this->createFailedPayment(
            errorCode: 'card_declined',
            retryCount: 0,
            nextRetryAt: new \DateTimeImmutable('-1 minute')
        );

        $this->gatewayFactory->method('create')->willReturn($this->gateway);
        $this->gateway->method('createPaymentIntent')
            ->willReturn(PaymentIntentResult::failure(
                errorCode: 'card_declined',
                errorMessage: 'Declined'
            ));

        $this->service->processRetry($payment1);

        $nextRetry1 = $payment1->nextRetryAt();
        $this->assertNotNull($nextRetry1);
        $diff1 = $nextRetry1->getTimestamp() - (new \DateTimeImmutable())->getTimestamp();
        // RetryPolicy: delay for next attempt (2nd) = 14400s (4h), ±100s tolerance
        $this->assertGreaterThan(14300, $diff1);
        $this->assertLessThan(14500, $diff1);

        // Second retry (retryCount=1→2): next retry should be ~24h from now
        $payment2 = $this->createFailedPayment(
            errorCode: 'card_declined',
            retryCount: 1,
            nextRetryAt: new \DateTimeImmutable('-1 minute')
        );

        $this->service->processRetry($payment2);

        $nextRetry2 = $payment2->nextRetryAt();
        $this->assertNotNull($nextRetry2);
        $diff2 = $nextRetry2->getTimestamp() - (new \DateTimeImmutable())->getTimestamp();
        // RetryPolicy: delay for next attempt (3rd) = 86400s (24h), ±100s tolerance
        $this->assertGreaterThan(86300, $diff2);
        $this->assertLessThan(86500, $diff2);
    }

    #[Test]
    public function testProcessRetryReturnsFalseOnGatewayException(): void
    {
        $payment = $this->createDueForRetryPayment();

        $this->expectGatewayFactoryResolves();
        $this->gateway->expects($this->once())
            ->method('createPaymentIntent')
            ->willThrowException(new \RuntimeException('Gateway connection error'));

        $this->logger->expects($this->atLeastOnce())->method('error');

        $result = $this->service->processRetry($payment);

        $this->assertFalse($result);
    }

    #[Test]
    public function testProcessRetryReturnsFalseOnRepositoryException(): void
    {
        $payment = $this->createDueForRetryPayment();

        $this->expectGatewayFactoryResolves();
        $this->gateway->expects($this->once())
            ->method('createPaymentIntent')
            ->willReturn(PaymentIntentResult::success(
                gatewayPaymentIntentId: 'pi_db_fail',
                status: 'requires_capture',
                amount: Money::fromScalars(10000, 'USD')
            ));

        $this->repository->expects($this->once())
            ->method('save')
            ->willThrowException(new \RuntimeException('Database error'));

        $result = $this->service->processRetry($payment);

        $this->assertFalse($result);
    }

    // ========================================================================
    // getPaymentsDueForRetry Tests
    // ========================================================================

    #[Test]
    public function testGetPaymentsDueForRetryDelegatesToRepository(): void
    {
        $now = new \DateTimeImmutable('2025-01-15 12:00:00');
        $expectedPayments = [
            $this->createDueForRetryPayment(),
            $this->createDueForRetryPayment(),
        ];

        $this->repository->expects($this->once())
            ->method('findPaymentsForRetry')
            ->with($this->equalTo($now))
            ->willReturn($expectedPayments);

        $result = $this->service->getPaymentsDueForRetry($now);

        $this->assertSame($expectedPayments, $result);
        $this->assertCount(2, $result);
    }

    #[Test]
    public function testGetPaymentsDueForRetryUsesCurrentTimeWhenNullProvided(): void
    {
        $this->repository->expects($this->once())
            ->method('findPaymentsForRetry')
            ->with($this->isInstanceOf(\DateTimeImmutable::class))
            ->willReturn([]);

        $result = $this->service->getPaymentsDueForRetry();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // ========================================================================
    // Helper Methods
    // ========================================================================

    private function createFailedPayment(
        ?string $errorCode = null,
        int $retryCount = 0,
        ?\DateTimeImmutable $nextRetryAt = null,
        PaymentGateway $gateway = null,
    ): Payment {
        $payment = Payment::create(
            id: PaymentId::generate(),
            tenantId: TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            orderId: 'order-123',
            amountInCents: 10000,
            currency: 'USD',
            method: PaymentMethod::card(),
            gateway: $gateway ?? PaymentGateway::stripe()
        );

        if (null !== $errorCode) {
            $payment->markAsFailed('Payment failed', $errorCode);
        }

        if ($retryCount > 0 || null !== $nextRetryAt) {
            $payment = Payment::reconstituteFromPersistence(
                id: $payment->id(),
                tenantId: $payment->tenantId(),
                orderId: $payment->orderId(),
                amountInCents: $payment->amountInCents(),
                currency: $payment->currency(),
                method: $payment->method(),
                gateway: $payment->gateway(),
                status: null !== $errorCode ? PaymentStatus::failed() : $payment->status(),
                gatewayTransactionId: null,
                errorMessage: null !== $errorCode ? 'Payment failed' : null,
                refundedAmountInCents: 0,
                createdAt: new \DateTimeImmutable('-1 hour'),
                updatedAt: new \DateTimeImmutable(),
                errorCode: $errorCode,
                retryCount: $retryCount,
                nextRetryAt: $nextRetryAt
            );
        }

        return $payment;
    }

    private function createNonRetryablePayment(): Payment
    {
        return $this->createFailedPayment(errorCode: 'expired_card', retryCount: 0);
    }

    private function createExhaustedPayment(): Payment
    {
        return $this->createFailedPayment(errorCode: 'card_declined', retryCount: 3);
    }

    private function createDueForRetryPayment(PaymentGateway $gateway = null): Payment
    {
        return $this->createFailedPayment(
            errorCode: 'card_declined',
            retryCount: 1,
            nextRetryAt: new \DateTimeImmutable('-1 hour'),
            gateway: $gateway
        );
    }

    private function expectGatewayFactoryResolves(): void
    {
        $this->gatewayFactory->expects($this->once())
            ->method('create')
            ->willReturn($this->gateway);
    }
}
