<?php

declare(strict_types=1);

namespace App\Invoice\Domain\Model;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Invoice\Domain\Event\InvoiceCancelled;
use App\Invoice\Domain\Event\InvoiceCreated;
use App\Invoice\Domain\Event\InvoiceIssued;
use App\Invoice\Domain\Event\InvoicePaid;
use App\Order\Domain\Model\OrderId;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Invoice Aggregate Root.
 *
 * Business Rules:
 * - Invoice in DRAFT status can be modified (add/remove lines)
 * - Invoice becomes immutable after issue()
 * - Tax breakdown groups tax amounts by rate (e.g., {'20.00': 2000, '10.00': 500})
 * - Totals auto-recalculate when lines change
 * - Due date defaults to issue_date + 30 days if not specified
 * - Reverse charge invoices show 0% tax but note "Reverse Charge applies"
 * - Invoice must have at least one line
 * - Cannot modify, issue, or mark as paid if already in final state
 * - Subtotal = sum of all line totals (excluding tax)
 * - Tax amount = sum of all line tax amounts
 * - Total = subtotal + tax amount
 */
final class Invoice extends AggregateRoot
{
    /** @var InvoiceLine[] */
    private array $lines = [];

    private function __construct(
        private InvoiceId $id,
        private TenantId $tenantId,
        private OrderId $orderId,
        private CustomerId $customerId,
        private ?InvoiceNumber $number,
        private InvoiceStatus $status,
        private BillingAddress $billingAddress,
        private Money $subtotal,
        private Money $taxAmount,
        private Money $total,
        /** @var array<string, int> */
        private array $taxBreakdown,
        private bool $isReverseCharge,
        private ?\DateTimeImmutable $issueDate,
        private ?\DateTimeImmutable $dueDate,
        private ?\DateTimeImmutable $paidDate,
        private ?string $pdfPath,
        private ?InvoiceId $creditedInvoiceId,
        private ?string $notes,
        private \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt
    ) {
    }

    /**
     * Create a new draft invoice.
     */
    public static function createDraft(
        InvoiceId $id,
        TenantId $tenantId,
        OrderId $orderId,
        CustomerId $customerId,
        BillingAddress $billingAddress,
        bool $isReverseCharge = false,
        ?string $notes = null
    ): self {
        $now = new \DateTimeImmutable();
        $currency = 'USD'; // Default currency, should be retrieved from tenant/order

        $invoice = new self(
            id: $id,
            tenantId: $tenantId,
            orderId: $orderId,
            customerId: $customerId,
            number: null, // Invoice number assigned when issued
            status: InvoiceStatus::DRAFT,
            billingAddress: $billingAddress,
            subtotal: Money::fromScalars(0, $currency),
            taxAmount: Money::fromScalars(0, $currency),
            total: Money::fromScalars(0, $currency),
            taxBreakdown: [],
            isReverseCharge: $isReverseCharge,
            issueDate: null,
            dueDate: null,
            paidDate: null,
            pdfPath: null,
            creditedInvoiceId: null,
            notes: $notes ? trim($notes) : null,
            createdAt: $now,
            updatedAt: $now
        );

        $invoice->recordEvent(new InvoiceCreated($id, $tenantId, $orderId, $customerId));

        return $invoice;
    }

    /**
     * Reconstitute invoice from persistence.
     *
     * @param InvoiceLine[] $lines
     * @param array<string, int> $taxBreakdown
     */
    public static function reconstituteFromPersistence(
        InvoiceId $id,
        TenantId $tenantId,
        OrderId $orderId,
        CustomerId $customerId,
        ?InvoiceNumber $number,
        InvoiceStatus $status,
        BillingAddress $billingAddress,
        array $lines,
        Money $subtotal,
        Money $taxAmount,
        Money $total,
        array $taxBreakdown,
        bool $isReverseCharge,
        ?\DateTimeImmutable $issueDate,
        ?\DateTimeImmutable $dueDate,
        ?\DateTimeImmutable $paidDate,
        ?string $pdfPath,
        ?InvoiceId $creditedInvoiceId,
        ?string $notes,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt
    ): self {
        $invoice = new self(
            id: $id,
            tenantId: $tenantId,
            orderId: $orderId,
            customerId: $customerId,
            number: $number,
            status: $status,
            billingAddress: $billingAddress,
            subtotal: $subtotal,
            taxAmount: $taxAmount,
            total: $total,
            taxBreakdown: $taxBreakdown,
            isReverseCharge: $isReverseCharge,
            issueDate: $issueDate,
            dueDate: $dueDate,
            paidDate: $paidDate,
            pdfPath: $pdfPath,
            creditedInvoiceId: $creditedInvoiceId,
            notes: $notes,
            createdAt: $createdAt,
            updatedAt: $updatedAt
        );

        $invoice->lines = $lines;

        return $invoice;
    }

