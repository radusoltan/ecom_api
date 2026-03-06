<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Application\Query;

use App\Payment\Application\Query\GetPaymentByOrder\GetPaymentByOrderHandler;
use App\Payment\Application\Query\GetPaymentByOrder\GetPaymentByOrderQuery;
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

#[CoversClass(GetPaymentByOrderHandler::class)]
final class GetPaymentByOrderHandlerTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';
    private const ORDER_ID = '550e8400-e29b-41d4-a716-446655440099';

    private PaymentRepositoryInterface $paymentRepository;
    private GetPaymentByOrderHandler $handler;

    protected function setUp(): void
    {
        $this->paymentRepository = $this->createMock(PaymentRepositoryInterface::class);
        $this->handler = new GetPaymentByOrderHandler($this->paymentRepository);
    }

    // ---------------------------------------------------------------
    // Happy path
    // ---------------------------------------------------------------

    #[Test]
    public function itReturnsPaymentWhenOrderHasOne(): void
    {
        $payment = $this->buildPayment(self::ORDER_ID);

        $this->paymentRepository
            ->expects(self::once())
            ->method('findByOrderId')
            ->with(self::ORDER_ID)
            ->willReturn($payment);

        $query = new GetPaymentByOrderQuery(orderId: self::ORDER_ID);

        $result = ($this->handler)($query);

        self::assertInstanceOf(Payment::class, $result);
        self::assertSame(self::ORDER_ID, $result->orderId());
    }

    #[Test]
    public function itReturnsNullWhenOrderHasNoPayment(): void
    {
        $this->paymentRepository
            ->expects(self::once())
            ->method('findByOrderId')
            ->with(self::ORDER_ID)
            ->willReturn(null);

        $query = new GetPaymentByOrderQuery(orderId: self::ORDER_ID);

        $result = ($this->handler)($query);

        self::assertNull($result);
    }

    #[Test]
    public function itPassesOrderIdAsStringToRepository(): void
    {
        $orderId = '550e8400-e29b-41d4-a716-446655440055';

        $this->paymentRepository
            ->expects(self::once())
            ->method('findByOrderId')
            ->with($orderId)
            ->willReturn(null);

        $query = new GetPaymentByOrderQuery(orderId: $orderId);

        ($this->handler)($query);
    }

    #[Test]
    public function itReturnsDomainModelNotDto(): void
    {
        $payment = $this->buildPayment(self::ORDER_ID);

        $this->paymentRepository
            ->method('findByOrderId')
            ->willReturn($payment);

        $query = new GetPaymentByOrderQuery(orderId: self::ORDER_ID);

        $result = ($this->handler)($query);

        // Handler returns Payment domain model, not a DTO
        self::assertInstanceOf(Payment::class, $result);
    }

    #[Test]
    public function itPreservesPaymentDataFromRepository(): void
    {
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $payment = Payment::create(
            id: $paymentId,
            tenantId: $tenantId,
            orderId: self::ORDER_ID,
            amount: Money::fromScalars(12000, 'EUR'),
            method: PaymentMethod::paypal(),
            gateway: PaymentGateway::paypal(),
        );

        $this->paymentRepository
            ->method('findByOrderId')
            ->willReturn($payment);

        $query = new GetPaymentByOrderQuery(orderId: self::ORDER_ID);

        $result = ($this->handler)($query);

        self::assertNotNull($result);
        self::assertTrue($result->id()->equals($paymentId));
        self::assertSame(12000, $result->amount()->getAmount());
        self::assertSame('EUR', $result->currency());
        self::assertTrue($result->method()->isPaypal());
    }

    #[Test]
    public function itDelegatesOnlyToFindByOrderId(): void
    {
        // Ensure the handler does not call any other repository method
        $this->paymentRepository
            ->expects(self::once())
            ->method('findByOrderId');

        $this->paymentRepository->expects(self::never())->method('findById');
        $this->paymentRepository->expects(self::never())->method('findAll');
        $this->paymentRepository->expects(self::never())->method('findAllByOrderId');

        $query = new GetPaymentByOrderQuery(orderId: self::ORDER_ID);

        ($this->handler)($query);
    }

    // ---------------------------------------------------------------
    // Helper
    // ---------------------------------------------------------------

    private function buildPayment(string $orderId): Payment
    {
        return Payment::create(
            id: PaymentId::generate(),
            tenantId: TenantId::fromString(self::TENANT_ID),
            orderId: $orderId,
            amount: Money::fromScalars(5000, 'USD'),
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe(),
        );
    }
}
