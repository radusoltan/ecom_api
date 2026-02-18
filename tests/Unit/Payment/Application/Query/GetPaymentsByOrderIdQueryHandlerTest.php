<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Application\Query;

use App\Payment\Application\DTO\PaymentDTO;
use App\Payment\Application\Query\GetPaymentsByOrder;
use App\Payment\Application\Query\GetPaymentsByOrderHandler;
use App\Payment\Domain\Model\Payment;
use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use App\Payment\Domain\ValueObject\PaymentGateway;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Payment\Domain\ValueObject\PaymentMethod;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class GetPaymentsByOrderIdQueryHandlerTest extends TestCase
{
    private PaymentRepositoryInterface $repository;
    private GetPaymentsByOrderHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(PaymentRepositoryInterface::class);
        $this->handler = new GetPaymentsByOrderHandler($this->repository);
    }

    public function testHandleReturnsPaymentsForOrder(): void
    {
        // Arrange
        $tenantId = TenantId::generate();
        $orderId = '01JCEX'.bin2hex(random_bytes(10));

        $payment1 = Payment::create(
            id: PaymentId::generate(),
            tenantId: $tenantId,
            orderId: $orderId,
            amountInCents: 10000,
            currency: 'USD',
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe()
        );

        // Simulate a refunded payment for the same order
        $payment2 = Payment::create(
            id: PaymentId::generate(),
            tenantId: $tenantId,
            orderId: $orderId,
            amountInCents: 5000,
            currency: 'USD',
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe()
        );

        $query = new GetPaymentsByOrder(
            orderId: $orderId,
            tenantId: $tenantId
        );

        $this->repository->expects($this->once())
            ->method('findAllByOrderId')
            ->with($orderId)
            ->willReturn([$payment1, $payment2]);

        // Act
        $result = ($this->handler)($query);

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertContainsOnlyInstancesOf(PaymentDTO::class, $result);
        $this->assertSame($orderId, $result[0]->orderId);
        $this->assertSame($orderId, $result[1]->orderId);
    }

    public function testHandleReturnsEmptyArrayForOrderWithNoPayments(): void
    {
        // Arrange
        $query = new GetPaymentsByOrder(
            orderId: '01JCEX'.bin2hex(random_bytes(10)),
            tenantId: TenantId::generate()
        );

        $this->repository->expects($this->once())
            ->method('findAllByOrderId')
            ->willReturn([]);

        // Act
        $result = ($this->handler)($query);

        // Assert
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testHandleReturnsOnlySinglePaymentIfOrderHasOne(): void
    {
        // Arrange
        $tenantId = TenantId::generate();
        $orderId = '01JCEX'.bin2hex(random_bytes(10));

        $payment = Payment::create(
            id: PaymentId::generate(),
            tenantId: $tenantId,
            orderId: $orderId,
            amountInCents: 7500,
            currency: 'GBP',
            method: PaymentMethod::paypal(),
            gateway: PaymentGateway::paypal()
        );

        $query = new GetPaymentsByOrder(orderId: $orderId, tenantId: $tenantId);

        $this->repository->method('findAllByOrderId')->willReturn([$payment]);

        // Act
        $result = ($this->handler)($query);

        // Assert
        $this->assertCount(1, $result);
        $this->assertSame(7500, $result[0]->amountInCents);
        $this->assertSame('GBP', $result[0]->currency);
    }
}