    /**
     * Add a line item to the invoice (only in DRAFT status).
     */
    public function addLine(InvoiceLine $line): void
    {
        if (!$this->canBeModified()) {
            throw new \InvalidArgumentException(sprintf('Cannot add line to invoice with status: %s', $this->status->value));
        }

        $this->lines[] = $line;
        $this->recalculateTotals();
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Remove a line item from the invoice (only in DRAFT status).
     */
    public function removeLine(InvoiceLineId $lineId): void
    {
        if (!$this->canBeModified()) {
            throw new \InvalidArgumentException(sprintf('Cannot remove line from invoice with status: %s', $this->status->value));
        }

        $this->lines = array_filter(
            $this->lines,
            fn(InvoiceLine $line) => !$line->id()->equals($lineId)
        );

        // Re-index array after removal
        $this->lines = array_values($this->lines);

        $this->recalculateTotals();
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Issue the invoice (assign invoice number and make immutable).
     */
    public function issue(InvoiceNumber $number, \DateTimeImmutable $issueDate, ?\DateTimeImmutable $dueDate = null): void
    {
        if (!$this->status->isDraft()) {
            throw new \InvalidArgumentException(sprintf('Cannot issue invoice with status: %s. Only draft invoices can be issued', $this->status->value));
        }

        if (empty($this->lines)) {
            throw new \InvalidArgumentException('Cannot issue invoice without line items');
        }

        // Default due date to 30 days after issue date if not specified
        if (null === $dueDate) {
            $dueDate = $issueDate->modify('+30 days');
        }

        // Ensure due date is not before issue date
        if ($dueDate < $issueDate) {
            throw new \InvalidArgumentException('Due date cannot be before issue date');
        }

        $this->number = $number;
        $this->status = InvoiceStatus::ISSUED;
        $this->issueDate = $issueDate;
        $this->dueDate = $dueDate;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new InvoiceIssued($this->id, $this->tenantId, $number, $issueDate, $this->total));
    }

    /**
     * Mark invoice as paid.
     */
    public function markAsPaid(\DateTimeImmutable $paidDate): void
    {
        if (!$this->status->isIssued()) {
            throw new \InvalidArgumentException(sprintf('Cannot mark invoice as paid with status: %s. Only issued invoices can be marked as paid', $this->status->value));
        }

        // Ensure paid date is not before issue date
        if (null !== $this->issueDate && $paidDate < $this->issueDate) {
            throw new \InvalidArgumentException('Paid date cannot be before issue date');
        }

        $this->status = InvoiceStatus::PAID;
        $this->paidDate = $paidDate;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new InvoicePaid($this->id, $this->tenantId, $paidDate, $this->total));
    }

