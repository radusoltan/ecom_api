<?php

declare(strict_types=1);

namespace App\Tests\Unit\Invoice\Domain\Event;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Invoice\Domain\Event\InvoiceCancelled;
use App\Invoice\Domain\Event\InvoiceCreated;
use App\Invoice\Domain\Event\InvoiceCredited;
use App\Invoice\Domain\Event\InvoiceIssued;
use App\Invoice\Domain\Event\InvoicePaid;
use App\Invoice\Domain\Model\InvoiceId;
use App\Invoice\Domain\Model\InvoiceNumber;
use App\Order\Domain\Model\OrderId;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(InvoiceCancelled::class)]
#[CoversClass(InvoiceCreated::class)]
#[CoversClass(InvoiceCredited::class)]
#[CoversClass(InvoiceIssued::class)]
#[CoversClass(InvoicePaid::class)]
final class InvoiceEventsTest extends TestCase
{
    // -------------------------------------------------------
    // InvoiceCancelled
    // -------------------------------------------------------

    #[Test]
    public function invoiceCancelledExposesInvoiceId(): void
    {
        $invoiceId = InvoiceId::generate();
        $tenantId = TenantId::generate();

        $event = new InvoiceCancelled($invoiceId, $tenantId);

        self::assertSame($invoiceId, $event->invoiceId);
    }

    #[Test]
    public function invoiceCancelledExposesTenantId(): void
    {
        $invoiceId = InvoiceId::generate();
        $tenantId = TenantId::generate();

        $event = new InvoiceCancelled($invoiceId, $tenantId);

        self::assertSame($tenantId, $event->tenantId);
    }

    #[Test]
    public function invoiceCancelledIsReadonly(): void
    {
        $event = new InvoiceCancelled(InvoiceId::generate(), TenantId::generate());

        $reflection = new \ReflectionClass($event);
        self::assertTrue($reflection->isReadOnly());
    }

    // -------------------------------------------------------
    // InvoiceCreated
    // -------------------------------------------------------

    #[Test]
    public function invoiceCreatedExposesAllProperties(): void
    {
        $invoiceId = InvoiceId::generate();
        $tenantId = TenantId::generate();
        $orderId = OrderId::generate();
        $customerId = CustomerId::generate();

        $event = new InvoiceCreated($invoiceId, $tenantId, $orderId, $customerId);

        self::assertSame($invoiceId, $event->invoiceId);
        self::assertSame($tenantId, $event->tenantId);
        self::assertSame($orderId, $event->orderId);
        self::assertSame($customerId, $event->customerId);
    }

    #[Test]
    public function invoiceCreatedIsReadonly(): void
    {
        $event = new InvoiceCreated(
            InvoiceId::generate(),
            TenantId::generate(),
            OrderId::generate(),
            CustomerId::generate(),
        );

        $reflection = new \ReflectionClass($event);
        self::assertTrue($reflection->isReadOnly());
    }

    #[Test]
    public function invoiceCreatedHoldsCorrectInvoiceId(): void
    {
        $invoiceId = InvoiceId::generate();
        $event = new InvoiceCreated(
            $invoiceId,
            TenantId::generate(),
            OrderId::generate(),
            CustomerId::generate(),
        );

        self::assertTrue($invoiceId->equals($event->invoiceId));
    }

    // -------------------------------------------------------
    // InvoiceCredited
    // -------------------------------------------------------

    #[Test]
    public function invoiceCreditedExposesAllProperties(): void
    {
        $creditNoteId = InvoiceId::generate();
        $originalId = InvoiceId::generate();
        $tenantId = TenantId::generate();
        $creditNoteNumber = InvoiceNumber::create(2025, 1);
        $creditAmount = Money::fromScalars(5000, 'EUR');
        $occurredAt = new \DateTimeImmutable('2025-06-15 10:00:00');

        $event = new InvoiceCredited(
            creditNoteId: $creditNoteId,
            originalInvoiceId: $originalId,
            tenantId: $tenantId,
            creditNoteNumber: $creditNoteNumber,
            creditAmount: $creditAmount,
            occurredAt: $occurredAt,
        );

        self::assertSame($creditNoteId, $event->creditNoteId);
        self::assertSame($originalId, $event->originalInvoiceId);
        self::assertSame($tenantId, $event->tenantId);
        self::assertTrue($creditNoteNumber->equals($event->creditNoteNumber));
        self::assertSame(5000, $event->creditAmount->getAmount());
        self::assertSame('EUR', $event->creditAmount->currency()->getCurrencyCode());
        self::assertSame($occurredAt, $event->occurredAt);
    }

