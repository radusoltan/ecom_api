<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Application\Command;

use App\Payment\Application\Command\CancelPayment;
use App\Payment\Application\Command\CancelPaymentHandler;
use App\Payment\Domain\Model\Payment;
use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use App\Payment\Domain\ValueObject\PaymentGateway;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Payment\Domain\ValueObject\PaymentMethod;
use App\Payment\Infrastructure\Gateway\PaymentGatewayFactory;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class CancelPaymentCommandHandlerTest extends TestCase
{
    private PaymentRepositoryInterface $repository;
    private PaymentGatewayFactory $gatewayFactory;
    private LoggerInterface $logger;
    private CancelPaymentHandler $handler;
    private \App\Payment\Domain\Service\PaymentGatewayInterface $stripeGateway;
    private \App\Payment\Domain\Service\PaymentGatewayInterface $paypalGateway;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(PaymentRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        // Create mock gateways
        $this->stripeGateway = $this->createMock(\App\Payment\Domain\Service\PaymentGatewayInterface::class);
        $this->paypalGateway = $this->createMock(\App\Payment\Domain\Service\PaymentGatewayInterface::class);

        // Create real factory instance with mock gateways
        $this->gatewayFactory = new PaymentGatewayFactory([
            'stripe' => $this->stripeGateway,
            'paypal' => $this->paypalGateway,
        ]);

        $this->handler = new CancelPaymentHandler(
            $this->repository,
            $this->gatewayFactory,
            $this->logger
        );
    }

    public function testHandleCancelsPendingPayment(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::generate();

        $payment = Payment::create(
            id: $paymentId,
            tenantId: $tenantId,
            orderId: '01JCEX' . bin2hex(random_bytes(10)),
            amountInCents: 9999,
            currency: 'USD',
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe()
        );

        $command = new CancelPayment(
            id: $paymentId,
            tenantId: $tenantId,
            reason: 'Customer cancelled order'
        );

        $this->repository->expects($this->once())
            ->method('findById')
            ->with($paymentId, $tenantId)
            ->willReturn($payment);

        $this->repository->expects($this->once())
            ->method('save')
            ->with($this->callback(fn(Payment $p) => $p->status()->isCancelled()));

        // Act
        ($this->handler)($command);

        // Assert - expectations verified
    }

    public function testHandleCancelsAuthorizedPayment(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::generate();

        $payment = Payment::create(
            id: $paymentId,
            tenantId: $tenantId,
            orderId: '01JCEX' . bin2hex(random_bytes(10)),
            amountInCents: 5000,
            currency: 'EUR',
            method: PaymentMethod::paypal(),
            gateway: PaymentGateway::paypal()
        );
        $payment->authorize('paypal_tx_123');

        $command = new CancelPayment(
            id: $paymentId,
            tenantId: $tenantId,
            reason: 'Timeout'
        );

        // Set expectations on the paypal gateway
        $this->paypalGateway->expects($this->once())
            ->method('cancel')
            ->with('paypal_tx_123', 'Timeout')
            ->willReturn([
                'status' => 'cancelled'
            ]);

        $this->repository->method('findById')->willReturn($payment);
        $this->repository->expects($this->once())
            ->method('save')
            ->with($this->callback(fn(Payment $p) => $p->status()->isCancelled()));

        // Act
        ($this->handler)($command);

        // Assert - expectations verified
    }

    public function testHandleThrowsExceptionWhenPaymentNotFound(): void
    {
        // Arrange
        $command = new CancelPayment(
            id: PaymentId::generate(),
            tenantId: TenantId::generate(),
            reason: 'Cancel'
        );

        $this->repository->expects($this->once())
            ->method('findById')
            ->willReturn(null);

        $this->repository->expects($this->never())
            ->method('save');

        // Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Payment.*not found/');

        // Act
        ($this->handler)($command);
    }
}
