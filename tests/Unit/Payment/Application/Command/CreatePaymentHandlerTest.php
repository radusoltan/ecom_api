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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreatePaymentHandler::class)]
final class CreatePaymentHandlerTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';
    private const ORDER_ID = '550e8400-e29b-41d4-a716-446655440099';

    private PaymentRepositoryInterface $paymentRepository;
    private CreatePaymentHandler $handler;

    protected function setUp(): void
    {
        $this->paymentRepository = $this->createMock(PaymentRepositoryInterface::class);
        $this->handler = new CreatePaymentHandler($this->paymentRepository);
    }

    // ---------------------------------------------------------------
    // Happy path
    // ---------------------------------------------------------------

    #[Test]
    public function itCreatesAndSavesPaymentInPendingStatus(): void
    {
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $command = new CreatePayment(
            id: $paymentId,
            tenantId: $tenantId,
            orderId: self::ORDER_ID,
            amount: Money::fromScalars(9999, 'USD'),
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe(),
        );

        $this->paymentRepository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(function (Payment $payment) use ($paymentId, $tenantId): bool {
                self::assertTrue($payment->id()->equals($paymentId));
                self::assertTrue($payment->tenantId()->equals($tenantId));
                self::assertSame(self::ORDER_ID, $payment->orderId());
                self::assertSame(9999, $payment->amount()->getAmount());
                self::assertSame('USD', $payment->currency());
                self::assertTrue($payment->status()->isPending());

                return true;
            }));

        ($this->handler)($command);
    }

    #[Test]
    public function itSetsCorrectPaymentMethodAndGateway(): void
    {
        $command = new CreatePayment(
            id: PaymentId::generate(),
            tenantId: TenantId::fromString(self::TENANT_ID),
            orderId: self::ORDER_ID,
            amount: Money::fromScalars(5000, 'EUR'),
            method: PaymentMethod::paypal(),
            gateway: PaymentGateway::paypal(),
        );

        $captured = null;
        $this->paymentRepository
            ->expects(self::once())
            ->method('save')
            ->willReturnCallback(function (Payment $p) use (&$captured): void {
                $captured = $p;
            });

        ($this->handler)($command);

        self::assertNotNull($captured);
        self::assertTrue($captured->method()->isPaypal());
        self::assertTrue($captured->gateway()->isPaypal());
    }

    #[Test]
    public function itCreatesPaymentWithNullGatewayTransactionId(): void
    {
        $command = new CreatePayment(
            id: PaymentId::generate(),
            tenantId: TenantId::fromString(self::TENANT_ID),
            orderId: self::ORDER_ID,
            amount: Money::fromScalars(1000, 'USD'),
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe(),
        );

        $captured = null;
        $this->paymentRepository
            ->method('save')
            ->willReturnCallback(function (Payment $p) use (&$captured): void {
                $captured = $p;
            });

        ($this->handler)($command);

        self::assertNotNull($captured);
        self::assertNull($captured->gatewayTransactionId());
        self::assertNull($captured->errorMessage());
    }

    #[Test]
    public function itCreatesPaymentWithZeroRefundedAmount(): void
    {
        $command = new CreatePayment(
            id: PaymentId::generate(),
            tenantId: TenantId::fromString(self::TENANT_ID),
            orderId: self::ORDER_ID,
            amount: Money::fromScalars(2500, 'GBP'),
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe(),
        );

        $captured = null;
        $this->paymentRepository
            ->method('save')
            ->willReturnCallback(function (Payment $p) use (&$captured): void {
                $captured = $p;
            });

        ($this->handler)($command);

        self::assertNotNull($captured);
        self::assertSame(0, $captured->refundedAmount()->getAmount());
        self::assertSame('GBP', $captured->refundedAmount()->getCurrency()->getCurrencyCode());
    }

    #[Test]
    public function itSupportsDifferentCurrencies(): void
    {
        foreach (['USD', 'EUR', 'GBP'] as $currency) {
            $command = new CreatePayment(
                id: PaymentId::generate(),
                tenantId: TenantId::fromString(self::TENANT_ID),
                orderId: self::ORDER_ID,
                amount: Money::fromScalars(1000, $currency),
                method: PaymentMethod::card(),
                gateway: PaymentGateway::stripe(),
            );

            $captured = null;
            $this->paymentRepository
                ->method('save')
                ->willReturnCallback(function (Payment $p) use (&$captured): void {
                    $captured = $p;
                });

            ($this->handler)($command);

            self::assertSame($currency, $captured->currency());
        }
    }

    #[Test]
    public function itReturnsVoidAndDoesNotReturnAnything(): void
    {
        $command = new CreatePayment(
            id: PaymentId::generate(),
            tenantId: TenantId::fromString(self::TENANT_ID),
            orderId: self::ORDER_ID,
            amount: Money::fromScalars(1000, 'USD'),
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe(),
        );

        $this->paymentRepository->method('save');

        $result = ($this->handler)($command);

        self::assertNull($result);
    }

    #[Test]
    public function itRecordsPaymentCreatedEvent(): void
    {
        $command = new CreatePayment(
            id: PaymentId::generate(),
            tenantId: TenantId::fromString(self::TENANT_ID),
            orderId: self::ORDER_ID,
            amount: Money::fromScalars(3000, 'USD'),
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe(),
        );

        $captured = null;
        $this->paymentRepository
            ->method('save')
            ->willReturnCallback(function (Payment $p) use (&$captured): void {
                $captured = $p;
            });

        ($this->handler)($command);

        self::assertNotNull($captured);
        // Domain events are recorded — pop them to verify
        $events = $captured->popEvents();
        self::assertCount(1, $events);
    }
}
