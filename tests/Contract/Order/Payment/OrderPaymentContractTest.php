<?php

declare(strict_types=1);

namespace App\Tests\Contract\Order\Payment;

use App\Order\Domain\Event\OrderPaid;
use App\Order\Domain\Event\OrderPlaced;
use App\Order\Domain\Model\OrderId;
use App\Payment\Domain\Event\PaymentCaptured;
use App\Payment\Domain\Event\PaymentFailed;
use App\Payment\Domain\Event\PaymentRefunded;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Contract tests for the Order <-> Payment boundary.
 *
 * Verifies that events crossing the Order/Payment boundary maintain
 * their expected structure, required fields, and type contracts.
 *
 * Producer: Order (OrderPlaced, OrderPaid)
 * Consumer: Payment (PaymentCaptured, PaymentFailed, PaymentRefunded)
 */
#[CoversNothing]
#[Group('contract')]
final class OrderPaymentContractTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    // -----------------------------------------------------------------------
    // OrderPlaced — produced by Order, consumed by Payment/Notifications
    // -----------------------------------------------------------------------

    #[Test]
    public function orderPlacedEventHasRequiredFields(): void
    {
        $event = new OrderPlaced(
            orderId: OrderId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            tenantId: TenantId::fromString(self::TENANT_ID),
            customerEmail: 'customer@example.com',
            total: Money::fromScalars(9999, 'USD'),
        );

        self::assertInstanceOf(OrderId::class, $event->orderId);
        self::assertInstanceOf(TenantId::class, $event->tenantId);
        self::assertIsString($event->customerEmail);
        self::assertNotEmpty($event->customerEmail);
        self::assertInstanceOf(Money::class, $event->total);
    }

    #[Test]
    public function orderPlacedMoneyContractIsConsistent(): void
    {
        $total = Money::fromScalars(2500, 'USD');
        $event = new OrderPlaced(
            orderId: OrderId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            tenantId: TenantId::fromString(self::TENANT_ID),
            customerEmail: 'test@example.com',
            total: $total,
        );

        self::assertSame(2500, $event->total->getAmount());
        self::assertSame('USD', $event->total->getCurrency()->getCurrencyCode());
    }

    // -----------------------------------------------------------------------
    // OrderPaid — produced by Order, consumed by Customer (loyalty points)
    // -----------------------------------------------------------------------

    #[Test]
    public function orderPaidEventHasRequiredFields(): void
    {
        $event = new OrderPaid(
            orderId: '550e8400-e29b-41d4-a716-446655440000',
            tenantId: TenantId::fromString(self::TENANT_ID),
            paymentId: '01926a7e-1234-7890-abcd-ef0123456789',
            paidAmountInCents: 9999,
            occurredOn: new \DateTimeImmutable(),
        );

        self::assertIsString($event->orderId);
        self::assertInstanceOf(TenantId::class, $event->tenantId);
        self::assertIsString($event->paymentId);
        self::assertIsInt($event->paidAmountInCents);
        self::assertGreaterThan(0, $event->paidAmountInCents);
        self::assertInstanceOf(\DateTimeImmutable::class, $event->occurredOn);
    }

    #[Test]
    public function orderPaidAmountIsInCents(): void
    {
        $event = new OrderPaid(
            orderId: '550e8400-e29b-41d4-a716-446655440000',
            tenantId: TenantId::fromString(self::TENANT_ID),
            paymentId: '01926a7e-1234-7890-abcd-ef0123456789',
            paidAmountInCents: 4999,
            occurredOn: new \DateTimeImmutable(),
        );

        // Consumer contract: paidAmountInCents is minor units (cents)
        self::assertSame(4999, $event->paidAmountInCents);
    }

    // -----------------------------------------------------------------------
    // PaymentCaptured — produced by Payment, consumed by Invoice/Notifications
    // -----------------------------------------------------------------------

    #[Test]
    public function paymentCapturedEventHasRequiredFields(): void
    {
        $event = new PaymentCaptured(
            paymentId: PaymentId::generate(),
            tenantId: TenantId::fromString(self::TENANT_ID),
            capturedAmount: Money::fromScalars(5000, 'EUR'),
            orderId: '550e8400-e29b-41d4-a716-446655440000',
        );

        self::assertInstanceOf(PaymentId::class, $event->paymentId);
        self::assertInstanceOf(TenantId::class, $event->tenantId);
        self::assertInstanceOf(Money::class, $event->capturedAmount);
        self::assertIsString($event->orderId);
    }

    #[Test]
    public function paymentCapturedOrderIdIsOptional(): void
    {
        $event = new PaymentCaptured(
            paymentId: PaymentId::generate(),
            tenantId: TenantId::fromString(self::TENANT_ID),
            capturedAmount: Money::fromScalars(1000, 'USD'),
        );

        self::assertNull($event->orderId);
    }

    #[Test]
    public function paymentCapturedMoneyContractMatchesOrderPlaced(): void
    {
        $orderTotal = Money::fromScalars(2500, 'USD');
        $capturedAmount = Money::fromScalars(2500, 'USD');

        // Contract: Money VO is interoperable across contexts
        self::assertSame($orderTotal->getAmount(), $capturedAmount->getAmount());
        self::assertSame($orderTotal->getCurrency(), $capturedAmount->getCurrency());
    }

    // -----------------------------------------------------------------------
    // PaymentFailed — produced by Payment, consumed by Notifications
    // -----------------------------------------------------------------------

    #[Test]
    public function paymentFailedEventHasRequiredFields(): void
    {
        $event = new PaymentFailed(
            paymentId: PaymentId::generate(),
            tenantId: TenantId::fromString(self::TENANT_ID),
            errorMessage: 'Card declined',
        );

        self::assertInstanceOf(PaymentId::class, $event->paymentId);
        self::assertInstanceOf(TenantId::class, $event->tenantId);
        self::assertIsString($event->errorMessage);
        self::assertNotEmpty($event->errorMessage);
    }

    // -----------------------------------------------------------------------
    // PaymentRefunded — produced by Payment, consumed by Notifications
    // -----------------------------------------------------------------------

    #[Test]
    public function paymentRefundedEventHasRequiredFields(): void
    {
        $event = new PaymentRefunded(
            paymentId: PaymentId::generate(),
            tenantId: TenantId::fromString(self::TENANT_ID),
            refundedAmount: Money::fromScalars(1500, 'USD'),
            reason: 'Customer return',
        );

        self::assertInstanceOf(PaymentId::class, $event->paymentId);
        self::assertInstanceOf(TenantId::class, $event->tenantId);
        self::assertInstanceOf(Money::class, $event->refundedAmount);
        self::assertIsString($event->reason);
    }

    // -----------------------------------------------------------------------
    // Shared value object contracts
    // -----------------------------------------------------------------------

    #[Test]
    public function tenantIdSerializationIsConsistentAcrossBoundary(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);

        // Both Order and Payment must serialize TenantId identically
        self::assertSame(self::TENANT_ID, $tenantId->toString());
        self::assertSame($tenantId->toString(), TenantId::fromString($tenantId->toString())->toString());
    }

    #[Test]
    public function moneyValueObjectRoundTripsAcrossBoundary(): void
    {
        $original = Money::fromScalars(9999, 'USD');
        $reconstructed = Money::fromScalars($original->getAmount(), $original->getCurrency()->getCurrencyCode());

        self::assertSame($original->getAmount(), $reconstructed->getAmount());
        self::assertSame($original->getCurrency()->getCurrencyCode(), $reconstructed->getCurrency()->getCurrencyCode());
    }
}
