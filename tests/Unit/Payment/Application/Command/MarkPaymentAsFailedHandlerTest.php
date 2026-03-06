<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Application\Command;

use App\Payment\Application\Command\MarkPaymentAsFailed;
use App\Payment\Application\Command\MarkPaymentAsFailedHandler;
use App\Payment\Domain\Model\Payment;
use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use App\Payment\Domain\ValueObject\PaymentGateway;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Payment\Domain\ValueObject\PaymentMethod;
use App\Payment\Domain\ValueObject\PaymentStatus;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(MarkPaymentAsFailedHandler::class)]
final class MarkPaymentAsFailedHandlerTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function buildPendingPayment(): Payment
    {
        return Payment::create(
            id: PaymentId::generate(),
            tenantId: TenantId::fromString(self::TENANT_ID),
            orderId: 'order-'.bin2hex(random_bytes(8)),
            amount: Money::fromScalars(9999, 'USD'),
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe(),
        );
    }

    private function buildAlreadyFailedPayment(): Payment
    {
        $payment = $this->buildPendingPayment();
        $payment->markAsFailed('Pre-existing failure', 'card_declined');

        return $payment;
    }

    private function buildRepository(
        ?Payment $findReturn,
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

            public function findByOrderId(string $orderId): ?Payment
            {
                return null;
            }

            public function findByGatewayPaymentId(string $gatewayPaymentId): ?Payment
            {
                return null;
            }

            public function findByIdempotencyKey(string $idempotencyKey, TenantId $tenantId): ?Payment
            {
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

    // ---------------------------------------------------------------
    // Success path
    // ---------------------------------------------------------------

    #[Test]
    public function itMarksPaymentAsFailedAndSaves(): void
    {
        $payment = $this->buildPendingPayment();
        $logger = $this->createMock(LoggerInterface::class);
        $savedItems = [];

        $repository = $this->buildRepository(
            findReturn: $payment,
            saveCb: static function (Payment $p) use (&$savedItems): void {
                $savedItems[] = $p;
            },
        );

        $command = new MarkPaymentAsFailed(
            id: PaymentId::generate(),
            errorMessage: 'Card was declined',
            errorCode: 'card_declined',
        );

        $handler = new MarkPaymentAsFailedHandler($repository, $logger);
        ($handler)($command);

        self::assertCount(1, $savedItems);
        self::assertTrue($savedItems[0]->status()->isFailed());
        self::assertSame('Card was declined', $savedItems[0]->errorMessage());
        self::assertSame('card_declined', $savedItems[0]->errorCode());
    }

    #[Test]
    public function itMarksPaymentAsFailedWithoutOptionalErrorCode(): void
    {
        $payment = $this->buildPendingPayment();
        $logger = $this->createMock(LoggerInterface::class);
        $savedItems = [];

        $repository = $this->buildRepository(
            findReturn: $payment,
            saveCb: static function (Payment $p) use (&$savedItems): void {
                $savedItems[] = $p;
            },
        );

        $command = new MarkPaymentAsFailed(
            id: PaymentId::generate(),
            errorMessage: 'Gateway timeout',
        );

        $handler = new MarkPaymentAsFailedHandler($repository, $logger);
        ($handler)($command);

        self::assertTrue($savedItems[0]->status()->isFailed());
        self::assertNull($savedItems[0]->errorCode());
    }

    // ---------------------------------------------------------------
    // Idempotency: already failed payment
    // ---------------------------------------------------------------

    #[Test]
    public function itIsIdempotentWhenPaymentIsAlreadyFailed(): void
    {
        $payment = $this->buildAlreadyFailedPayment();
        $logger = $this->createMock(LoggerInterface::class);
        $saveCalled = false;

        $repository = $this->buildRepository(
            findReturn: $payment,
            saveCb: static function () use (&$saveCalled): void {
                $saveCalled = true;
            },
        );

        $command = new MarkPaymentAsFailed(
            id: PaymentId::generate(),
            errorMessage: 'Another failure attempt',
        );

        $handler = new MarkPaymentAsFailedHandler($repository, $logger);
        ($handler)($command);

        // save() must NOT be called when payment is already failed
        self::assertFalse($saveCalled, 'save() should not be called for already-failed payment');
    }

    // ---------------------------------------------------------------
    // Not found
    // ---------------------------------------------------------------

    #[Test]
    public function itThrowsWhenPaymentNotFound(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $repository = $this->buildRepository(findReturn: null);

        $command = new MarkPaymentAsFailed(
            id: PaymentId::generate(),
            errorMessage: 'Does not matter',
        );

        $handler = new MarkPaymentAsFailedHandler($repository, $logger);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Payment not found/');

        ($handler)($command);
    }

    // ---------------------------------------------------------------
    // Invalid transition guard
    // ---------------------------------------------------------------

    #[Test]
    public function itThrowsRuntimeExceptionWhenTransitionIsInvalid(): void
    {
        // A captured payment cannot transition to failed (terminal states: refunded, failed, cancelled)
        // Build captured payment (authorized -> captured)
        $payment = Payment::reconstituteFromPersistence(
            id: PaymentId::generate(),
            tenantId: TenantId::fromString(self::TENANT_ID),
            orderId: 'order-abc',
            amount: Money::fromScalars(9999, 'USD'),
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe(),
            status: PaymentStatus::captured(),
            gatewayTransactionId: 'pi_captured',
            errorMessage: null,
            refundedAmount: Money::fromScalars(0, 'USD'),
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        );

        $logger = $this->createMock(LoggerInterface::class);
        $repository = $this->buildRepository(findReturn: $payment);

        $command = new MarkPaymentAsFailed(
            id: PaymentId::generate(),
            errorMessage: 'Some error',
        );

        $handler = new MarkPaymentAsFailedHandler($repository, $logger);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Cannot mark payment/');

        ($handler)($command);
    }

    // ---------------------------------------------------------------
    // Logging
    // ---------------------------------------------------------------

    #[Test]
    public function itLogsInfoBeforeProcessing(): void
    {
        $payment = $this->buildPendingPayment();
        $logger = $this->createMock(LoggerInterface::class);

        $logger->expects(self::atLeastOnce())
            ->method('info')
            ->with(
                self::logicalOr(
                    self::equalTo('Marking payment as failed'),
                    self::equalTo('Payment marked as failed successfully'),
                ),
                self::isType('array'),
            );

        $repository = $this->buildRepository(findReturn: $payment);
        $command = new MarkPaymentAsFailed(
            id: PaymentId::generate(),
            errorMessage: 'Card declined',
            errorCode: 'card_declined',
        );

        $handler = new MarkPaymentAsFailedHandler($repository, $logger);
        ($handler)($command);
    }

    #[Test]
    public function itLogsWarningWhenPaymentNotFound(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        $logger->expects(self::atLeastOnce())
            ->method('info')
            ->with('Marking payment as failed', self::isType('array'));

        $logger->expects(self::once())
            ->method('warning')
            ->with('Payment not found for marking as failed', self::isType('array'));

        $repository = $this->buildRepository(findReturn: null);
        $command = new MarkPaymentAsFailed(id: PaymentId::generate(), errorMessage: 'Error');
        $handler = new MarkPaymentAsFailedHandler($repository, $logger);

        try {
            ($handler)($command);
        } catch (\RuntimeException) {
            // Expected
        }
    }
}