    #[Test]
    public function invoiceCreditedUsesDefaultOccurredAtWhenOmitted(): void
    {
        $before = new \DateTimeImmutable();

        $event = new InvoiceCredited(
            creditNoteId: InvoiceId::generate(),
            originalInvoiceId: InvoiceId::generate(),
            tenantId: TenantId::generate(),
            creditNoteNumber: InvoiceNumber::create(2025, 42),
            creditAmount: Money::fromScalars(1000, 'USD'),
        );

        $after = new \DateTimeImmutable();

        self::assertGreaterThanOrEqual($before->getTimestamp(), $event->occurredAt->getTimestamp());
        self::assertLessThanOrEqual($after->getTimestamp(), $event->occurredAt->getTimestamp());
    }

    #[Test]
    public function invoiceCreditedIsReadonly(): void
    {
        $event = new InvoiceCredited(
            creditNoteId: InvoiceId::generate(),
            originalInvoiceId: InvoiceId::generate(),
            tenantId: TenantId::generate(),
            creditNoteNumber: InvoiceNumber::create(2025, 1),
            creditAmount: Money::fromScalars(100, 'USD'),
        );

        $reflection = new \ReflectionClass($event);
        self::assertTrue($reflection->isReadOnly());
    }

    // -------------------------------------------------------
    // InvoiceIssued
    // -------------------------------------------------------

    #[Test]
    public function invoiceIssuedExposesAllProperties(): void
    {
        $invoiceId = InvoiceId::generate();
        $tenantId = TenantId::generate();
        $invoiceNumber = InvoiceNumber::create(2025, 100);
        $issueDate = new \DateTimeImmutable('2025-03-01');
        $total = Money::fromScalars(12000, 'USD');

        $event = new InvoiceIssued($invoiceId, $tenantId, $invoiceNumber, $issueDate, $total);

        self::assertSame($invoiceId, $event->invoiceId);
        self::assertSame($tenantId, $event->tenantId);
        self::assertTrue($invoiceNumber->equals($event->invoiceNumber));
        self::assertSame($issueDate, $event->issueDate);
        self::assertSame(12000, $event->total->getAmount());
        self::assertSame('USD', $event->total->currency()->getCurrencyCode());
    }

    #[Test]
    public function invoiceIssuedIsReadonly(): void
    {
        $event = new InvoiceIssued(
            InvoiceId::generate(),
            TenantId::generate(),
            InvoiceNumber::create(2025, 1),
            new \DateTimeImmutable(),
            Money::fromScalars(0, 'USD'),
        );

        $reflection = new \ReflectionClass($event);
        self::assertTrue($reflection->isReadOnly());
    }

    #[Test]
    public function invoiceIssuedInvoiceNumberFormatIsCorrect(): void
    {
        $invoiceNumber = InvoiceNumber::create(2025, 7);
        $event = new InvoiceIssued(
            InvoiceId::generate(),
            TenantId::generate(),
            $invoiceNumber,
            new \DateTimeImmutable(),
            Money::fromScalars(0, 'USD'),
        );

        self::assertSame('INV-2025-000007', $event->invoiceNumber->toString());
    }

    // -------------------------------------------------------
    // InvoicePaid
    // -------------------------------------------------------

    #[Test]
    public function invoicePaidExposesAllProperties(): void
    {
        $invoiceId = InvoiceId::generate();
        $tenantId = TenantId::generate();
        $paidDate = new \DateTimeImmutable('2025-04-10');
        $total = Money::fromScalars(8500, 'GBP');

        $event = new InvoicePaid($invoiceId, $tenantId, $paidDate, $total);

        self::assertSame($invoiceId, $event->invoiceId);
        self::assertSame($tenantId, $event->tenantId);
        self::assertSame($paidDate, $event->paidDate);
        self::assertSame(8500, $event->total->getAmount());
        self::assertSame('GBP', $event->total->currency()->getCurrencyCode());
    }

    #[Test]
    public function invoicePaidIsReadonly(): void
    {
        $event = new InvoicePaid(
            InvoiceId::generate(),
            TenantId::generate(),
            new \DateTimeImmutable(),
            Money::fromScalars(1000, 'USD'),
        );

        $reflection = new \ReflectionClass($event);
        self::assertTrue($reflection->isReadOnly());
    }

    #[Test]
    public function invoicePaidPaidDateIsPreserved(): void
    {
        $paidDate = new \DateTimeImmutable('2025-12-31 23:59:59');
        $event = new InvoicePaid(
            InvoiceId::generate(),
            TenantId::generate(),
            $paidDate,
            Money::fromScalars(1000, 'EUR'),
        );

        self::assertSame('2025-12-31', $event->paidDate->format('Y-m-d'));
    }
}