    /**
     * Cancel the invoice (only ISSUED invoices can be cancelled).
     */
    public function cancel(): void
    {
        if (!$this->status->isIssued()) {
            throw new \InvalidArgumentException(sprintf('Cannot cancel invoice with status: %s. Only issued invoices can be cancelled', $this->status->value));
        }

        $this->status = InvoiceStatus::CANCELLED;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new InvoiceCancelled($this->id, $this->tenantId));
    }

    /**
     * Set the PDF file path.
     */
    public function setPdfPath(string $path): void
    {
        if (empty(trim($path))) {
            throw new \InvalidArgumentException('PDF path cannot be empty');
        }

        $this->pdfPath = trim($path);
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Recalculate all totals based on current lines.
     */
    public function recalculateTotals(): void
    {
        if (empty($this->lines)) {
            $currencyCode = $this->subtotal->currency()->getCurrencyCode();
            $this->subtotal = Money::fromScalars(0, $currencyCode);
            $this->taxAmount = Money::fromScalars(0, $currencyCode);
            $this->total = Money::fromScalars(0, $currencyCode);
            $this->taxBreakdown = [];

            return;
        }

        // Get currency from first line
        $currencyCode = $this->lines[0]->unitPrice()->currency()->getCurrencyCode();

        $subtotal = Money::fromScalars(0, $currencyCode);
        $taxAmount = Money::fromScalars(0, $currencyCode);

        foreach ($this->lines as $line) {
            $subtotal = $subtotal->add($line->lineTotal());
            $taxAmount = $taxAmount->add($line->taxAmount());
        }

        $this->subtotal = $subtotal;
        $this->taxAmount = $this->isReverseCharge ? Money::fromScalars(0, $currencyCode) : $taxAmount;
        $this->total = $subtotal->add($this->taxAmount);
        $this->taxBreakdown = $this->calculateTaxBreakdown();
    }

    /**
     * Calculate tax breakdown grouped by tax rate.
     *
     * @return array<string, int> Tax rate (as string) => amount in minor units
     */
    private function calculateTaxBreakdown(): array
    {
        if ($this->isReverseCharge) {
            return ['0.00' => 0];
        }

        $breakdown = [];

        foreach ($this->lines as $line) {
            $rateKey = number_format($line->taxRate(), 2, '.', '');
            $taxMinorUnits = $line->taxAmount()->getAmount();

            if (!isset($breakdown[$rateKey])) {
                $breakdown[$rateKey] = 0;
            }

            $breakdown[$rateKey] += $taxMinorUnits;
        }

        // Sort by rate descending and ensure string keys
        krsort($breakdown);

        /** @var array<string, int> */
        return $breakdown;
    }

    /**
     * Check if invoice can be modified (only DRAFT invoices).
     */
    public function canBeModified(): bool
    {
        return $this->status->isDraft();
    }

    /**
     * Check if invoice is overdue.
     */
    public function isOverdue(): bool
    {
        if (null === $this->dueDate || !$this->status->isIssued()) {
            return false;
        }

        return new \DateTimeImmutable() > $this->dueDate;
    }

    /**
     * Get number of days overdue (negative if not yet due).
     */
    public function getDaysOverdue(): int
    {
        if (null === $this->dueDate) {
            return 0;
        }

        $now = new \DateTimeImmutable();
        $diff = $now->diff($this->dueDate);

        return $diff->invert ? (int) $diff->days : -(int) $diff->days;
    }

    // Getters

    public function id(): InvoiceId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function orderId(): OrderId
    {
        return $this->orderId;
    }

    public function customerId(): CustomerId
    {
        return $this->customerId;
    }

    public function number(): ?InvoiceNumber
    {
        return $this->number;
    }

    public function status(): InvoiceStatus
    {
        return $this->status;
    }

    public function billingAddress(): BillingAddress
    {
        return $this->billingAddress;
    }

    /**
     * @return InvoiceLine[]
     */
    public function lines(): array
    {
        return $this->lines;
    }

    public function subtotal(): Money
    {
        return $this->subtotal;
    }

    public function taxAmount(): Money
    {
        return $this->taxAmount;
    }

    public function total(): Money
    {
        return $this->total;
    }

    /**
     * @return array<string, int>
     */
    public function taxBreakdown(): array
    {
        return $this->taxBreakdown;
    }

    public function isReverseCharge(): bool
    {
        return $this->isReverseCharge;
    }

    public function issueDate(): ?\DateTimeImmutable
    {
        return $this->issueDate;
    }

    public function dueDate(): ?\DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function paidDate(): ?\DateTimeImmutable
    {
        return $this->paidDate;
    }

    public function pdfPath(): ?string
    {
        return $this->pdfPath;
    }

    public function creditedInvoiceId(): ?InvoiceId
    {
        return $this->creditedInvoiceId;
    }

    public function notes(): ?string
    {
        return $this->notes;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
