<?php

declare(strict_types=1);

namespace App\Tests\Unit\Invoice\Domain\Model;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Invoice\Domain\Event\InvoiceCancelled;
use App\Invoice\Domain\Event\InvoiceCreated;
use App\Invoice\Domain\Event\InvoiceIssued;
use App\Invoice\Domain\Event\InvoicePaid;
use App\Invoice\Domain\Model\BillingAddress;
use App\Invoice\Domain\Model\Invoice;
use App\Invoice\Domain\Model\InvoiceId;
use App\Invoice\Domain\Model\InvoiceLine;
use App\Invoice\Domain\Model\InvoiceLineId;
use App\Invoice\Domain\Model\InvoiceNumber;
use App\Invoice\Domain\Model\InvoiceStatus;
use App\Order\Domain\Model\OrderId;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Invoice::class)]
final class InvoiceTest extends TestCase
{
    private InvoiceId $invoiceId;
    private TenantId $tenantId;
    private OrderId $orderId;
    private CustomerId $customerId;
    private BillingAddress $billingAddress;

    protected function setUp(): void
    {
        $this->invoiceId = InvoiceId::generate();
        $this->tenantId = TenantId::generate();
        $this->orderId = OrderId::generate();
        $this->customerId = CustomerId::generate();
        $this->billingAddress = BillingAddress::create(
            'John Doe',
            '123 Main Street',
            null,
            'New York',
            '10001',
            'US'
        );
    }

    #[Test]
    public function createDraftCreatesInvoiceWithCorrectState(): void
    {
        $invoice = $this->createDraftInvoice();

        self::assertTrue($invoice->id()->equals($this->invoiceId));
        self::assertTrue($invoice->tenantId()->equals($this->tenantId));
        self::assertSame(InvoiceStatus::DRAFT, $invoice->status());
        self::assertTrue($invoice->canBeModified());
        self::assertNull($invoice->number());
        self::assertNull($invoice->issueDate());
        self::assertNull($invoice->dueDate());
        self::assertNull($invoice->paidDate());
        self::assertNull($invoice->pdfPath());
        self::assertEmpty($invoice->lines());
        self::assertTrue($invoice->subtotal()->isZero());
        self::assertTrue($invoice->total()->isZero());
    }

    #[Test]
    public function createDraftRecordsInvoiceCreatedEvent(): void
    {
        $invoice = $this->createDraftInvoice();

        $events = $invoice->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(InvoiceCreated::class, $events[0]);
        self::assertTrue($events[0]->invoiceId->equals($this->invoiceId));
    }

    #[Test]
    public function createDraftWithNotesStoresNotes(): void
    {
        $invoice = Invoice::createDraft(
            $this->invoiceId,
            $this->tenantId,
            $this->orderId,
            $this->customerId,
            $this->billingAddress,
            notes: 'Special instructions for delivery'
        );

        self::assertSame('Special instructions for delivery', $invoice->notes());
    }

    #[Test]
    public function addLineTosDraftInvoice(): void
    {
        $invoice = $this->createDraftInvoice();
        $line = $this->createLine(quantity: 2, unitPriceAmount: 1000, taxRate: 20.0);

        $invoice->addLine($line);

        self::assertCount(1, $invoice->lines());
        self::assertSame(2000, $invoice->subtotal()->getAmount());
        self::assertSame(400, $invoice->taxAmount()->getAmount());
        self::assertSame(2400, $invoice->total()->getAmount());
    }

    #[Test]
    public function addMultipleLinesRecalculatesTotals(): void
    {
        $invoice = $this->createDraftInvoice();
        $invoice->addLine($this->createLine(quantity: 1, unitPriceAmount: 1000, taxRate: 20.0));
        $invoice->addLine($this->createLine(quantity: 3, unitPriceAmount: 500, taxRate: 10.0));

        self::assertCount(2, $invoice->lines());
        self::assertSame(2500, $invoice->subtotal()->getAmount()); // 1000 + 1500
        self::assertSame(350, $invoice->taxAmount()->getAmount());  // 200 + 150
        self::assertSame(2850, $invoice->total()->getAmount());
    }

    #[Test]
    public function addLineToIssuedInvoiceThrowsException(): void
    {
        $invoice = $this->createIssuedInvoice();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot add line');

        $invoice->addLine($this->createLine());
    }

    #[Test]
    public function removeLineFromDraftInvoice(): void
    {
        $invoice = $this->createDraftInvoice();
        $line1 = $this->createLine(quantity: 1, unitPriceAmount: 1000, taxRate: 20.0);
        $line2 = $this->createLine(quantity: 2, unitPriceAmount: 500, taxRate: 10.0);
        $invoice->addLine($line1);
        $invoice->addLine($line2);

        $invoice->removeLine($line1->id());

        self::assertCount(1, $invoice->lines());
        self::assertSame(1000, $invoice->subtotal()->getAmount());
    }

