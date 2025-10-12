<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Application\Command;

use App\Payment\Application\Command\RefundPayment;
use App\Payment\Application\Command\RefundPaymentHandler;
use App\Payment\Domain\Model\Payment;
use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use App\Payment\Domain\ValueObject\PaymentGateway;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Payment\Domain\ValueObject\PaymentMethod;
use App\Payment\Infrastructure\Gateway\PaymentGatewayFactory;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class RefundPaymentCommandHandlerTest extends TestCase
{
    private PaymentRepositoryInterface $repository;
    private PaymentGatewayFactory $gatewayFactory;
    private LoggerInterface $logger;
    private RefundPaymentHandler $handler;
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

        $this->handler = new RefundPaymentHandler(
            $this->repository,
            $this->gatewayFactory,
            $this->logger
        );
    }

    public function testHandleRefundsCapturedPayment(): void
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
        $payment->authorize('pi_abc123xyz');
        $payment->capture();

        $command = new RefundPayment(
            id: $paymentId,
            tenantId: $tenantId,
            refundAmountInCents: 5000,
            reason: 'Customer requested refund'
        );

        // Set expectations on the stripe gateway
        $this->stripeGateway->expects($this->once())
            ->method('refund')
            ->with('pi_abc123xyz', 5000, 'Customer requested refund')
            ->willReturn([
                'refund_id' => 're_abc123xyz',
                'refunded_amount' => 5000,
                'status' => 'refunded'
            ]);

        $this->repository->expects($this->once())
            ->method('findById')
            ->with($paymentId, $tenantId)
            ->willReturn($payment);

        $this->repository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Payment $p) {
                return $p->status()->isRefunded()
                    && $p->refundedAmountInCents() === 5000;
            }));

        // Act
        ($this->handler)($command);

        // Assert - expectations verified
    }

    public function testHandleFullRefund(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::generate();

        $payment = Payment::create(
            id: $paymentId,
            tenantId: $tenantId,
            orderId: '01JCEX' . bin2hex(random_bytes(10)),
            amountInCents: 10000,
            currency: 'USD',
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe()
        );
        $payment->authorize('pi_test123');
        $payment->capture();

        $command = new RefundPayment(
            id: $paymentId,
            tenantId: $tenantId,
            refundAmountInCents: 10000, // Full refund
            reason: 'Order cancelled'
        );

        // Set expectations on the stripe gateway
        $this->stripeGateway->method('refund')
            ->with('pi_test123', 10000, 'Order cancelled')
            ->willReturn([
                'refund_id' => 're_test123',
                'refunded_amount' => 10000,
                'status' => 'refunded'
            ]);

        $this->repository->method('findById')->willReturn($payment);
        $this->repository->expects($this->once())
            ->method('save')
            ->with($this->callback(fn(Payment $p) =>
                $p->refundedAmountInCents() === 10000
            ));

        // Act
        ($this->handler)($command);

        // Assert - expectations verified
    }

    public function testHandleThrowsExceptionWhenPaymentNotFound(): void
    {
        // Arrange
        $command = new RefundPayment(
            id: PaymentId::generate(),
            tenantId: TenantId::generate(),
            refundAmountInCents: 5000,
            reason: 'Refund'
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
