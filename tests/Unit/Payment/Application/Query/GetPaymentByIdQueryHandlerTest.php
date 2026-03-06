<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Application\Query;

use App\Payment\Application\DTO\PaymentDTO;
use App\Payment\Application\Query\GetPaymentById;
use App\Payment\Application\Query\GetPaymentByIdHandler;
use App\Payment\Domain\Model\Payment;
use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use App\Payment\Domain\ValueObject\PaymentGateway;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Payment\Domain\ValueObject\PaymentMethod;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class GetPaymentByIdQueryHandlerTest extends TestCase
{
    private PaymentRepositoryInterface $repository;
    private GetPaymentByIdHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(PaymentRepositoryInterface::class);
        $this->handler = new GetPaymentByIdHandler($this->repository);
    }

    public function testHandleReturnsPaymentDTO(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::generate();
        $orderId = '01JCEX'.bin2hex(random_bytes(10));

        $payment = Payment::create(
            id: $paymentId,
            tenantId: $tenantId,
            orderId: $orderId,
            amount: Money::fromScalars(9999, 'USD'),
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe()
        );

        $query = new GetPaymentById(
            id: $paymentId,
            tenantId: $tenantId
        );

        $this->repository->expects($this->once())
            ->method('findById')
            ->with($paymentId)
            ->willReturn($payment);

        // Act
        $result = ($this->handler)($query);

        // Assert
        $this->assertInstanceOf(PaymentDTO::class, $result);
        $this->assertSame($paymentId->toString(), $result->id);
        $this->assertSame($tenantId->toString(), $result->tenantId);
        $this->assertSame($orderId, $result->orderId);
        $this->assertSame(9999, $result->amountInCents);
        $this->assertSame('USD', $result->currency);
        $this->assertSame('card', $result->method);
        $this->assertSame('stripe', $result->gateway);
        $this->assertSame('pending', $result->status);
        $this->assertNull($result->gatewayTransactionId);
        $this->assertSame(0, $result->refundedAmountInCents);
    }

    public function testHandleReturnsNullWhenPaymentNotFound(): void
    {
        // Arrange
        $query = new GetPaymentById(
            id: PaymentId::generate(),
            tenantId: TenantId::generate()
        );

        $this->repository->expects($this->once())
            ->method('findById')
            ->willReturn(null);

        // Act
        $result = ($this->handler)($query);

        // Assert
        $this->assertNull($result);
    }

    public function testHandleReturnsAuthorizedPaymentWithTransactionId(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::generate();
        $gatewayTransactionId = 'pi_abc123xyz';

        $payment = Payment::create(
            id: $paymentId,
            tenantId: $tenantId,
            orderId: '01JCEX'.bin2hex(random_bytes(10)),
            amount: Money::fromScalars(5000, 'EUR'),
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe()
        );
        $payment->authorize($gatewayTransactionId);

        $query = new GetPaymentById(id: $paymentId, tenantId: $tenantId);

        $this->repository->method('findById')->willReturn($payment);

        // Act
        $result = ($this->handler)($query);

        // Assert
        $this->assertSame('authorized', $result->status);
        $this->assertSame($gatewayTransactionId, $result->gatewayTransactionId);
    }
}
