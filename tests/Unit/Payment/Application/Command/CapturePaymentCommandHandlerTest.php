<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Application\Command;

use App\Payment\Application\Command\CapturePayment;
use App\Payment\Application\Command\CapturePaymentHandler;
use App\Payment\Domain\Model\Payment;
use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use App\Payment\Domain\Service\PaymentGatewayInterface;
use App\Payment\Domain\Service\PaymentIntentResult;
use App\Payment\Domain\ValueObject\PaymentGateway;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Payment\Domain\ValueObject\PaymentMethod;
use App\Payment\Domain\ValueObject\PaymentStatus;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class CapturePaymentCommandHandlerTest extends TestCase
{
    private PaymentGatewayInterface&MockObject $gateway;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->gateway = $this->createMock(PaymentGatewayInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testHandleCapturesAuthorizedPayment(): void
    {
        // Arrange
        $payment = $this->buildAuthorizedPayment('pi_abc123xyz', 9999, 'USD');
        $command = new CapturePayment(id: PaymentId::generate());

        $captureResult = PaymentIntentResult::success(
            gatewayPaymentIntentId: 'pi_abc123xyz',
            status: 'succeeded',
            amount: Money::fromScalars(9999, 'USD')
        );

        $this->gateway->expects($this->once())
            ->method('capturePaymentIntent')
            ->with('pi_abc123xyz', null)
            ->willReturn($captureResult);

        $savedPayments = [];
        $repository = $this->buildRepository(
            findReturn: $payment,
            saveCb: function (Payment $p) use (&$savedPayments): void { $savedPayments[] = $p; }
        );

        $handler = new CapturePaymentHandler($repository, $this->gateway, $this->logger);

        // Act
        ($handler)($command);

        // Assert
        $this->assertNotEmpty($savedPayments, 'Payment should be saved after capture');
        $this->assertTrue(end($savedPayments)->status()->isCaptured());
    }

    public function testHandleThrowsExceptionWhenPaymentNotFound(): void
    {
        // Arrange
        $command = new CapturePayment(id: PaymentId::generate());
        $saveCalled = false;

        $repository = $this->buildRepository(
            findReturn: null,
            saveCb: function () use (&$saveCalled): void { $saveCalled = true; }
        );

        $handler = new CapturePaymentHandler($repository, $this->gateway, $this->logger);

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Payment not found/');

        // Act
        ($handler)($command);

        $this->assertFalse($saveCalled);
    }

    public function testHandleThrowsExceptionWhenPaymentHasNoGatewayTransactionId(): void
    {
        // Arrange – pending payment without gateway transaction ID
        $payment = $this->buildPendingPayment();
        $command = new CapturePayment(id: PaymentId::generate());

        $this->gateway->expects($this->never())->method('capturePaymentIntent');

        $repository = $this->buildRepository(findReturn: $payment);
        $handler = new CapturePaymentHandler($repository, $this->gateway, $this->logger);

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/has not been authorized yet/');

        // Act
        ($handler)($command);
    }

    public function testHandleMarksPaymentAsFailedWhenGatewayFails(): void
    {
        // Arrange
        $payment = $this->buildAuthorizedPayment('pi_abc123xyz', 9999, 'USD');
        $command = new CapturePayment(id: PaymentId::generate());

        $failResult = PaymentIntentResult::failure(
            errorCode: 'capture_failed',
            errorMessage: 'Capture failed at gateway'
        );

        $this->gateway->expects($this->once())
            ->method('capturePaymentIntent')
            ->willReturn($failResult);

        $savedPayments = [];
        $repository = $this->buildRepository(
            findReturn: $payment,
            saveCb: function (Payment $p) use (&$savedPayments): void { $savedPayments[] = $p; }
        );

        $handler = new CapturePaymentHandler($repository, $this->gateway, $this->logger);

        // Act
        try {
            ($handler)($command);
            $this->fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertSame('Capture failed at gateway', $e->getMessage());
        }

        // Assert payment was saved as failed
        $this->assertNotEmpty($savedPayments);
        $this->assertTrue($savedPayments[0]->status()->isFailed());
    }

    public function testHandleMarksPaymentAsFailedOnUnexpectedException(): void
    {
        // Arrange
        $payment = $this->buildAuthorizedPayment('pi_abc123xyz', 9999, 'USD');
        $command = new CapturePayment(id: PaymentId::generate());

        $this->gateway->expects($this->once())
            ->method('capturePaymentIntent')
            ->willThrowException(new \InvalidArgumentException('Unexpected gateway error'));

        $savedPayments = [];
        $repository = $this->buildRepository(
            findReturn: $payment,
            saveCb: function (Payment $p) use (&$savedPayments): void { $savedPayments[] = $p; }
        );

        $handler = new CapturePaymentHandler($repository, $this->gateway, $this->logger);

        // Act
        try {
            ($handler)($command);
            $this->fail('Expected exception was not thrown');
        } catch (\Throwable $e) {
            $this->assertInstanceOf(\InvalidArgumentException::class, $e);
        }

        // Assert payment was marked failed (non-RuntimeException path)
        $this->assertNotEmpty($savedPayments);
        $this->assertTrue($savedPayments[0]->status()->isFailed());
    }

    public function testHandleLogsSuccessAfterCapture(): void
    {
        // Arrange
        $payment = $this->buildAuthorizedPayment('pi_abc123xyz', 5000, 'EUR');
        $command = new CapturePayment(id: PaymentId::generate());

        $captureResult = PaymentIntentResult::success(
            gatewayPaymentIntentId: 'pi_abc123xyz',
            status: 'succeeded',
            amount: Money::fromScalars(5000, 'EUR')
        );

        $this->gateway->method('capturePaymentIntent')->willReturn($captureResult);

        $repository = $this->buildRepository(findReturn: $payment);

        $this->logger->expects($this->atLeastOnce())
            ->method('info')
            ->with(
                $this->logicalOr(
                    $this->equalTo('Capturing payment via gateway'),
                    $this->equalTo('Payment capture successful')
                ),
                $this->isType('array')
            );

        $handler = new CapturePaymentHandler($repository, $this->gateway, $this->logger);

        // Act
        ($handler)($command);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function buildAuthorizedPayment(
        string $transactionId,
        int $amountInCents,
        string $currency,
    ): Payment {
        return Payment::reconstituteFromPersistence(
            id: PaymentId::generate(),
            tenantId: TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            orderId: '01JCEX'.bin2hex(random_bytes(10)),
            amountInCents: $amountInCents,
            currency: $currency,
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe(),
            status: PaymentStatus::authorized(),
            gatewayTransactionId: $transactionId,
            errorMessage: null,
            refundedAmountInCents: 0,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable()
        );
    }

    private function buildPendingPayment(): Payment
    {
        return Payment::create(
            id: PaymentId::generate(),
            tenantId: TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            orderId: '01JCEX'.bin2hex(random_bytes(10)),
            amountInCents: 9999,
            currency: 'USD',
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe()
        );
    }

    /**
     * Build a repository stub that accepts mixed types for findById, avoiding the
     * type mismatch between Domain\Model\PaymentId (used by CapturePayment command)
     * and Domain\ValueObject\PaymentId (declared in PaymentRepositoryInterface).
     */
    private function buildRepository(
        ?Payment $findReturn = null,
        ?callable $saveCb = null,
    ): PaymentRepositoryInterface {
        return new class($findReturn, $saveCb) implements PaymentRepositoryInterface {
            public function __construct(
                private readonly ?Payment $returnPayment,
                private readonly mixed $saveCb,
            ) {
            }

            public function save(Payment $payment): void
            {
                if (null !== $this->saveCb) {
                    ($this->saveCb)($payment);
                }
            }

            public function findById(mixed $id): ?Payment
            {
                return $this->returnPayment;
            }

            public function findByOrderId(mixed $orderId): ?Payment
            {
                return null;
            }

            public function findByGatewayPaymentId(string $gatewayPaymentId): ?Payment
            {
                return null;
            }

            public function findByIdempotencyKey(
                string $idempotencyKey,
                TenantId $tenantId,
            ): ?Payment {
                return null;
            }

            public function findAll(TenantId $tenantId): array
            {
                return [];
            }

            public function findAllByOrderId(string $orderId): array
            {
                return [];
            }

            public function findPaymentsForRetry(\DateTimeImmutable $before): array
            {
                return [];
            }
        };
    }
}
