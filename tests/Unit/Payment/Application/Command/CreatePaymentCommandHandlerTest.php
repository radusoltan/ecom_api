<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Application\Command;

use App\Payment\Application\Command\CreatePayment;
use App\Payment\Application\Command\CreatePaymentHandler;
use App\Payment\Domain\Model\Payment;
use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use App\Payment\Domain\ValueObject\PaymentGateway;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Payment\Domain\ValueObject\PaymentMethod;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class CreatePaymentCommandHandlerTest extends TestCase
{
    private PaymentRepositoryInterface $repository;
    private CreatePaymentHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(PaymentRepositoryInterface::class);
        $this->handler = new CreatePaymentHandler($this->repository);
    }

    public function testHandleCreatesAndSavesPayment(): void
    {
        // Arrange
        $command = new CreatePayment(
            id: PaymentId::generate(),
            tenantId: TenantId::generate(),
            orderId: '01JCEX'.bin2hex(random_bytes(10)),
            amount: Money::fromScalars(9999, 'USD'),
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe()
        );

        $this->repository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Payment $payment) use ($command) {
                return $payment->id()->equals($command->id)
                    && $payment->tenantId()->equals($command->tenantId)
                    && $payment->orderId() === $command->orderId
                    && $payment->amount()->getAmount() === $command->amount->getAmount()
                    && $payment->currency() === $command->amount->getCurrency()->getCurrencyCode()
                    && $payment->status()->isPending();
            }));

        // Act
        ($this->handler)($command);

        // Assert - expectations verified by mock
    }

    public function testHandleCreatesPaymentInPendingStatus(): void
    {
        // Arrange
        $command = new CreatePayment(
            id: PaymentId::generate(),
            tenantId: TenantId::generate(),
            orderId: '01JCEX'.bin2hex(random_bytes(10)),
            amount: Money::fromScalars(5000, 'EUR'),
            method: PaymentMethod::paypal(),
            gateway: PaymentGateway::paypal()
        );

        $capturedPayment = null;
        $this->repository->expects($this->once())
            ->method('save')
            ->willReturnCallback(function (Payment $payment) use (&$capturedPayment) {
                $capturedPayment = $payment;
            });

        // Act
        ($this->handler)($command);

        // Assert
        $this->assertNotNull($capturedPayment);
        $this->assertTrue($capturedPayment->status()->isPending());
        $this->assertNull($capturedPayment->gatewayTransactionId());
        $this->assertSame(0, $capturedPayment->refundedAmount()->getAmount());
    }

    public function testHandleWithDifferentCurrencies(): void
    {
        // Arrange - GBP
        $command = new CreatePayment(
            id: PaymentId::generate(),
            tenantId: TenantId::generate(),
            orderId: '01JCEX'.bin2hex(random_bytes(10)),
            amount: Money::fromScalars(12500, 'GBP'),
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe()
        );

        $this->repository->expects($this->once())
            ->method('save')
            ->with($this->callback(fn (Payment $p) => 'GBP' === $p->currency()));

        // Act
        ($this->handler)($command);

        // Assert - expectations verified
    }

    public function testHandleWithDifferentPaymentMethods(): void
    {
        // Arrange - PayPal method
        $command = new CreatePayment(
            id: PaymentId::generate(),
            tenantId: TenantId::generate(),
            orderId: '01JCEX'.bin2hex(random_bytes(10)),
            amount: Money::fromScalars(7500, 'USD'),
            method: PaymentMethod::paypal(),
            gateway: PaymentGateway::paypal()
        );

        $this->repository->expects($this->once())
            ->method('save')
            ->with($this->callback(fn (Payment $p) => $p->method()->isPaypal()));

        // Act
        ($this->handler)($command);

        // Assert - expectations verified
    }
}