    #[Test]
    public function removeLineFromIssuedInvoiceThrowsException(): void
    {
        $invoice = $this->createIssuedInvoice();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot remove line');

        $invoice->removeLine(InvoiceLineId::generate());
    }

    #[Test]
    public function issueInvoiceTransitionsToIssuedStatus(): void
    {
        $invoice = $this->createDraftInvoice();
        $invoice->addLine($this->createLine());
        $invoice->popEvents();

        $number = InvoiceNumber::create(2025, 1);
        $issueDate = new \DateTimeImmutable('2025-03-01');
        $invoice->issue($number, $issueDate);

        self::assertSame(InvoiceStatus::ISSUED, $invoice->status());
        self::assertTrue($invoice->number()->equals($number));
        self::assertSame('2025-03-01', $invoice->issueDate()->format('Y-m-d'));
        self::assertSame('2025-03-31', $invoice->dueDate()->format('Y-m-d'));
        self::assertFalse($invoice->canBeModified());
    }

    #[Test]
    public function issueInvoiceRecordsIssuedEvent(): void
    {
        $invoice = $this->createDraftInvoice();
        $invoice->addLine($this->createLine());
        $invoice->popEvents();

        $number = InvoiceNumber::create(2025, 1);
        $invoice->issue($number, new \DateTimeImmutable());

        $events = $invoice->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(InvoiceIssued::class, $events[0]);
        self::assertTrue($events[0]->invoiceNumber->equals($number));
    }

    #[Test]
    public function issueWithCustomDueDateUsesIt(): void
    {
        $invoice = $this->createDraftInvoice();
        $invoice->addLine($this->createLine());

        $issueDate = new \DateTimeImmutable('2025-03-01');
        $dueDate = new \DateTimeImmutable('2025-06-01');
        $invoice->issue(InvoiceNumber::create(2025, 1), $issueDate, $dueDate);

        self::assertSame('2025-06-01', $invoice->dueDate()->format('Y-m-d'));
    }

    #[Test]
    public function issueDueDateBeforeIssueDateThrowsException(): void
    {
        $invoice = $this->createDraftInvoice();
        $invoice->addLine($this->createLine());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Due date cannot be before issue date');

        $invoice->issue(
            InvoiceNumber::create(2025, 1),
            new \DateTimeImmutable('2025-03-01'),
            new \DateTimeImmutable('2025-02-01')
        );
    }

    #[Test]
    public function issueWithNoLinesThrowsException(): void
    {
        $invoice = $this->createDraftInvoice();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('without line items');

        $invoice->issue(InvoiceNumber::create(2025, 1), new \DateTimeImmutable());
    }

    #[Test]
    public function issueAlreadyIssuedInvoiceThrowsException(): void
    {
        $invoice = $this->createIssuedInvoice();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Only draft invoices');

        $invoice->issue(InvoiceNumber::create(2025, 2), new \DateTimeImmutable());
    }

    #[Test]
    public function markAsPaidTransitionsToPaidStatus(): void
    {
        $invoice = $this->createIssuedInvoice();
        $invoice->popEvents();

        $paidDate = new \DateTimeImmutable('2025-03-15');
        $invoice->markAsPaid($paidDate);

        self::assertSame(InvoiceStatus::PAID, $invoice->status());
        self::assertSame('2025-03-15', $invoice->paidDate()->format('Y-m-d'));
    }

    #[Test]
    public function markAsPaidRecordsPaidEvent(): void
    {
        $invoice = $this->createIssuedInvoice();
        $invoice->popEvents();

        $invoice->markAsPaid(new \DateTimeImmutable());

        $events = $invoice->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(InvoicePaid::class, $events[0]);
    }

    #[Test]
    public function markAsPaidBeforeIssueDateThrowsException(): void
    {
        $invoice = $this->createIssuedInvoice();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Paid date cannot be before issue date');

        $invoice->markAsPaid(new \DateTimeImmutable('2020-01-01'));
    }

    #[Test]
    public function markDraftAsPaidThrowsException(): void
    {
        $invoice = $this->createDraftInvoice();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Only issued invoices');

        $invoice->markAsPaid(new \DateTimeImmutable());
    }

    #[Test]
    public function cancelIssuedInvoice(): void
    {
        $invoice = $this->createIssuedInvoice();
        $invoice->popEvents();

        $invoice->cancel();

        self::assertSame(InvoiceStatus::CANCELLED, $invoice->status());
    }

    #[Test]
    public function cancelRecordsCancelledEvent(): void
    {
        $invoice = $this->createIssuedInvoice();
        $invoice->popEvents();

        $invoice->cancel();

        $events = $invoice->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(InvoiceCancelled::class, $events[0]);
    }

