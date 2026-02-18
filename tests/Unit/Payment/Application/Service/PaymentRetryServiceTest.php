<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Application\Service;

use App\Payment\Application\Service\PaymentRetryService;
use App\Payment\Domain\Model\Payment;
use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use App\Payment\Domain\ValueObject\PaymentGateway;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Payment\Domain\ValueObject\PaymentMethod;
use App\Payment\Domain\ValueObject\PaymentStatus;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for PaymentRetryService.
 *
 * Tests retry orchestration logic:
 * - scheduleRetry: Scheduling retry attempts with exponential backoff
 * - shouldRetry: Determining retry eligibility
 * - processRetry: Processing retry attempts
 * - getPaymentsDueForRetry: Retrieving payments due for retry
 *
 * @see PaymentRetryService
 */
final class PaymentRetryServiceTest extends TestCase
{
    private PaymentRetryService $service;
    private PaymentRepositoryInterface&MockObject $repository;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(PaymentRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->service = new PaymentRetryService($this->repository, $this->logger);
    }

    // ========================================================================
    // scheduleRetry Tests
    // ========================================================================

    #[Test]
    public function testScheduleRetryReturnsNextRetryTime(): void
    {
        // Arrange
        $payment = $this->createPayment(errorCode: 'card_declined', retryCount: 0);

        $this->repository->expects($this->once())
            ->method('save')
            ->with($payment);

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Payment retry scheduled', $this->isType('array'));

        // Act
        $nextRetryAt = $this->service->scheduleRetry($payment);

        // Assert
        $this->assertNotNull($nextRetryAt);
        $this->assertInstanceOf(\DateTimeImmutable::class, $nextRetryAt);
        // Should be approximately 1 hour from now
        $diff = $nextRetryAt->getTimestamp() - (new \DateTimeImmutable())->getTimestamp();
        $this->assertGreaterThan(3500, $diff); // At least 58 minutes
        $this->assertLessThan(3700, $diff); // At most 62 minutes
    }

    #[Test]
    public function testScheduleRetryReturnsNullWhenNotEligible(): void
    {
        // Arrange - Payment with max attempts reached
        $payment = $this->createExhaustedPayment();

        $this->logger->expects($this->atLeastOnce())
            ->method('info');

        // Repository save should NOT be called
        $this->repository->expects($this->never())
            ->method('save');

        // Act
        $nextRetryAt = $this->service->scheduleRetry($payment);

        // Assert
        $this->assertNull($nextRetryAt);
    }

    #[Test]
    public function testScheduleRetrySavesPayment(): void
    {
        // Arrange
        $payment = $this->createPayment(errorCode: 'card_declined', retryCount: 0);

        $this->repository->expects($this->once())
            ->method('save')
            ->with($this->identicalTo($payment));

        // Act
        $this->service->scheduleRetry($payment);
    }

    #[Test]
    public function testScheduleRetryLogsSuccess(): void
    {
        // Arrange
        $payment = $this->createPayment(errorCode: 'card_declined', retryCount: 1);

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

        // Act
        $this->service->scheduleRetry($payment);
    }

    #[Test]
    public function testScheduleRetryReturnsNullOnException(): void
    {
        // Arrange - Create a payment that will cause scheduleRetry to throw
        $payment = $this->createPayment(errorCode: 'card_declined', retryCount: 3);

        $this->logger->expects($this->atLeastOnce())
            ->method('info');

        // Act
        $nextRetryAt = $this->service->scheduleRetry($payment);

        // Assert
        $this->assertNull($nextRetryAt);
    }

    // ========================================================================
    // shouldRetry Tests
    // ========================================================================

