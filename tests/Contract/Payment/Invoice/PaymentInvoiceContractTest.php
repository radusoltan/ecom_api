<?php

declare(strict_types=1);

namespace App\Tests\Contract\Payment\Invoice;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Invoice\Domain\Event\InvoiceCreated;
use App\Invoice\Domain\Model\InvoiceId;
use App\Order\Domain\Model\OrderId;
use App\Payment\Domain\Event\PaymentCaptured;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Contract tests for the Payment <-> Invoice boundary.
 *
 * Verifies that PaymentCaptured events carry all fields needed by the
 * Invoice context's PaymentCompletedInvoiceSubscriber.
 *
 * Producer: Payment (PaymentCaptured)
 * Consumer: Invoice (PaymentCompletedInvoiceSubscriber -> GenerateInvoiceCommand)
 */
#[CoversNothing]
#[Group('contract')]
final class PaymentInvoiceContractTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    // -----------------------------------------------------------------------
    // PaymentCaptured — consumed by Invoice subscriber
    // -----------------------------------------------------------------------

    #[Test]
    public function paymentCapturedCarriesFieldsInvoiceSubscriberNeeds(): void
    {
        $event = new PaymentCaptured(
            paymentId: PaymentId::generate(),
            tenantId: TenantId::fromString(self::TENANT_ID),
            capturedAmount: Money::fromScalars(9999, 'USD'),
            orderId: '550e8400-e29b-41d4-a716-446655440000',
        );

        // Invoice subscriber needs tenantId to find order
        self::assertInstanceOf(TenantId::class, $event->tenantId);
        // Invoice subscriber needs orderId to fetch order details
        self::assertNotNull($event->orderId);
        // Invoice subscriber needs capturedAmount for invoice total
        self::assertInstanceOf(Money::class, $event->capturedAmount);
    }

    #[Test]
    public function paymentCapturedWithoutOrderIdCannotGenerateInvoice(): void
    {
        $event = new PaymentCaptured(
            paymentId: PaymentId::generate(),
            tenantId: TenantId::fromString(self::TENANT_ID),
            capturedAmount: Money::fromScalars(1000, 'USD'),
        );

        // Contract: orderId null means invoice subscriber should skip
        self::assertNull($event->orderId);
    }

    // -----------------------------------------------------------------------
    // InvoiceCreated — produced by Invoice, references Order + Customer
    // -----------------------------------------------------------------------

    #[Test]
    public function invoiceCreatedHasRequiredCrossContextReferences(): void
    {
        $event = new InvoiceCreated(
            invoiceId: InvoiceId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            tenantId: TenantId::fromString(self::TENANT_ID),
            orderId: OrderId::fromString('660e8400-e29b-41d4-a716-446655440001'),
            customerId: CustomerId::fromString('770e8400-e29b-41d4-a716-446655440002'),
        );

        self::assertInstanceOf(InvoiceId::class, $event->invoiceId);
        self::assertInstanceOf(TenantId::class, $event->tenantId);
        // Cross-context references
        self::assertInstanceOf(OrderId::class, $event->orderId);
        self::assertInstanceOf(CustomerId::class, $event->customerId);
    }

    // -----------------------------------------------------------------------
    // Cross-boundary ID type contracts
    // -----------------------------------------------------------------------

    #[Test]
    public function paymentIdSerializesConsistently(): void
    {
        $id = PaymentId::generate();
        $serialized = $id->toString();

        self::assertNotEmpty($serialized);
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $serialized
        );
    }

    #[Test]
    public function invoiceIdSerializesConsistently(): void
    {
        $id = InvoiceId::fromString('550e8400-e29b-41d4-a716-446655440000');
        self::assertSame('550e8400-e29b-41d4-a716-446655440000', $id->toString());
    }

    #[Test]
    public function moneyAmountIsAlwaysInMinorUnits(): void
    {
        // Contract: Money amounts across Payment and Invoice are in cents
        $money = Money::fromScalars(2500, 'USD');

        self::assertIsInt($money->getAmount());
        self::assertSame(2500, $money->getAmount());
        self::assertSame('USD', $money->getCurrency()->getCurrencyCode());
    }
}