    #[Test]
    public function cancelDraftInvoiceThrowsException(): void
    {
        $invoice = $this->createDraftInvoice();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Only issued invoices');

        $invoice->cancel();
    }

    #[Test]
    public function cancelPaidInvoiceThrowsException(): void
    {
        $invoice = $this->createIssuedInvoice();
        $invoice->markAsPaid(new \DateTimeImmutable());

        $this->expectException(\InvalidArgumentException::class);

        $invoice->cancel();
    }

    #[Test]
    public function reverseChargeInvoiceHasZeroTax(): void
    {
        $invoice = Invoice::createDraft(
            $this->invoiceId,
            $this->tenantId,
            $this->orderId,
            $this->customerId,
            $this->billingAddress,
            isReverseCharge: true
        );

        $invoice->addLine($this->createLine(quantity: 2, unitPriceAmount: 1000, taxRate: 20.0));

        self::assertTrue($invoice->isReverseCharge());
        self::assertSame(0, $invoice->taxAmount()->getAmount());
        self::assertSame(2000, $invoice->total()->getAmount()); // subtotal only
    }

    #[Test]
    public function reverseChargeTaxBreakdownShowsZero(): void
    {
        $invoice = Invoice::createDraft(
            $this->invoiceId,
            $this->tenantId,
            $this->orderId,
            $this->customerId,
            $this->billingAddress,
            isReverseCharge: true
        );

        $invoice->addLine($this->createLine(quantity: 1, unitPriceAmount: 1000, taxRate: 20.0));

        self::assertSame(['0.00' => 0], $invoice->taxBreakdown());
    }

    #[Test]
    public function taxBreakdownGroupsByRate(): void
    {
        $invoice = $this->createDraftInvoice();
        $invoice->addLine($this->createLine(quantity: 1, unitPriceAmount: 1000, taxRate: 20.0));
        $invoice->addLine($this->createLine(quantity: 1, unitPriceAmount: 500, taxRate: 10.0));
        $invoice->addLine($this->createLine(quantity: 1, unitPriceAmount: 2000, taxRate: 20.0));

        $breakdown = $invoice->taxBreakdown();

        self::assertArrayHasKey('20.00', $breakdown);
        self::assertArrayHasKey('10.00', $breakdown);
        self::assertSame(600, $breakdown['20.00']); // 200 + 400
        self::assertSame(50, $breakdown['10.00']);
    }

    #[Test]
    public function setPdfPathSetsPath(): void
    {
        $invoice = $this->createDraftInvoice();

        $invoice->setPdfPath('/storage/invoices/INV-2025-000001.pdf');

        self::assertSame('/storage/invoices/INV-2025-000001.pdf', $invoice->pdfPath());
    }

    #[Test]
    public function setPdfPathWithEmptyPathThrowsException(): void
    {
        $invoice = $this->createDraftInvoice();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('PDF path cannot be empty');

        $invoice->setPdfPath('   ');
    }

    #[Test]
    public function isOverdueReturnsFalseForDraftInvoice(): void
    {
        $invoice = $this->createDraftInvoice();
        self::assertFalse($invoice->isOverdue());
    }

    #[Test]
    public function isOverdueReturnsFalseForPaidInvoice(): void
    {
        $invoice = $this->createIssuedInvoice();
        $invoice->markAsPaid(new \DateTimeImmutable());
        self::assertFalse($invoice->isOverdue());
    }

    #[Test]
    public function reconstituteDoesNotRecordEvents(): void
    {
        $invoice = Invoice::reconstituteFromPersistence(
            $this->invoiceId,
            $this->tenantId,
            $this->orderId,
            $this->customerId,
            null,
            InvoiceStatus::DRAFT,
            $this->billingAddress,
            [],
            Money::fromScalars(0, 'USD'),
            Money::fromScalars(0, 'USD'),
            Money::fromScalars(0, 'USD'),
            [],
            false,
            null,
            null,
            null,
            null,
            null,
            null,
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );

        self::assertEmpty($invoice->popEvents());
    }

    private function createDraftInvoice(): Invoice
    {
        return Invoice::createDraft(
            $this->invoiceId,
            $this->tenantId,
            $this->orderId,
            $this->customerId,
            $this->billingAddress
        );
    }

    private function createIssuedInvoice(): Invoice
    {
        $invoice = $this->createDraftInvoice();
        $invoice->addLine($this->createLine());
        $invoice->issue(InvoiceNumber::create(2025, 1), new \DateTimeImmutable('2025-03-01'));

        return $invoice;
    }

    private function createLine(
        int $quantity = 1,
        int $unitPriceAmount = 1000,
        float $taxRate = 20.0,
    ): InvoiceLine {
        return InvoiceLine::create(
            InvoiceLineId::generate(),
            'Test Product Item',
            $quantity,
            Money::fromScalars($unitPriceAmount, 'USD'),
            $taxRate
        );
    }
}
