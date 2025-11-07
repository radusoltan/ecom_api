<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Application\Query;

use App\Payment\Application\DTO\PaymentDTO;
use App\Payment\Application\Query\GetAllPayments;
use App\Payment\Application\Query\GetAllPaymentsHandler;
use App\Payment\Domain\Model\Payment;
use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use App\Payment\Domain\ValueObject\PaymentGateway;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Payment\Domain\ValueObject\PaymentMethod;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class GetAllPaymentsQueryHandlerTest extends TestCase
{
    private PaymentRepositoryInterface $repository;
    private GetAllPaymentsHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(PaymentRepositoryInterface::class);
        $this->handler = new GetAllPaymentsHandler($this->repository);
    }

    public function testHandleReturnsArrayOfPaymentDTOs(): void
    {
        // Arrange
        $tenantId = TenantId::generate();

        $payment1 = Payment::create(
            id: PaymentId::generate(),
            tenantId: $tenantId,
            orderId: '01JCEX'.bin2hex(random_bytes(10)),
            amountInCents: 5000,
            currency: 'USD',
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe()
        );

        $payment2 = Payment::create(
            id: PaymentId::generate(),
            tenantId: $tenantId,
            orderId: '01JCEX'.bin2hex(random_bytes(10)),
            amountInCents: 10000,
            currency: 'EUR',
            method: PaymentMethod::paypal(),
            gateway: PaymentGateway::paypal()
        );

        $query = new GetAllPayments(tenantId: $tenantId);

        $this->repository->expects($this->once())
            ->method('findAll')
            ->with($tenantId)
            ->willReturn([$payment1, $payment2]);

        // Act
        $result = ($this->handler)($query);

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertContainsOnlyInstancesOf(PaymentDTO::class, $result);
        $this->assertSame(5000, $result[0]->amountInCents);
        $this->assertSame('USD', $result[0]->currency);
        $this->assertSame(10000, $result[1]->amountInCents);
        $this->assertSame('EUR', $result[1]->currency);
    }

    public function testHandleReturnsEmptyArrayWhenNoPaymentsFound(): void
    {
        // Arrange
        $query = new GetAllPayments(tenantId: TenantId::generate());

        $this->repository->expects($this->once())
            ->method('findAll')
            ->willReturn([]);

        // Act
        $result = ($this->handler)($query);

        // Assert
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testHandleReturnsMultipleTenantIsolatedPayments(): void
    {
        // Arrange
        $tenantId = TenantId::generate();

        $payments = [];
        for ($i = 0; $i < 5; ++$i) {
            $payments[] = Payment::create(
                id: PaymentId::generate(),
                tenantId: $tenantId,
                orderId: '01JCEX'.bin2hex(random_bytes(10)),
                amountInCents: 1000 * ($i + 1),
                currency: 'USD',
                method: PaymentMethod::card(),
                gateway: PaymentGateway::stripe()
            );
        }

        $query = new GetAllPayments(tenantId: $tenantId);

        $this->repository->method('findAll')->willReturn($payments);

        // Act
        $result = ($this->handler)($query);

        // Assert
        $this->assertCount(5, $result);
        foreach ($result as $dto) {
            $this->assertSame($tenantId->toString(), $dto->tenantId);
        }
    }
}
