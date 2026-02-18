<?php

declare(strict_types=1);

namespace App\Invoice\Domain\Event;

use App\Invoice\Domain\Model\InvoiceId;
use App\Invoice\Domain\Model\InvoiceNumber;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * InvoiceCredited Domain Event.
 *
 * Raised when a credit note is issued for an original invoice.
 *
 * Business Context:
 * - A new invoice (credit note) is created to reverse or partially refund an original invoice
 * - Credit note has its own invoice number (e.g., INV-2025-000123)
 * - References the original invoice being credited
 * - Credit amount may be partial or full
 * - Can be used by subscribers for:
 *   - Sending credit note emails to customers
 *   - Updating accounting records
 *   - Refunding customer payments
 *   - Updating customer account balance
 *   - Compliance reporting (VAT adjustments)
 */
final readonly class InvoiceCredited
{
    public function __construct(
        public InvoiceId $creditNoteId,
        public InvoiceId $originalInvoiceId,
        public TenantId $tenantId,
        public InvoiceNumber $creditNoteNumber,
        public Money $creditAmount,
        public \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {
    }
}
