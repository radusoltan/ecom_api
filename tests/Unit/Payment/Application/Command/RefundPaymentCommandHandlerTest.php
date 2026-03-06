<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Application\Command;

use App\Payment\Application\Command\RefundPayment;
use App\Payment\Application\Command\RefundPaymentHandler;
use App\Payment\Domain\Model\Payment;
use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use App\Payment\Domain\Service\PaymentGatewayInterface;
use App\Payment\Domain\Service\RefundResult;
use App\Payment\Domain\ValueObject\PaymentGateway;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Payment\Domain\ValueObject\PaymentMethod;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class RefundPaymentCommandHandlerTest extends TestCase
{
    private PaymentRepositoryInterface&MockObject $repository;
    private PaymentGatewayInterface&MockObject $gateway;
    private LoggerInterface&MockObject $logger;
    private RefundPaymentHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(PaymentRepositoryInterface::class);
        $this->gateway = $this->createMock(PaymentGatewayInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new RefundPaymentHandler(
            $this->repository,
            $this->gateway,
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
            orderId: '01JCEX'.bin2hex(random_bytes(10)),
            amount: Money::fromScalars(9999, 'USD'),
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe()
        );
        $payment->authorize('pi_abc123xyz');
        $payment->capture();

        $command = new RefundPayment(
            id: $paymentId,
            refundAmount: Money::fromScalars(5000, 'USD'),
            reason: 'Customer requested refund'
        );

        $this->repository->expects($this->once())
            ->method('findById')
            ->with($paymentId)
            ->willReturn($payment);

        // Gateway creates refund successfully
        $this->gateway->expects($this->once())
            ->method('createRefund')
            ->willReturn(RefundResult::success(
                gatewayRefundId: 're_abc123xyz',
                status: 'succeeded',
                amount: Money::fromScalars(5000, 'USD')
            ));

        $this->repository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Payment $p) {
                return $p->status()->isRefunded()
                    && 5000 === $p->refundedAmount()->getAmount();
            }));

        // Act
        ($this->handler)($command);
    }

    public function testHandleFullRefund(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::generate();

        $payment = Payment::create(
            id: $paymentId,
            tenantId: $tenantId,
            orderId: '01JCEX'.bin2hex(random_bytes(10)),
            amount: Money::fromScalars(10000, 'USD'),
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe()
        );
        $payment->authorize('pi_test123');
        $payment->capture();

        $command = new RefundPayment(
            id: $paymentId,
            refundAmount: Money::fromScalars(10000, 'USD'),
            reason: 'Order cancelled'
        );

        $this->repository->method('findById')->willReturn($payment);

        $this->gateway->method('createRefund')
            ->willReturn(RefundResult::success(
                gatewayRefundId: 're_test123',
                status: 'succeeded',
                amount: Money::fromScalars(10000, 'USD')
            ));

        $this->repository->expects($this->once())
            ->method('save')
            ->with($this->callback(
                fn (Payment $p) => 10000 === $p->refundedAmount()->getAmount()
            ));

        // Act
        ($this->handler)($command);
    }

    public function testHandleThrowsExceptionWhenPaymentNotFound(): void
    {
        // Arrange
        $command = new RefundPayment(
            id: PaymentId::generate(),
            refundAmount: Money::fromScalars(5000, 'USD'),
            reason: 'Refund'
        );

        $this->repository->expects($this->once())
            ->method('findById')
            ->willReturn(null);

        $this->repository->expects($this->never())
            ->method('save');

        // Assert - handler throws InvalidArgumentException for not found
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Payment not found/');

        // Act
        ($this->handler)($command);
    }

    public function testHandleThrowsExceptionWhenGatewayRefundFails(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::generate();

        $payment = Payment::create(
            id: $paymentId,
            tenantId: $tenantId,
            orderId: '01JCEX'.bin2hex(random_bytes(10)),
            amount: Money::fromScalars(9999, 'USD'),
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe()
        );
        $payment->authorize('pi_abc123xyz');
        $payment->capture();

        $command = new RefundPayment(
            id: $paymentId,
            refundAmount: Money::fromScalars(5000, 'USD'),
            reason: 'Customer requested refund'
        );

        $this->repository->expects($this->once())
            ->method('findById')
            ->willReturn($payment);

        // Gateway returns failure
        $this->gateway->expects($this->once())
            ->method('createRefund')
            ->willReturn(RefundResult::failure(
                errorCode: 'refund_failed',
                errorMessage: 'Refund failed at gateway'
            ));

        // Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Refund failed at gateway');

        // Act
        ($this->handler)($command);
    }

    public function testHandleThrowsExceptionWhenRefundingPendingPayment(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::generate();

        // Create a pending payment (not captured) - gatewayTransactionId is null
        $payment = Payment::create(
            id: $paymentId,
            tenantId: $tenantId,
            orderId: '01JCEX'.bin2hex(random_bytes(10)),
            amount: Money::fromScalars(9999, 'USD'),
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe()
        );

        $command = new RefundPayment(
            id: $paymentId,
            refundAmount: Money::fromScalars(5000, 'USD'),
            reason: 'Customer requested refund'
        );

        $this->repository->expects($this->once())
            ->method('findById')
            ->willReturn($payment);

        // Gateway should never be called if payment has no gateway transaction ID
        $this->gateway->expects($this->never())
            ->method('createRefund');

        $this->repository->expects($this->never())
            ->method('save');

        // Assert - handler throws InvalidArgumentException for uncaptured payment
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/has not been captured yet/');

        // Act
        ($this->handler)($command);
    }
}
