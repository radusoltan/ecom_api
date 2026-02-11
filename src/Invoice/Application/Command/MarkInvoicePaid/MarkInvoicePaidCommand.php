<?php

declare(strict_types=1);

namespace App\Invoice\Application\Command\MarkInvoicePaid;

/**
 * Command to mark an issued invoice as paid.
 *
 * Updates invoice status from ISSUED to PAID and records payment date.
 *
 * Business Rules:
 * - Only issued invoices can be marked as paid
 * - Paid date cannot be before issue date
 * - Invoice cannot be modified after being marked as paid
 */
final readonly class MarkInvoicePaidCommand
{
    /**
     * @param string $invoiceId Invoice identifier (UUID)
     * @param string $paidDate  Payment date (ISO 8601 format)
     */
    public function __construct(
        public string $invoiceId,
        public string $paidDate,
    ) {
    }
}