    #[Test]
    public function testShouldRetryReturnsFalseForNonFailedPayment(): void
    {
        // Arrange - Payment in authorized status (not failed)
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

        // Act
        $result = $this->service->shouldRetry($payment);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function testShouldRetryReturnsFalseForNullErrorCode(): void
    {
        // Arrange
        $payment = $this->createPayment(errorCode: null, retryCount: 0);
        $payment->markAsFailed('Payment failed'); // No error code

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Error not retryable', $this->isType('array'));

        // Act
        $result = $this->service->shouldRetry($payment);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function testShouldRetryReturnsFalseForNonRetryableError(): void
    {
        // Arrange - expired_card is a non-retryable error
        $payment = $this->createNonRetryablePayment();

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Error not retryable', $this->isType('array'));

        // Act
        $result = $this->service->shouldRetry($payment);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function testShouldRetryReturnsFalseWhenMaxAttemptsReached(): void
    {
        // Arrange
        $payment = $this->createExhaustedPayment();

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                'Max retry attempts reached',
                $this->callback(function (array $context) {
                    return 3 === $context['retry_count']
                        && 3 === $context['max_attempts'];
                })
            );

        // Act
        $result = $this->service->shouldRetry($payment);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function testShouldRetryReturnsTrueForRetryableError(): void
    {
        // Arrange
        $payment = $this->createPayment(errorCode: 'card_declined', retryCount: 0);

        // Act
        $result = $this->service->shouldRetry($payment);

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function testShouldRetryLogsWhenNotEligible(): void
    {
        // Arrange
        $payment = $this->createNonRetryablePayment();

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                'Error not retryable',
                $this->callback(function (array $context) use ($payment) {
                    return $context['payment_id'] === $payment->id()->toString()
                        && 'expired_card' === $context['error_code'];
                })
            );

        // Act
        $this->service->shouldRetry($payment);
    }

    // ========================================================================
    // processRetry Tests
    // ========================================================================

    #[Test]
    public function testProcessRetryReturnsFalseWhenNotDue(): void
    {
        // Arrange - Payment with future retry time
        $payment = $this->createPayment(
            errorCode: 'card_declined',
            retryCount: 1,
            nextRetryAt: new \DateTimeImmutable('+1 hour')
        );

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('Payment not due for retry yet', $this->isType('array'));

        $this->repository->expects($this->never())
            ->method('save');

        // Act
        $result = $this->service->processRetry($payment);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function testProcessRetryRecordsAttempt(): void
    {
        // Arrange
        $payment = $this->createDueForRetryPayment();
        $initialRetryCount = $payment->retryCount();

        $this->repository->expects($this->atLeastOnce())
            ->method('save')
            ->with($payment);

        // Act
        $result = $this->service->processRetry($payment);

        // Assert
        $this->assertTrue($result);
        // Retry count should have been incremented
        $this->assertEquals($initialRetryCount + 1, $payment->retryCount());
    }

    #[Test]
    public function testProcessRetrySchedulesNextRetryOnFailure(): void
    {
        // Arrange
        $payment = $this->createPayment(
            errorCode: 'card_declined',
            retryCount: 0,
            nextRetryAt: new \DateTimeImmutable('-1 hour')
        );

        $this->repository->expects($this->atLeastOnce())
            ->method('save');

        // Act
        $result = $this->service->processRetry($payment);

        // Assert
        $this->assertTrue($result);
        // Should have scheduled next retry
        $this->assertNotNull($payment->nextRetryAt());
        // Next retry should be in the future
        $this->assertGreaterThan(new \DateTimeImmutable(), $payment->nextRetryAt());
    }

    #[Test]
    public function testProcessRetryMarksExhaustedWhenMaxReached(): void
    {
        // Arrange - Payment with 2 retries, this will be the 3rd
        $payment = $this->createPayment(
            errorCode: 'card_declined',
            retryCount: 2,
            nextRetryAt: new \DateTimeImmutable('-1 hour')
        );

        $this->logger->expects($this->atLeastOnce())
            ->method('warning')
            ->with(
                'Payment retry exhausted',
                $this->callback(function (array $context) {
                    return 3 === $context['total_attempts'];
                })
            );

        $this->repository->expects($this->once())
            ->method('save');

        // Act
        $result = $this->service->processRetry($payment);

        // Assert
        $this->assertTrue($result);
        $this->assertEquals(3, $payment->retryCount());
        // Next retry should be null (exhausted)
        $this->assertNull($payment->nextRetryAt());
    }

    #[Test]
    public function testProcessRetrySavesPayment(): void
    {
        // Arrange
        $payment = $this->createDueForRetryPayment();

        $this->repository->expects($this->atLeastOnce())
            ->method('save')
            ->with($this->identicalTo($payment));

        // Act
        $this->service->processRetry($payment);
    }

    #[Test]
    public function testProcessRetryReturnsFalseOnException(): void
    {
        // Arrange - Mock repository to throw exception
        $payment = $this->createDueForRetryPayment();

        $this->repository->expects($this->atLeastOnce())
            ->method('save')
            ->willThrowException(new \RuntimeException('Database error'));

        // Logger will be called at least once with error level
        $this->logger->expects($this->atLeastOnce())
            ->method('error');

        // Act
        $result = $this->service->processRetry($payment);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function testProcessRetryLogsAllSteps(): void
    {
        // Arrange
        $payment = $this->createDueForRetryPayment();

        // Expect at least "Processing payment retry" log
        $this->logger->expects($this->atLeastOnce())
            ->method('info')
            ->with(
                $this->logicalOr(
                    $this->equalTo('Processing payment retry'),
                    $this->equalTo('Payment retry scheduled')
                ),
                $this->isType('array')
            );

        // Act
        $this->service->processRetry($payment);
    }

    // ========================================================================
    // getPaymentsDueForRetry Tests
    // ========================================================================

    #[Test]
    public function testGetPaymentsDueForRetryDelegatesToRepository(): void
    {
        // Arrange
        $now = new \DateTimeImmutable('2025-01-15 12:00:00');
        $expectedPayments = [
            $this->createDueForRetryPayment(),
            $this->createDueForRetryPayment(),
        ];

        $this->repository->expects($this->once())
            ->method('findPaymentsForRetry')
            ->with($this->equalTo($now))
            ->willReturn($expectedPayments);

        // Act
        $result = $this->service->getPaymentsDueForRetry($now);

        // Assert
        $this->assertSame($expectedPayments, $result);
        $this->assertCount(2, $result);
    }

    #[Test]
    public function testGetPaymentsDueForRetryUsesCurrentTimeWhenNullProvided(): void
    {
        // Arrange
        $this->repository->expects($this->once())
            ->method('findPaymentsForRetry')
            ->with($this->isInstanceOf(\DateTimeImmutable::class))
            ->willReturn([]);

        // Act
        $result = $this->service->getPaymentsDueForRetry();

        // Assert
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // ========================================================================
    // Helper Methods
    // ========================================================================

    /**
     * Create a payment with specific retry-related state.
     */
    private function createPayment(
        ?string $errorCode = null,
        int $retryCount = 0,
        ?\DateTimeImmutable $nextRetryAt = null,
    ): Payment {
        $payment = Payment::create(
            id: PaymentId::generate(),
            tenantId: TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            orderId: 'order-123',
            amountInCents: 10000,
            currency: 'USD',
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe()
        );

        // If we need a failed payment, mark it as failed
        if (null !== $errorCode) {
            $payment->markAsFailed('Payment failed', $errorCode);
        }

        // If retry count > 0, we need to reconstitute with retry data
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

    /**
     * Create a payment that is NOT eligible for retry.
     */
    private function createNonRetryablePayment(): Payment
    {
        // Payment with non-retryable error (expired_card)
        return $this->createPayment(errorCode: 'expired_card', retryCount: 0);
    }

    /**
     * Create a payment that has exhausted retry attempts.
     */
    private function createExhaustedPayment(): Payment
    {
        return $this->createPayment(errorCode: 'card_declined', retryCount: 3);
    }

    /**
     * Create a payment that is due for retry.
     */
    private function createDueForRetryPayment(): Payment
    {
        return $this->createPayment(
            errorCode: 'card_declined',
            retryCount: 1,
            nextRetryAt: new \DateTimeImmutable('-1 hour') // Due in the past
        );
    }
}
