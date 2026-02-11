<?php

declare(strict_types=1);

namespace App\Invoice\Application\Command\CancelInvoice;

/**
 * Command to cancel an issued invoice.
 *
 * Changes invoice status from ISSUED to CANCELLED.
 *
 * Business Rules:
 * - Only issued invoices can be cancelled
 * - Paid invoices cannot be cancelled (use credit note instead)
 * - Draft invoices should be deleted, not cancelled
 * - Cancelled invoices remain in the system for audit purposes
 */
final readonly class CancelInvoiceCommand
{
    /**
     * @param string $invoiceId Invoice identifier (UUID)
     */
    public function __construct(
        public string $invoiceId,
    ) {
    }
}
