<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Application\Command;

use App\Payment\Application\Command\CancelPayment;
use App\Payment\Application\Command\CancelPaymentHandler;
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
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests for CancelPaymentHandler.
 */
final class CancelPaymentCommandHandlerTest extends TestCase
{
    private PaymentGatewayInterface $gateway;
    private LoggerInterface $logger;
    private CancelPaymentHandler $handler;

    protected function setUp(): void
    {
        $this->gateway = $this->createMock(PaymentGatewayInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    private function createRepositoryStub(?Payment $returnValue): PaymentRepositoryInterface
    {
        return new class($returnValue) implements PaymentRepositoryInterface {
            private ?Payment $savedPayment = null;

            public function __construct(private readonly ?Payment $findByIdResult)
            {
            }

            public function save(Payment $payment): void
            {
                $this->savedPayment = $payment;
            }

            public function getSavedPayment(): ?Payment
            {
                return $this->savedPayment;
            }

            public function findById(PaymentId $id): ?Payment
            {
                return $this->findByIdResult;
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

    public function testHandleCancelsPendingPayment(): void
    {
        // Arrange - pending payment has no gateway transaction ID
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::generate();

        $payment = Payment::create(
            id: PaymentId::generate(),
            tenantId: $tenantId,
            orderId: '01JCEX'.bin2hex(random_bytes(10)),
            amountInCents: 9999,
            currency: 'USD',
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe()
        );

        $repository = $this->createRepositoryStub($payment);
        $this->handler = new CancelPaymentHandler($repository, $this->gateway, $this->logger);

        $command = new CancelPayment(
            id: $paymentId,
            reason: 'Customer cancelled order'
        );

        // Gateway should NOT be called for pending payments (no transaction ID)
        $this->gateway->expects($this->never())
            ->method('cancelPaymentIntent');

        // Act
        ($this->handler)($command);

        // Assert - payment is now cancelled
        $saved = $repository->getSavedPayment();
        $this->assertNotNull($saved);
        $this->assertTrue($saved->status()->isCancelled());
    }

    public function testHandleCancelsAuthorizedPayment(): void
    {
        // Arrange - authorized payment has a gateway transaction ID
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::generate();

        $payment = Payment::reconstituteFromPersistence(
            id: PaymentId::generate(),
            tenantId: $tenantId,
            orderId: '01JCEX'.bin2hex(random_bytes(10)),
            amountInCents: 5000,
            currency: 'EUR',
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe(),
            status: PaymentStatus::authorized(),
            gatewayTransactionId: 'pi_stripe_authorized_123',
            errorMessage: null,
            refundedAmountInCents: 0,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable()
        );

        $repository = $this->createRepositoryStub($payment);
        $this->handler = new CancelPaymentHandler($repository, $this->gateway, $this->logger);

        $command = new CancelPayment(
            id: $paymentId,
            reason: 'Timeout'
        );

        // Gateway should be called for authorized payments (they have a transaction ID)
        $this->gateway->expects($this->once())
            ->method('cancelPaymentIntent')
            ->with('pi_stripe_authorized_123')
            ->willReturn(PaymentIntentResult::success(
                gatewayPaymentIntentId: 'pi_stripe_authorized_123',
                status: 'canceled',
                amount: Money::fromScalars(5000, 'EUR')
            ));

        // Act
        ($this->handler)($command);

        // Assert
        $saved = $repository->getSavedPayment();
        $this->assertNotNull($saved);
        $this->assertTrue($saved->status()->isCancelled());
    }

    public function testHandleThrowsExceptionWhenPaymentNotFound(): void
    {
        // Arrange - repository returns null (payment not found)
        $command = new CancelPayment(
            id: PaymentId::generate(),
            reason: 'Cancel'
        );

        $repository = $this->createRepositoryStub(null);
        $this->handler = new CancelPaymentHandler($repository, $this->gateway, $this->logger);

        // Assert - handler throws InvalidArgumentException when payment not found
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Payment.*not found/');

        // Act
        ($this->handler)($command);
    }
}
