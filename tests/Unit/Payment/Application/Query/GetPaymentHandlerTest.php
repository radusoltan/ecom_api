<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Application\Query;

use App\Payment\Application\Query\GetPayment\GetPaymentHandler;
use App\Payment\Application\Query\GetPayment\GetPaymentQuery;
use App\Payment\Domain\Model\Payment;
use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use App\Payment\Domain\ValueObject\PaymentGateway;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Payment\Domain\ValueObject\PaymentMethod;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetPaymentHandler::class)]
final class GetPaymentHandlerTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';
    private const ORDER_ID = '550e8400-e29b-41d4-a716-446655440099';

    private PaymentRepositoryInterface $paymentRepository;
    private GetPaymentHandler $handler;

    protected function setUp(): void
    {
        $this->paymentRepository = $this->createMock(PaymentRepositoryInterface::class);
        $this->handler = new GetPaymentHandler($this->paymentRepository);
    }

    // ---------------------------------------------------------------
    // Happy path
    // ---------------------------------------------------------------

    #[Test]
    public function itReturnsPaymentWhenFound(): void
    {
        $paymentId = PaymentId::generate();
        $payment = $this->buildPayment($paymentId);

        $this->paymentRepository
            ->expects(self::once())
            ->method('findById')
            ->with(self::callback(fn (PaymentId $id) => $id->equals($paymentId)))
            ->willReturn($payment);

        $query = new GetPaymentQuery(paymentId: $paymentId);

        $result = ($this->handler)($query);

        self::assertInstanceOf(Payment::class, $result);
        self::assertTrue($result->id()->equals($paymentId));
    }

    #[Test]
    public function itReturnsNullWhenPaymentNotFound(): void
    {
        $paymentId = PaymentId::generate();

        $this->paymentRepository
            ->expects(self::once())
            ->method('findById')
            ->willReturn(null);

        $query = new GetPaymentQuery(paymentId: $paymentId);

        $result = ($this->handler)($query);

        self::assertNull($result);
    }

    #[Test]
    public function itPassesPaymentIdDirectlyToRepository(): void
    {
        $paymentId = PaymentId::generate();

        $this->paymentRepository
            ->expects(self::once())
            ->method('findById')
            ->with(self::identicalTo($paymentId))
            ->willReturn(null);

        $query = new GetPaymentQuery(paymentId: $paymentId);

        ($this->handler)($query);
    }

    #[Test]
    public function itReturnsDomainModelNotDto(): void
    {
        $paymentId = PaymentId::generate();
        $payment = $this->buildPayment($paymentId);

        $this->paymentRepository
            ->method('findById')
            ->willReturn($payment);

        $query = new GetPaymentQuery(paymentId: $paymentId);

        $result = ($this->handler)($query);

        // Handler returns Payment domain model directly (no DTO mapping)
        self::assertInstanceOf(Payment::class, $result);
    }

    #[Test]
    public function itPreservesAllPaymentFields(): void
    {
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $payment = Payment::create(
            id: $paymentId,
            tenantId: $tenantId,
            orderId: self::ORDER_ID,
            amount: Money::fromScalars(7500, 'EUR'),
            method: PaymentMethod::bankTransfer(),
            gateway: PaymentGateway::stripe(),
        );

        $this->paymentRepository
            ->method('findById')
            ->willReturn($payment);

        $query = new GetPaymentQuery(paymentId: $paymentId);

        $result = ($this->handler)($query);

        self::assertNotNull($result);
        self::assertSame(self::ORDER_ID, $result->orderId());
        self::assertSame(7500, $result->amount()->getAmount());
        self::assertSame('EUR', $result->currency());
        self::assertTrue($result->method()->isBankTransfer());
        self::assertTrue($result->tenantId()->equals($tenantId));
    }

    // ---------------------------------------------------------------
    // Helper
    // ---------------------------------------------------------------

    private function buildPayment(PaymentId $id): Payment
    {
        return Payment::create(
            id: $id,
            tenantId: TenantId::fromString(self::TENANT_ID),
            orderId: self::ORDER_ID,
            amount: Money::fromScalars(5000, 'USD'),
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe(),
        );
    }
}
